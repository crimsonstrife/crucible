<?php

namespace App\Services;

use App\Contracts\RepositoryDriverInterface;
use App\Enums\CollaboratorRole;
use App\Enums\RepositoryVisibility;
use App\Jobs\ArchiveRepositoryJob;
use App\Jobs\DeleteRepositoryJob;
use App\Jobs\InitializeRepositoryJob;
use App\Jobs\SyncRepositoryJob;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Str;

class RepositoryService
{
    public function __construct(
        protected RepositoryDriverInterface $driver
    ) {}

    public function create(Organization $org, User $owner, array $data): Repository
    {
        $repo = $this->makeRepository($org, $owner, $data);

        InitializeRepositoryJob::dispatch($repo);

        return $repo;
    }

    public function importRemote(Organization $org, User $owner, string $remoteUrl, array $data): Repository
    {
        $repo = $this->makeRepository($org, $owner, array_merge($data, [
            'remote_url' => $remoteUrl,
            'auto_sync'  => (bool) ($data['auto_sync'] ?? false),
        ]));

        InitializeRepositoryJob::dispatch($repo, $remoteUrl);

        return $repo;
    }

    public function syncFromRemote(Repository $repo, User $actor): void
    {
        abort_unless((bool) $repo->remote_url, 422, 'This repository has no upstream remote configured.');

        SyncRepositoryJob::dispatch($repo);
    }

    public function archive(Repository $repo, User $actor): void
    {
        $repo->update(['is_archived' => true]);
        ArchiveRepositoryJob::dispatch($repo);
    }

    public function unarchive(Repository $repo, User $actor): void
    {
        $repo->update(['is_archived' => false]);
    }

    public function delete(Repository $repo, User $actor): void
    {
        DeleteRepositoryJob::dispatch($repo);
        $repo->delete();
    }

    public function updateVisibility(Repository $repo, RepositoryVisibility $visibility, User $actor): void
    {
        $repo->update(['visibility' => $visibility]);
    }

    public function addCollaborator(Repository $repo, User $target, CollaboratorRole $role, User $actor): void
    {
        $repo->collaborators()->syncWithoutDetaching([
            $target->id => ['role' => $role->value],
        ]);
    }

    public function removeCollaborator(Repository $repo, User $target, User $actor): void
    {
        $repo->collaborators()->detach($target->id);
    }

    protected function makeRepository(Organization $organization, User $owner, array $data): Repository
    {
        return $organization->repositories()->create([
            'owner_id' => $owner->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'vcs_type' => $data['vcs_type'] ?? 'git',
            'visibility' => $data['visibility'] ?? 'private',
            'default_branch' => $data['default_branch'] ?? 'main',
            'lfs_enabled' => $data['lfs_enabled'] ?? false,
            'forge_project_id' => $data['forge_project_id'] ?? null,
        ]);
    }
}
