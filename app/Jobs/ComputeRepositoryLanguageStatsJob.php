<?php

namespace App\Jobs;

use App\Contracts\RepositoryDriverInterface;
use App\Drivers\NativeGitDriver;
use App\Models\Repository;
use App\Services\RepositoryLanguageStatsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ComputeRepositoryLanguageStatsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly Repository $repository,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->repository->id;
    }

    public function handle(
        RepositoryDriverInterface $driver,
        RepositoryLanguageStatsService $service,
    ): void {
        if (! ($driver instanceof NativeGitDriver)) {
            Log::info('[ComputeRepositoryLanguageStatsJob] skipped — requires native git backend', [
                'repo' => $this->repository->id,
            ]);

            return;
        }

        $service->recompute($this->repository);
    }
}
