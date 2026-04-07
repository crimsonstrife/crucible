<?php

namespace App\Jobs;

use App\Contracts\RepositoryDriverInterface;
use App\Models\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ArchiveRepositoryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Repository $repository
    ) {}

    public function handle(RepositoryDriverInterface $driver): void
    {
        $driver->archive($this->repository);
        Log::info('[ArchiveRepositoryJob] completed', ['repo' => $this->repository->id]);
    }
}
