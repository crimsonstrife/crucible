<?php

namespace App\Services;

use App\Enums\CollaboratorRole;
use App\Models\Repository;
use App\Models\User;

class PermissionResolutionService
{
    public function effectiveRoleFor(User $user, Repository $repo): ?CollaboratorRole
    {
        return $repo->collaboratorRoleFor($user);
    }

    public function canWrite(User $user, Repository $repo): bool
    {
        if ($user->hasPermissionTo('is-super-admin', 'web')) {
            return true;
        }

        $role = $this->effectiveRoleFor($user, $repo);

        return $role !== null && $role->canWrite();
    }

    public function canAdmin(User $user, Repository $repo): bool
    {
        if ($user->hasPermissionTo('is-super-admin', 'web')) {
            return true;
        }

        $role = $this->effectiveRoleFor($user, $repo);

        return $role !== null && $role->isAdmin();
    }
}
