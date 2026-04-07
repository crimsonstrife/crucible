<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Services\RecordAccessService;
use Illuminate\Auth\Access\HandlesAuthorization;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class OrganizationPolicy
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

    public function view(User $user, Organization $org): bool
    {
        return $org->members()->where('users.id', $user->id)->exists()
            || $this->allowsSharedAccess($user, $org, 'view');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Organization $org): bool
    {
        return $this->hasMinRole($user, $org, 'admin')
            || $this->allowsSharedAccess($user, $org, 'manage');
    }

    public function delete(User $user, Organization $org): bool
    {
        return $this->hasMinRole($user, $org, 'owner')
            || $this->allowsSharedAccess($user, $org, 'delete');
    }

    public function manageMember(User $user, Organization $org): bool
    {
        return $this->hasMinRole($user, $org, 'admin')
            || $this->allowsSharedAccess($user, $org, 'manage');
    }

    protected function hasMinRole(User $user, Organization $org, string $minRole): bool
    {
        $roles = ['member' => 0, 'admin' => 1, 'owner' => 2];
        $minVal = $roles[$minRole] ?? 0;

        $pivot = $org->members()->where('users.id', $user->id)->first()?->pivot;
        if (! $pivot) {
            return false;
        }

        $userVal = $roles[$pivot->role] ?? -1;

        return $userVal >= $minVal;
    }

    protected function allowsSharedAccess(User $user, Organization $organization, string $ability): bool
    {
        return $this->recordAccessService
            ->levelFor($user, $organization)
            ?->allows($ability) ?? false;
    }
}
