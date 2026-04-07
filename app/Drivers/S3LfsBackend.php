<?php

namespace App\Drivers;

use App\Contracts\LfsBackendInterface;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * S3-compatible LFS storage backend.
 *
 * Uses Laravel's S3 filesystem disk. Configure with:
 *   CRUCIBLE_LFS_BACKEND=s3
 *   CRUCIBLE_LFS_S3_DISK=s3        (or any S3-compatible disk name)
 */
class S3LfsBackend implements LfsBackendInterface
{
    public function __construct(
        protected string $diskName = 's3',
    ) {}

    public function exists(string $oid): bool
    {
        return $this->disk()->exists($this->pathFor($oid));
    }

    public function store(string $oid, int $size, mixed $stream): void
    {
        $path = $this->pathFor($oid);

        if (! $this->disk()->put($path, $stream)) {
            throw new RuntimeException('Failed to store the LFS object to S3.');
        }

        if ($size > 0 && $this->size($oid) !== $size) {
            $this->delete($oid);

            throw new RuntimeException('Stored LFS object size does not match the expected size.');
        }
    }

    public function delete(string $oid): void
    {
        $this->disk()->delete($this->pathFor($oid));
    }

    public function size(string $oid): int
    {
        return $this->disk()->size($this->pathFor($oid));
    }

    public function readStream(string $oid): mixed
    {
        return $this->disk()->readStream($this->pathFor($oid));
    }

    protected function pathFor(string $oid): string
    {
        return sprintf(
            'crucible-lfs/%s/%s/%s',
            substr($oid, 0, 2),
            substr($oid, 2, 2),
            $oid,
        );
    }

    protected function disk()
    {
        return Storage::disk($this->diskName);
    }
}
