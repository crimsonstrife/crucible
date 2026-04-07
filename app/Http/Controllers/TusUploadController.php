<?php

namespace App\Http\Controllers;

use App\Models\LfsUploadSession;
use App\Models\Repository;
use App\Services\LfsService;
use App\Services\StorageQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * TUS (tus.io) resumable upload protocol handler for LFS objects.
 *
 * Implements TUS v1.0.0 core protocol:
 *   - OPTIONS:  Discover server capabilities
 *   - POST:     Create a new upload
 *   - HEAD:     Get upload offset (resume point)
 *   - PATCH:    Append data to upload
 *   - DELETE:   Terminate an upload
 *
 * Used for LFS objects above the configured chunked threshold.
 * The upload URL is returned by LfsBatchController when the object exceeds the threshold.
 */
class TusUploadController extends Controller
{
    private const TUS_VERSION = '1.0.0';

    private const TUS_MAX_SIZE = 10 * 1024 * 1024 * 1024; // 10 GB

    public function __construct(
        protected LfsService $lfsService,
        protected StorageQuotaService $quotaService,
    ) {}

    /**
     * OPTIONS — Server capability discovery.
     */
    public function options(): Response
    {
        return response('', 204, $this->tusHeaders([
            'Tus-Extension'  => 'creation,termination',
            'Tus-Max-Size'   => self::TUS_MAX_SIZE,
        ]));
    }

    /**
     * POST — Create a new upload session.
     *
     * Headers expected:
     *   Upload-Length: <total size in bytes>
     *   Upload-Metadata: oid <base64>, repository_id <base64>
     */
    public function create(Request $request, Repository $repository): Response|JsonResponse
    {
        $uploadLength = (int) $request->header('Upload-Length', 0);

        if ($uploadLength <= 0) {
            return response()->json(['error' => 'Upload-Length header is required.'], 400);
        }

        if ($uploadLength > self::TUS_MAX_SIZE) {
            return response()->json(['error' => 'Upload exceeds maximum allowed size.'], 413);
        }

        // Parse Upload-Metadata
        $metadata = $this->parseMetadata($request->header('Upload-Metadata', ''));
        $oid = $metadata['oid'] ?? null;

        if (! $oid || ! preg_match('/\A[a-f0-9]{64}\z/i', $oid)) {
            return response()->json(['error' => 'Valid OID required in Upload-Metadata.'], 400);
        }

        // Quota check
        $repository->loadMissing('organization');
        if (! $this->quotaService->canUpload($repository->organization, $uploadLength)) {
            return response()->json(['error' => 'Organization storage quota exceeded.'], 507);
        }

        // Create or resume session
        $session = LfsUploadSession::updateOrCreate(
            [
                'repository_id' => $repository->id,
                'oid'           => $oid,
            ],
            [
                'total_size'     => $uploadLength,
                'uploaded_bytes' => 0,
                'expires_at'     => now()->addHours(24),
            ],
        );

        $uploadUrl = route('api.tus.upload', [
            'organization' => $repository->organization,
            'repository'   => $repository,
            'session'      => $session->id,
        ]);

        return response('', 201, $this->tusHeaders([
            'Location'      => $uploadUrl,
            'Upload-Offset' => 0,
        ]));
    }

    /**
     * HEAD — Get current upload offset for resumption.
     */
    public function head(Repository $repository, LfsUploadSession $session): Response|JsonResponse
    {
        abort_unless($session->repository_id === $repository->id, 404);

        if ($session->isExpired()) {
            $session->delete();

            return response()->json(['error' => 'Upload session has expired.'], 410);
        }

        return response('', 200, $this->tusHeaders([
            'Upload-Offset' => $session->uploaded_bytes,
            'Upload-Length' => $session->total_size,
            'Cache-Control' => 'no-store',
        ]));
    }

    /**
     * PATCH — Append data to the upload.
     *
     * Headers expected:
     *   Upload-Offset: <current offset>
     *   Content-Type: application/offset+octet-stream
     */
    public function patch(Request $request, Repository $repository, LfsUploadSession $session): Response|JsonResponse
    {
        abort_unless($session->repository_id === $repository->id, 404);

        if ($session->isExpired()) {
            $session->delete();

            return response()->json(['error' => 'Upload session has expired.'], 410);
        }

        $clientOffset = (int) $request->header('Upload-Offset', -1);

        if ($clientOffset !== $session->uploaded_bytes) {
            return response()->json([
                'error'           => 'Offset mismatch.',
                'expected_offset' => $session->uploaded_bytes,
            ], 409);
        }

        $chunk = $request->getContent();
        $chunkSize = strlen($chunk);

        if ($chunkSize === 0) {
            return response()->json(['error' => 'Empty body.'], 400);
        }

        // Prevent uploading beyond declared length
        if ($session->uploaded_bytes + $chunkSize > $session->total_size) {
            return response()->json(['error' => 'Upload exceeds declared Upload-Length.'], 413);
        }

        // Write chunk to temp storage
        $tempPath = $this->tempPathFor($session);
        $disk = Storage::disk('local');

        if ($clientOffset === 0) {
            $disk->put($tempPath, $chunk);
        } else {
            $fullPath = $disk->path($tempPath);
            file_put_contents($fullPath, $chunk, FILE_APPEND);
        }

        $session->update([
            'uploaded_bytes' => $session->uploaded_bytes + $chunkSize,
        ]);

        $newOffset = $session->uploaded_bytes;

        // Check if upload is complete
        if ($session->isComplete()) {
            $stream = $disk->readStream($tempPath);

            $this->lfsService->store(
                $session->repository,
                $session->oid,
                $session->total_size,
                $stream,
            );

            $disk->delete($tempPath);
            $session->delete();
        }

        return response('', 204, $this->tusHeaders([
            'Upload-Offset' => $newOffset,
        ]));
    }

    /**
     * DELETE — Terminate an upload and clean up.
     */
    public function destroy(Repository $repository, LfsUploadSession $session): Response
    {
        abort_unless($session->repository_id === $repository->id, 404);

        $tempPath = $this->tempPathFor($session);
        Storage::disk('local')->delete($tempPath);
        $session->delete();

        return response('', 204, $this->tusHeaders());
    }

    /**
     * Parse TUS Upload-Metadata header.
     *
     * Format: key base64value, key base64value, ...
     */
    private function parseMetadata(string $header): array
    {
        $metadata = [];

        if (blank($header)) {
            return $metadata;
        }

        foreach (explode(',', $header) as $pair) {
            $parts = preg_split('/\s+/', trim($pair), 2);

            if (count($parts) === 2) {
                $metadata[$parts[0]] = base64_decode($parts[1]);
            } elseif (count($parts) === 1) {
                $metadata[$parts[0]] = '';
            }
        }

        return $metadata;
    }

    private function tusHeaders(array $extra = []): array
    {
        return array_merge([
            'Tus-Resumable' => self::TUS_VERSION,
        ], $extra);
    }

    private function tempPathFor(LfsUploadSession $session): string
    {
        return sprintf('crucible-lfs-uploads/%s/%s', $session->repository_id, $session->oid);
    }
}
