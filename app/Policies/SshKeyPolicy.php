<?php

namespace App\Policies;

use App\Models\SshKey;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class SshKeyPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        try {
            return $user->hasPermissionTo('is-super-admin', 'web') ? true : null;
        } catch (PermissionDoesNotExist) {
            return null;
        }
    }

    public function view(User $user, SshKey $key): bool
    {
        return $user->id === $key->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, SshKey $key): bool
    {
        return $user->id === $key->user_id;
    }
}
