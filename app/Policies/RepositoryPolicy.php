<?php

namespace App\Policies;

use App\Enums\CollaboratorRole;
use App\Models\Repository;
use App\Models\User;
use App\Services\RecordAccessService;
use Illuminate\Auth\Access\HandlesAuthorization;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class RepositoryPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly RecordAccessService $recordAccessService
    ) {}

    public function before(User $user, string $ability): ?bool
    {
        try {
            return $user->hasPermissionTo('is-super-admin', 'web') ? true : null;
        } catch (PermissionDoesNotExist) {
            return null;
        }
    }

    public function view(User $user, Repository $repo): bool
    {
        if ($repo->visibility->value === 'public') {
            return true;
        }

        if ($repo->visibility->value === 'internal') {
            return $repo->organization->members()->where('users.id', $user->id)->exists();
        }

        // Private: must be collaborator or owner
        return $repo->owner_id === $user->id
            || $repo->collaborators()->where('users.id', $user->id)->exists()
            || $this->allowsSharedAccess($user, $repo, 'view');
    }

    public function create(User $user): bool
    {
        return true; // Any authenticated org member can create repos
    }

    public function update(User $user, Repository $repo): bool
    {
        return $user->id === $repo->owner_id
            || $this->hasMinRole($user, $repo, CollaboratorRole::Maintain)
            || $this->allowsSharedAccess($user, $repo, 'update');
    }

    public function delete(User $user, Repository $repo): bool
    {
        return $user->id === $repo->owner_id
            || $this->hasMinRole($user, $repo, CollaboratorRole::Admin)
            || $this->allowsSharedAccess($user, $repo, 'delete');
    }

    public function archive(User $user, Repository $repo): bool
    {
        return $this->delete($user, $repo);
    }

    public function manageCollaborators(User $user, Repository $repo): bool
    {
        return $user->id === $repo->owner_id
            || $this->hasMinRole($user, $repo, CollaboratorRole::Admin)
            || $this->allowsSharedAccess($user, $repo, 'manage');
    }

    public function manageLocks(User $user, Repository $repo): bool
    {
        return $user->id === $repo->owner_id
            || $this->hasMinRole($user, $repo, CollaboratorRole::Maintain)
            || $this->allowsSharedAccess($user, $repo, 'manage');
    }

    public function manageLfs(User $user, Repository $repo): bool
    {
        return $this->manageLocks($user, $repo);
    }

    public function viewReleases(?User $user, Repository $repo): bool
    {
        if ($repo->visibility->value === 'public') {
            return true;
        }

        return $user !== null && $this->view($user, $repo);
    }

    public function manageReleases(User $user, Repository $repo): bool
    {
        if ($repo->is_archived) {
            return false;
        }

        return $user->id === $repo->owner_id
            || $this->hasMinRole($user, $repo, CollaboratorRole::Maintain)
            || $this->allowsSharedAccess($user, $repo, 'update');
    }

    public function push(User $user, Repository $repo): bool
    {
        if ($repo->is_archived) {
            return false;
        }

        return $user->id === $repo->owner_id
            || $this->hasMinRole($user, $repo, CollaboratorRole::Write)
            || $this->allowsSharedAccess($user, $repo, 'update');
    }

    protected function hasMinRole(User $user, Repository $repo, CollaboratorRole $minRole): bool
    {
        $role = $repo->collaboratorRoleFor($user);

        return $role !== null && $role->rank() >= $minRole->rank();
    }

    protected function allowsSharedAccess(User $user, Repository $repository, string $ability): bool
    {
        return $this->recordAccessService
            ->levelFor($user, $repository)
            ?->allows($ability) ?? false;
    }
}
