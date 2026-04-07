<?php

namespace App\Services;

use App\Contracts\LfsBackendInterface;
use App\Models\LfsObject;
use App\Models\Repository;
use App\Settings\LfsSettings;
use App\Support\GameEngineTemplates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LfsService
{
    public function __construct(
        protected LfsBackendInterface $backend,
        protected LfsSettings $settings,
        protected ?StorageQuotaService $quotaService = null,
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
        $query = $repository->lfsObjects();

        $totals = $query->selectRaw('COUNT(*) as count, COALESCE(SUM(size), 0) as total_size')->first();

        $byMime = $repository->lfsObjects()
            ->selectRaw("COALESCE(mime_type, 'application/octet-stream') as grouped_mime, COUNT(*) as count, COALESCE(SUM(size), 0) as total_size")
            ->groupBy('grouped_mime')
            ->orderByDesc('total_size')
            ->get()
            ->map(fn ($row) => [
                'mime_type'  => $row->grouped_mime,
                'count'      => (int) $row->count,
                'total_size' => (int) $row->total_size,
            ])
            ->all();

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
        return $repository->lfsObjects()
            ->orderByDesc('size')
            ->limit($limit)
            ->get()
            ->map(fn (LfsObject $obj) => [
                'oid'          => $obj->oid,
                'size'         => $obj->size,
                'mime_type'    => $obj->mime_type,
                'storage_path' => $obj->storage_path,
                'created_at'   => $obj->created_at?->toAtomString(),
            ])
            ->all();
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
