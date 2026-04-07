<?php

namespace App\Models;

use App\Traits\HasPermissionSets;
use App\Traits\IsPermissible;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Roles use standard auto-increment integer PKs (Spatie default).
 * UUID is only needed on domain models — not on ACL primitives.
 */
class Role extends SpatieRole
{
    use HasPermissionSets, IsPermissible;

    public function permissionSets(): BelongsToMany
    {
        return $this->belongsToMany(PermissionSet::class, 'role_permission_sets');
    }
}
