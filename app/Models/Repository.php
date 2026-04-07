<?php

namespace App\Models;

use App\Enums\CollaboratorRole;
use App\Enums\EngineType;
use App\Enums\RepositoryVisibility;
use App\Enums\VcsType;
use App\Traits\HasRecordShares;
use App\Traits\IsPermissible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Repository extends BaseModel
{
    use HasFactory, HasRecordShares, HasSlug, HasUuids, IsPermissible;

    protected $fillable = [
        'organization_id',
        'owner_id',
        'name',
        'slug',
        'description',
        'vcs_type',
        'visibility',
        'default_branch',
        'lfs_enabled',
        'is_archived',
        'is_fork',
        'forked_from_id',
        'remote_url',
        'last_synced_at',
        'auto_sync',
        'size_kb',
        'lfs_size_kb',
        'engine_type',
    ];

    protected $casts = [
        'vcs_type' => VcsType::class,
        'visibility' => RepositoryVisibility::class,
        'lfs_enabled'    => 'boolean',
        'is_archived'    => 'boolean',
        'is_fork'        => 'boolean',
        'size_kb'        => 'integer',
        'lfs_size_kb'    => 'integer',
        'remote_url'     => 'encrypted',
        'last_synced_at' => 'datetime',
        'auto_sync'      => 'boolean',
        'engine_type'    => EngineType::class,
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'repository_collaborators')
            ->withPivot('role')
            ->withTimestamps()
            ->using(RepositoryCollaborator::class);
    }

    public function lfsObjects(): HasMany
    {
        return $this->hasMany(LfsObject::class);
    }

    public function fileLocks(): HasMany
    {
        return $this->hasMany(FileLock::class);
    }

    public function lockPolicies(): HasMany
    {
        return $this->hasMany(RepositoryLockPolicy::class);
    }

    public function lfsPolicies(): HasMany
    {
        return $this->hasMany(RepositoryLfsPolicy::class);
    }

    public function pullRequests(): HasMany
    {
        return $this->hasMany(PullRequest::class);
    }

    public function branchProtectionRules(): HasMany
    {
        return $this->hasMany(BranchProtectionRule::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    public function commitStatuses(): HasMany
    {
        return $this->hasMany(CommitStatus::class);
    }

    public function sparseCheckoutProfiles(): HasMany
    {
        return $this->hasMany(SparseCheckoutProfile::class);
    }

    /**
     * Alias for fileLocks() — required by Laravel's scoped route binding when
     * the route parameter is named {lock} (binds via Repository::locks()).
     */
    public function locks(): HasMany
    {
        return $this->fileLocks();
    }

    public function forgedFrom(): BelongsTo
    {
        return $this->belongsTo(Repository::class, 'forked_from_id');
    }

    /** The linked Forge project for this repository (1:1). */
    public function forgeIntegration(): HasOne
    {
        return $this->hasOne(ForgeIntegration::class);
    }

    public function sshKeys(): HasMany
    {
        return $this->hasMany(SshKey::class, 'deploy_repo_id');
    }

    public function collaboratorRoleFor(User $user): ?CollaboratorRole
    {
        $pivot = $this->collaborators()->where('users.id', $user->id)->first()?->pivot;

        if (! $pivot) {
            return null;
        }

        return $pivot->role instanceof CollaboratorRole
            ? $pivot->role
            : CollaboratorRole::tryFrom($pivot->role);
    }
}
