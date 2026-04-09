<?php

namespace App\Http\Controllers;

use App\Contracts\RepositoryDriverInterface;
use App\Http\Controllers\Concerns\InteractsWithGitTransport;
use App\Models\Repository;
use App\Services\LfsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LfsBatchController extends Controller
{
    use InteractsWithGitTransport;

    public function __construct(
        protected LfsService $lfsService,
        protected RepositoryDriverInterface $repositoryDriver,
    ) {}

    public function batch(Request $request, Repository $repository): JsonResponse
    {
        $operation = $request->string('operation')->toString();

        if (! in_array($operation, ['upload', 'download'], true)) {
            return response()->json([
                'message' => 'The operation field must be either upload or download.',
            ], 422);
        }

        if (! $this->lfsService->isEnabledFor($repository)) {
            return response()->json([
                'message' => 'Git LFS is disabled for this repository.',
            ], 409);
        }

        $this->authorize($operation === 'download' ? 'view' : 'push', $repository);

        $validated = $request->validate([
            'operation' => ['required', 'string'],
            'objects' => ['required', 'array', 'min:1'],
            'objects.*.oid' => ['required', 'string', 'size:64'],
            'objects.*.size' => ['required', 'integer', 'min:0'],
        ]);

        return response()->json(
            $this->lfsService->batch($repository, $operation, $validated['objects'])
        );
    }

    public function transportBatch(Request $request, string $org, string $repo): JsonResponse|Response
    {
        $repository = $this->resolveTransportRepository($org, $repo);

        $this->ensureNativeGitTransportIsActive();

        if (! $this->lfsService->isEnabledFor($repository)) {
            return response()->json([
                'message' => 'Git LFS is disabled for this repository.',
            ], 409, [
                'Content-Type' => 'application/vnd.git-lfs+json',
            ]);
        }

        $operation = $request->string('operation')->toString();

        if (! in_array($operation, ['upload', 'download'], true)) {
            return response()->json([
                'message' => 'The operation field must be either upload or download.',
            ], 422, [
                'Content-Type' => 'application/vnd.git-lfs+json',
            ]);
        }

        if ($operation === 'upload') {
            if ($repository->is_archived) {
                abort(403, 'Repository is archived and read-only.');
            }

            if ($challenge = $this->checkTransportWriteAccess($repository)) {
                return $challenge;
            }
        } elseif ($challenge = $this->checkTransportReadAccess($repository)) {
            return $challenge;
        }

        $validated = $request->validate([
            'operation' => ['required', 'string'],
            'objects' => ['required', 'array', 'min:1'],
            'objects.*.oid' => ['required', 'string', 'size:64'],
            'objects.*.size' => ['required', 'integer', 'min:0'],
        ]);

        return response()->json(
            $this->lfsService->batchWithResolver(
                $repository,
                $operation,
                $validated['objects'],
                fn (string $action, string $oid) => match ($action) {
                    'upload' => route('git.lfs.objects.upload', ['org' => $org, 'repo' => $repo, 'oid' => $oid]),
                    'download' => route('git.lfs.objects.download', ['org' => $org, 'repo' => $repo, 'oid' => $oid]),
                },
            ),
            200,
            ['Content-Type' => 'application/vnd.git-lfs+json'],
        );
    }

    public function upload(Request $request, Repository $repository, string $oid): Response|JsonResponse
    {
        if (! $this->lfsService->isEnabledFor($repository)) {
            return response()->json([
                'message' => 'Git LFS is disabled for this repository.',
            ], 409);
        }

        $this->authorize('push', $repository);

        $body = $this->decodedRequestBody($request);

        $this->lfsService->store(
            $repository,
            $oid,
            strlen($body),
            $body,
        );

        return response('', 200, ['Content-Length' => '0']);
    }

    public function transportUpload(Request $request, string $org, string $repo, string $oid): Response|JsonResponse
    {
        $repository = $this->resolveTransportRepository($org, $repo);

        $this->ensureNativeGitTransportIsActive();

        if (! $this->lfsService->isEnabledFor($repository)) {
            return response()->json([
                'message' => 'Git LFS is disabled for this repository.',
            ], 409, [
                'Content-Type' => 'application/vnd.git-lfs+json',
            ]);
        }

        if ($repository->is_archived) {
            abort(403, 'Repository is archived and read-only.');
        }

        if ($challenge = $this->checkTransportWriteAccess($repository)) {
            return $challenge;
        }

        $body = $this->decodedRequestBody($request);

        $this->lfsService->store(
            $repository,
            $oid,
            strlen($body),
            $body,
        );

        return response('', 200, ['Content-Length' => '0']);
    }

    public function download(Repository $repository, string $oid): StreamedResponse|JsonResponse
    {
        if (! $this->lfsService->isEnabledFor($repository)) {
            return response()->json([
                'message' => 'Git LFS is disabled for this repository.',
            ], 409);
        }

        $this->authorize('view', $repository);

        $download = $this->lfsService->download($repository, $oid);

        if ($download === null) {
            return response()->json([
                'message' => 'LFS object not found.',
            ], 404);
        }

        return response()->stream(
            function () use ($download): void {
                fpassthru($download['stream']);

                if (is_resource($download['stream'])) {
                    fclose($download['stream']);
                }
            },
            200,
            [
                'Content-Type' => $download['object']->mime_type ?: 'application/octet-stream',
                'Content-Length' => (string) $download['object']->size,
            ],
        );
    }

    public function transportDownload(string $org, string $repo, string $oid): StreamedResponse|JsonResponse|Response
    {
        $repository = $this->resolveTransportRepository($org, $repo);

        $this->ensureNativeGitTransportIsActive();

        if (! $this->lfsService->isEnabledFor($repository)) {
            return response()->json([
                'message' => 'Git LFS is disabled for this repository.',
            ], 409, [
                'Content-Type' => 'application/vnd.git-lfs+json',
            ]);
        }

        if ($challenge = $this->checkTransportReadAccess($repository)) {
            return $challenge;
        }

        $download = $this->lfsService->download($repository, $oid);

        if ($download === null) {
            return response()->json([
                'message' => 'LFS object not found.',
            ], 404, [
                'Content-Type' => 'application/vnd.git-lfs+json',
            ]);
        }

        return response()->stream(
            function () use ($download): void {
                fpassthru($download['stream']);

                if (is_resource($download['stream'])) {
                    fclose($download['stream']);
                }
            },
            200,
            [
                'Content-Type' => $download['object']->mime_type ?: 'application/octet-stream',
                'Content-Length' => (string) $download['object']->size,
            ],
        );
    }
}
