<?php

namespace App\Drivers;

use App\Contracts\RepositoryDriverInterface;
use App\Models\Repository;
use Illuminate\Support\Facades\Log;

class StubRepositoryDriver implements RepositoryDriverInterface
{
    public function initialize(Repository $repo): void
    {
        Log::info('[StubRepositoryDriver] initialize', ['repo' => $repo->id]);
    }

    public function clone(string $remote, Repository $repo): void
    {
        Log::info('[StubRepositoryDriver] clone', ['remote' => $remote, 'repo' => $repo->id]);
    }

    public function branches(Repository $repo): array
    {
        Log::info('[StubRepositoryDriver] branches', ['repo' => $repo->id]);

        return [$repo->default_branch ?? 'main'];
    }

    public function defaultBranch(Repository $repo): string
    {
        Log::info('[StubRepositoryDriver] defaultBranch', ['repo' => $repo->id]);

        return $repo->default_branch ?? 'main';
    }

    public function archive(Repository $repo): void
    {
        Log::info('[StubRepositoryDriver] archive', ['repo' => $repo->id]);
    }

    public function delete(Repository $repo): void
    {
        Log::info('[StubRepositoryDriver] delete', ['repo' => $repo->id]);
    }

    public function exists(Repository $repo): bool
    {
        Log::info('[StubRepositoryDriver] exists', ['repo' => $repo->id]);

        return false;
    }

    public function size(Repository $repo): int
    {
        Log::info('[StubRepositoryDriver] size', ['repo' => $repo->id]);

        return 0;
    }
}
