<?php

namespace App\Models;

use App\Traits\IsPermissible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSet extends Model
{
    use HasFactory, HasUuids, IsPermissible;

    protected $fillable = [
        'name',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_set_permissions');
    }

    public function mutedPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_set_mutes');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(PermissionSetGroup::class, 'group_permission_sets', 'permission_set_id', 'group_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission_sets');
    }
}
