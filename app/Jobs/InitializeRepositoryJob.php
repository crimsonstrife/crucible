<?php

namespace App\Jobs;

use App\Contracts\RepositoryDriverInterface;
use App\Models\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class InitializeRepositoryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Repository $repository,
        public readonly ?string $cloneFrom = null
    ) {}

    public function handle(RepositoryDriverInterface $driver): void
    {
        $repository = $this->repository->fresh(['organization']) ?? $this->repository->loadMissing('organization');

        if ($this->cloneFrom) {
            $driver->clone($this->cloneFrom, $repository);
        } else {
            $driver->initialize($repository);
        }

        if ($driver->exists($repository)) {
            $repository->forceFill([
                'default_branch' => $driver->defaultBranch($repository),
                'size_kb' => (int) ceil($driver->size($repository) / 1024),
            ]);

            $repository->saveWithoutTouch();
        }

        Log::info('[InitializeRepositoryJob] completed', ['repo' => $repository->id]);
    }
}
