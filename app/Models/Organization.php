<?php

namespace App\Models;

use App\Traits\IsPermissible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Organization extends BaseModel
{
    use HasFactory, HasSlug, HasUuids, IsPermissible;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'avatar_path',
        'website_url',
        'is_personal',
        'storage_quota_gb',
    ];

    protected $casts = [
        'is_personal'      => 'boolean',
        'storage_quota_gb' => 'integer',
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

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members')
            ->withPivot('role')
            ->withTimestamps()
            ->using(OrganizationMember::class);
    }

    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasPermissionTo('is-super-admin', 'web')) {
            return $query;
        }

        return $query->whereHas('members', fn ($q) => $q->where('users.id', $user->id));
    }
}
