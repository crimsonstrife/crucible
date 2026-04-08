<?php

namespace App\Jobs;

use App\Contracts\RepositoryDriverInterface;
use App\Drivers\NativeGitDriver;
use App\Models\Repository;
use App\Services\NativeGitRepositoryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SyncRepositoryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Repository $repository,
    ) {}

    public function handle(
        RepositoryDriverInterface $driver,
        NativeGitRepositoryService $nativeGit,
    ): void {
        $repository = $this->repository->fresh() ?? $this->repository;

        if (! ($driver instanceof NativeGitDriver)) {
            Log::warning('[SyncRepositoryJob] skipped — requires native git backend', [
                'repo' => $repository->id,
            ]);

            return;
        }

        $remoteUrl = $repository->remote_url;

        if (! $remoteUrl) {
            Log::warning('[SyncRepositoryJob] skipped — no remote_url set', [
                'repo' => $repository->id,
            ]);

            return;
        }

        if (! $driver->exists($repository)) {
            // Repository not on disk yet — clone it instead.
            Log::info('[SyncRepositoryJob] repo not on disk, cloning', ['repo' => $repository->id]);
            $driver->clone($remoteUrl, $repository);
        } else {
            $nativeGit->fetchRemote($repository, $remoteUrl);
        }

        if ($driver->exists($repository)) {
            $repository->forceFill([
                'default_branch' => $driver->defaultBranch($repository),
                'size_kb'        => (int) ceil($driver->size($repository) / 1024),
                'last_synced_at' => now(),
            ]);

            $repository->saveWithoutTouch();

            $repository->forceFill(['lfs_sync_status' => 'pending']);
            $repository->saveWithoutTouch();

            Log::info('[SyncRepositoryJob] dispatching FetchLfsObjectsJob', ['repo' => $repository->id]);
            FetchLfsObjectsJob::dispatch($repository, $remoteUrl);
        }

        Log::info('[SyncRepositoryJob] completed', ['repo' => $repository->id]);
    }
}
