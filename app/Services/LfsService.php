<?php

namespace App\Services;

use App\Contracts\LfsBackendInterface;
use App\Models\LfsObject;
use App\Models\Repository;
use App\Settings\LfsSettings;
use App\Support\GameEngineTemplates;
use App\Support\MimeDetector;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LfsService
{
    public function __construct(
        protected LfsBackendInterface $backend,
        protected LfsSettings $settings,
        protected ?StorageQuotaService $quotaService = null,
        protected ?NativeGitRepositoryService $nativeGit = null,
    ) {}

    public function isEnabledFor(Repository $repository): bool
    {
        return $this->settings->enabled && $repository->lfs_enabled;
    }

    public function batch(Repository $repository, string $operation, array $objects): array
    {
        return $this->batchWithResolver(
            $repository,
            $operation,
            $objects,
            fn (string $action, string $oid) => match ($action) {
                'upload' => route('api.lfs.objects.upload', ['organization' => $repository->organization, 'repository' => $repository, 'oid' => $oid]),
                'download' => route('api.lfs.objects.download', ['organization' => $repository->organization, 'repository' => $repository, 'oid' => $oid]),
            },
        );
    }

    public function batchWithResolver(
        Repository $repository,
        string $operation,
        array $objects,
        callable $urlResolver,
    ): array {
        return [
            'transfer' => 'basic',
            'objects' => collect($objects)
                ->map(fn (array $object) => $this->batchObject($repository, $operation, $object, $urlResolver))
                ->values()
                ->all(),
        ];
    }

    public function store(
        Repository $repository,
        string $oid,
        int $size,
        mixed $stream,
        ?string $mimeType = null,
    ): LfsObject {
        $this->guardOid($oid);
        $this->guardSize($size);

        $existingObject = $repository->lfsObjects()->where('oid', $oid)->first();

        if ($existingObject !== null && $existingObject->size !== $size && $size > 0) {
            throw ValidationException::withMessages([
                'size' => ['The uploaded object size does not match the reserved size.'],
            ]);
        }

        $this->backend->store($oid, $size, $stream);

        return $repository->lfsObjects()->updateOrCreate(
            ['oid' => $oid],
            [
                'size' => max($size, $this->backend->size($oid)),
                'mime_type' => $mimeType,
                'storage_path' => $this->storagePathFor($oid),
            ],
        );
    }

    public function download(Repository $repository, string $oid): ?array
    {
        $object = $repository->lfsObjects()->where('oid', $oid)->first();

        if ($object === null || ! $this->backend->exists($oid)) {
            return null;
        }

        return [
            'object' => $object,
            'stream' => $this->backend->readStream($oid),
        ];
    }

    protected function batchObject(
        Repository $repository,
        string $operation,
        array $object,
        callable $urlResolver,
    ): array {
        $oid = (string) ($object['oid'] ?? '');
        $size = (int) ($object['size'] ?? 0);

        if (! $this->isValidOid($oid)) {
            return $this->errorObject($oid, $size, 422, 'The object oid must be a 64 character sha256 hash.');
        }

        if ($size < 0) {
            return $this->errorObject($oid, $size, 422, 'The object size must be zero or greater.');
        }

        if ($size > $this->maxObjectBytes()) {
            return $this->errorObject($oid, $size, 413, 'The object exceeds the configured maximum size.');
        }

        // Quota check for uploads
        if ($operation === 'upload' && $size > 0 && $this->quotaService) {
            if (! $this->quotaService->canUploadToRepository($repository, $size)) {
                return $this->errorObject($oid, $size, 507, 'Organization storage quota exceeded.');
            }
        }

        if ($operation === 'upload') {
            if ($repository->lfsObjects()->where('oid', $oid)->exists() && $this->backend->exists($oid)) {
                return [
                    'oid' => $oid,
                    'size' => $size,
                ];
            }

            $repository->lfsObjects()->updateOrCreate(
                ['oid' => $oid],
                [
                    'size' => $size,
                    'storage_path' => $this->storagePathFor($oid),
                ],
            );

            return [
                'oid' => $oid,
                'size' => $size,
                'actions' => [
                    'upload' => [
                        'href' => $urlResolver('upload', $oid),
                    ],
                ],
            ];
        }

        if (! $repository->lfsObjects()->where('oid', $oid)->exists() || ! $this->backend->exists($oid)) {
            return $this->errorObject($oid, $size, 404, 'The requested object was not found.');
        }

        return [
            'oid' => $oid,
            'size' => $size,
            'actions' => [
                'download' => [
                    'href' => $urlResolver('download', $oid),
                ],
            ],
        ];
    }

    protected function errorObject(string $oid, int $size, int $code, string $message): array
    {
        return [
            'oid' => $oid,
            'size' => $size,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }

    /**
     * Generate .gitattributes content from a repository's LFS policies.
     */
    public function generateGitattributes(Repository $repository): string
    {
        $policies = $repository->lfsPolicies()->orderBy('pattern')->get();

        $lines = ['# Generated by Crucible SCM', '# https://git-lfs.com/', ''];

        foreach ($policies as $policy) {
            $comment = $policy->description ? " # {$policy->description}" : '';
            $lines[] = "{$policy->pattern} filter=lfs diff=lfs merge=lfs -text{$comment}";
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Storage statistics for a repository's LFS objects.
     */
    public function storageStats(Repository $repository): array
    {
        $totals = $repository->lfsObjects()
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(size), 0) as total_size')
            ->first();

        $oidToPath = $this->lfsOidPathMap($repository);

        $buckets = [];

        $repository->lfsObjects()
            ->select(['oid', 'mime_type', 'size'])
            ->get()
            ->each(function (LfsObject $obj) use (&$buckets, $oidToPath) {
                $mime = $this->effectiveMimeType($obj, $oidToPath) ?? 'application/octet-stream';

                if (! isset($buckets[$mime])) {
                    $buckets[$mime] = ['mime_type' => $mime, 'count' => 0, 'total_size' => 0];
                }

                $buckets[$mime]['count']++;
                $buckets[$mime]['total_size'] += (int) $obj->size;
            });

        $byMime = array_values($buckets);
        usort($byMime, fn (array $a, array $b) => $b['total_size'] <=> $a['total_size']);

        return [
            'total_objects' => (int) $totals->count,
            'total_size'    => (int) $totals->total_size,
            'by_mime_type'  => $byMime,
        ];
    }

    /**
     * Largest LFS objects for a repository.
     */
    public function largestObjects(Repository $repository, int $limit = 20): array
    {
        $oidToPath = $this->lfsOidPathMap($repository);

        return $repository->lfsObjects()
            ->orderByDesc('size')
            ->limit($limit)
            ->get()
            ->map(fn (LfsObject $obj) => [
                'oid'          => $obj->oid,
                'size'         => $obj->size,
                'mime_type'    => $this->effectiveMimeType($obj, $oidToPath),
                'storage_path' => $obj->storage_path,
                'created_at'   => $obj->created_at?->toAtomString(),
            ])
            ->all();
    }

    /**
     * Resolve the most useful mime type for an LFS object.
     *
     * Prefers a type derived from the tracked file path's extension, because
     * the stored `mime_type` column is frequently `application/octet-stream`
     * (git-lfs clients always send that Content-Type on upload, which the
     * batch API historically persisted).  Falls back to the stored value when
     * no tracked path is known or the path yields only the generic binary
     * type itself.
     *
     * @param  array<string, string>  $oidToPath
     */
    protected function effectiveMimeType(LfsObject $object, array $oidToPath): ?string
    {
        $generic = 'application/octet-stream';

        $path = $oidToPath[$object->oid] ?? null;

        $derived = $path !== null ? MimeDetector::mimeFromExtension($path) : null;

        if ($derived !== null && $derived !== $generic) {
            return $derived;
        }

        if (filled($object->mime_type) && $object->mime_type !== $generic) {
            return $object->mime_type;
        }

        // Last resort: whichever non-null value we have, preferring the
        // derived one so the caller at least knows a path was available.
        return $derived ?? $object->mime_type;
    }

    /**
     * Load the OID → tracked path map for this repository.
     *
     * Resolves NativeGitRepositoryService from the container when it was not
     * injected — Laravel's auto-resolution can skip nullable-with-default
     * constructor parameters, leaving $this->nativeGit as null even though a
     * binding exists.
     *
     * @return array<string, string>
     */
    protected function lfsOidPathMap(Repository $repository): array
    {
        return $this->nativeGit ??= app(NativeGitRepositoryService::class)->lfsOidPathMap($repository);
    }

    protected function guardOid(string $oid): void
    {
        if (! $this->isValidOid($oid)) {
            throw ValidationException::withMessages([
                'oid' => ['The object oid must be a 64 character sha256 hash.'],
            ]);
        }
    }

    protected function guardSize(int $size): void
    {
        if ($size < 0) {
            throw ValidationException::withMessages([
                'size' => ['The object size must be zero or greater.'],
            ]);
        }

        if ($size > $this->maxObjectBytes()) {
            throw ValidationException::withMessages([
                'size' => ['The object exceeds the configured maximum size.'],
            ]);
        }
    }

    protected function isValidOid(string $oid): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/i', $oid) === 1;
    }

    protected function storagePathFor(string $oid): string
    {
        return sprintf(
            'crucible-lfs/%s/%s/%s',
            substr($oid, 0, 2),
            substr($oid, 2, 2),
            $oid,
        );
    }

    protected function maxObjectBytes(): int
    {
        return $this->settings->maxObjectSizeMb * 1024 * 1024;
    }
}
