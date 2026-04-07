<?php

namespace App\Contracts;

use App\Models\Repository;

interface RepositoryDriverInterface
{
    public function initialize(Repository $repo): void;

    public function clone(string $remote, Repository $repo): void;

    public function branches(Repository $repo): array;

    public function defaultBranch(Repository $repo): string;

    public function archive(Repository $repo): void;

    public function delete(Repository $repo): void;

    public function exists(Repository $repo): bool;

    public function size(Repository $repo): int;
}
