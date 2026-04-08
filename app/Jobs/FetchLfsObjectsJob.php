<?php

namespace App\Jobs;

use App\Models\Repository;
use App\Services\LfsService;
use App\Services\NativeGitRepositoryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FetchLfsObjectsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 2;

    public function __construct(
        public readonly Repository $repository,
    ) {}

    public function handle(
        NativeGitRepositoryService $nativeGit,
        LfsService $lfsService,
    ): void {
        $repository = $this->repository->fresh(['organization']) ?? $this->repository->loadMissing('organization');

        if (! $nativeGit->isLfsInstalled()) {
            Log::warning('[FetchLfsObjectsJob] git-lfs not installed, skipping', [
                'repo' => $repository->id,
            ]);

            return;
        }

        $remoteUrl = $repository->remote_url;

        if (! $remoteUrl) {
            return;
        }

        // Pull LFS objects from the remote into the bare repo's cache.
        try {
            $nativeGit->fetchLfsObjects($repository, $remoteUrl);
        } catch (RuntimeException $e) {
            Log::error('[FetchLfsObjectsJob] git lfs fetch failed', [
                'repo' => $repository->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        // Discover cached LFS objects and import any that Crucible doesn't have yet.
        $cachedObjects = $nativeGit->listCachedLfsObjects($repository);

        if ($cachedObjects === []) {
            return;
        }

        $existingOids = $repository->lfsObjects()->pluck('oid')->flip();
        $importedCount = 0;

        foreach ($cachedObjects as $obj) {
            if ($existingOids->has($obj['oid'])) {
                continue;
            }

            $filePath = $nativeGit->lfsObjectCachePath($repository, $obj['oid']);

            if (! is_file($filePath)) {
                continue;
            }

            $stream = fopen($filePath, 'rb');

            try {
                $lfsService->store($repository, $obj['oid'], $obj['size'], $stream);
                $importedCount++;
            } catch (\Throwable $e) {
                Log::warning('[FetchLfsObjectsJob] failed to import LFS object', [
                    'repo' => $repository->id,
                    'oid' => $obj['oid'],
                    'error' => $e->getMessage(),
                ]);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        // Update repository LFS metadata.
        $totalLfsSize = $repository->lfsObjects()->sum('size');
        $updates = ['lfs_size_kb' => (int) ceil($totalLfsSize / 1024)];

        if ($totalLfsSize > 0 && ! $repository->lfs_enabled) {
            $updates['lfs_enabled'] = true;
        }

        $repository->forceFill($updates);
        $repository->saveWithoutTouch();

        Log::info('[FetchLfsObjectsJob] completed', [
            'repo' => $repository->id,
            'imported' => $importedCount,
            'total_lfs_objects' => count($cachedObjects),
            'lfs_size_kb' => $updates['lfs_size_kb'],
        ]);
    }
}
