<?php

namespace App\Drivers;

use App\Contracts\LfsBackendInterface;
use App\Settings\LfsSettings;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LocalLfsBackend implements LfsBackendInterface
{
    public function __construct(
        protected LfsSettings $settings,
    ) {}

    public function exists(string $oid): bool
    {
        return $this->disk()->exists($this->pathFor($oid));
    }

    public function store(string $oid, int $size, mixed $stream): void
    {
        if (! $this->disk()->put($this->pathFor($oid), $stream)) {
            throw new RuntimeException('Failed to store the LFS object.');
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
        return Storage::disk($this->settings->storageDisk ?: config('crucible.lfs.storage_disk', 'local'));
    }
}
