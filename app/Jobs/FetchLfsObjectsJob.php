<?php

namespace App\Jobs;

use App\Models\Repository;
use App\Services\LfsService;
use App\Services\NativeGitRepositoryService;
use App\Support\MimeDetector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FetchLfsObjectsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Large repos (e.g. game assets) can have tens of thousands of LFS objects.
     * Allow up to 2 hours for the full fetch + import cycle.
     */
    public int $timeout = 7200;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly Repository $repository,
        public readonly ?string $remoteUrl = null,
    ) {}

    public function handle(
        NativeGitRepositoryService $nativeGit,
        LfsService $lfsService,
    ): void {
        $repository = $this->repository->fresh(['organization']) ?? $this->repository->loadMissing('organization');

        Log::info('[FetchLfsObjectsJob] started', ['repo' => $repository->id]);

        if (! $nativeGit->isLfsInstalled()) {
            Log::warning('[FetchLfsObjectsJob] git-lfs not installed, skipping', [
                'repo' => $repository->id,
            ]);
            $this->setStatus($repository, 'skipped');

            return;
        }

        $remoteUrl = $this->remoteUrl ?? $repository->remote_url;

        if (! $remoteUrl) {
            Log::warning('[FetchLfsObjectsJob] no remote_url set, skipping', [
                'repo' => $repository->id,
            ]);
            $this->setStatus($repository, 'skipped');

            return;
        }

        $this->setStatus($repository, 'fetching');

        // Pull LFS objects from the remote into the bare repo's cache.
        try {
            $nativeGit->fetchLfsObjects($repository, $remoteUrl);
        } catch (RuntimeException $e) {
            Log::error('[FetchLfsObjectsJob] git lfs fetch failed', [
                'repo' => $repository->id,
                'error' => $e->getMessage(),
            ]);
            $this->setStatus($repository, 'failed');

            return;
        }

        // Discover cached LFS objects and import any that Crucible doesn't have yet.
        $cachedObjects = $nativeGit->listCachedLfsObjects($repository);

        Log::info('[FetchLfsObjectsJob] found cached LFS objects', [
            'repo' => $repository->id,
            'count' => count($cachedObjects),
        ]);

        if ($cachedObjects === []) {
            Log::info('[FetchLfsObjectsJob] no LFS objects to import', [
                'repo' => $repository->id,
            ]);
            $this->setStatus($repository, 'synced');

            return;
        }

        $this->setStatus($repository, 'importing');

        $existingOids = $repository->lfsObjects()->pluck('oid')->flip();
        $importedCount = 0;

        // Map OID → tracked path so we can derive a mime type from the file
        // extension at ingest time.  Falls back to an empty map if git-lfs is
        // not installed; the LFS dashboard will resolve mime types at display
        // time in that case.
        $oidToPath = $nativeGit->lfsOidPathMap($repository);

        foreach ($cachedObjects as $obj) {
            if ($existingOids->has($obj['oid'])) {
                continue;
            }

            $filePath = $nativeGit->lfsObjectCachePath($repository, $obj['oid']);

            if (! is_file($filePath)) {
                Log::warning('[FetchLfsObjectsJob] cached LFS object file missing', [
                    'repo' => $repository->id,
                    'oid' => $obj['oid'],
                    'expected_path' => $filePath,
                ]);

                continue;
            }

            $stream = fopen($filePath, 'rb');

            $mimeType = isset($oidToPath[$obj['oid']])
                ? MimeDetector::mimeFromExtension($oidToPath[$obj['oid']])
                : null;

            try {
                $lfsService->store($repository, $obj['oid'], $obj['size'], $stream, $mimeType);
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
        $updates = [
            'lfs_size_kb' => (int) ceil($totalLfsSize / 1024),
            'lfs_sync_status' => 'synced',
        ];

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

    /**
     * Handle a job failure (uncaught exception / timeout).
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('[FetchLfsObjectsJob] job failed with exception', [
            'repo' => $this->repository->id,
            'error' => $exception?->getMessage(),
        ]);

        $this->setStatus($this->repository, 'failed');
    }

    protected function setStatus(Repository $repository, string $status): void
    {
        $repository->forceFill(['lfs_sync_status' => $status]);
        $repository->saveWithoutTouch();
    }
}
