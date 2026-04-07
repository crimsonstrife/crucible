<?php

namespace App\Drivers;

use App\Contracts\RepositoryDriverInterface;
use App\Models\Repository;
use App\Services\NativeGitRepositoryService;
use Illuminate\Support\Facades\Log;

class NativeGitDriver implements RepositoryDriverInterface
{
    public function __construct(
        protected NativeGitRepositoryService $nativeGit,
    ) {}

    public function initialize(Repository $repo): void
    {
        $this->nativeGit->initialize($repo);
    }

    public function clone(string $remote, Repository $repo): void
    {
        $this->nativeGit->clone($remote, $repo);
    }

    public function branches(Repository $repo): array
    {
        return $this->nativeGit->branches($repo);
    }

    public function defaultBranch(Repository $repo): string
    {
        return $this->nativeGit->defaultBranch($repo);
    }

    public function archive(Repository $repo): void
    {
        // Archive is a logical operation; the git data remains intact.
        Log::info('[NativeGitDriver] archive (logical only)', ['repo' => $repo->id]);
    }

    public function delete(Repository $repo): void
    {
        $this->nativeGit->delete($repo);
    }

    public function exists(Repository $repo): bool
    {
        return $this->nativeGit->exists($repo);
    }

    public function size(Repository $repo): int
    {
        return $this->nativeGit->size($repo);
    }
}
