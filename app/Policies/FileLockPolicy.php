<?php

namespace App\Policies;

use App\Models\FileLock;
use App\Models\User;
use App\Services\RecordAccessService;
use Illuminate\Auth\Access\HandlesAuthorization;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class FileLockPolicy
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

    public function unlock(User $user, FileLock $lock): bool
    {
        // Lock owner can always unlock their own locks
        if ($lock->isOwnedBy($user)) {
            return true;
        }

        if ($lock->repository->owner_id === $user->id) {
            return true;
        }

        // Maintainer or Admin role on the repo can force-unlock
        $role = $lock->repository->collaboratorRoleFor($user);

        if ($role !== null && $role->canMaintain()) {
            return true;
        }

        return $this->recordAccessService
            ->levelFor($user, $lock->repository)
            ?->allows('manage') ?? false;
    }
}
