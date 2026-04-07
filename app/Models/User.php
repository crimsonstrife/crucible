<?php

namespace App\Models;

use App\Traits\HasPermissionSets;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use HasApiTokens,
        HasFactory,
        HasPermissionSets,
        HasPermissions,
        HasProfilePhoto,
        HasRoles,
        HasTeams,
        HasUuids,
        Notifiable,
        TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'forge_user_id',
        'email_notifications',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $appends = [
        'profile_photo_url',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'     => 'datetime',
            'password'              => 'hashed',
            'email_notifications'   => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasPermissionTo('is-super-admin', 'web') || $this->hasPermissionTo('is-admin', 'web');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
            ->withPivot('role')
            ->withTimestamps()
            ->using(OrganizationMember::class);
    }

    public function ownedRepositories(): HasMany
    {
        return $this->hasMany(Repository::class, 'owner_id');
    }

    public function sshKeys(): HasMany
    {
        return $this->hasMany(SshKey::class);
    }

    public function fileLocks(): HasMany
    {
        return $this->hasMany(FileLock::class, 'locked_by');
    }

    public function connectedApps(): HasMany
    {
        return $this->hasMany(ConnectedApp::class);
    }
}
