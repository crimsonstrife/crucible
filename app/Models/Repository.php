<?php

namespace App\Models;

use App\Enums\RepositoryVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Repository extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'organization_id',
        'visibility',
        'default_branch',
    ];

    protected $casts = [
        'visibility' => RepositoryVisibility::class,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Repository $repository) {
            if (empty($repository->slug)) {
                $repository->slug = Str::slug($repository->name);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function isPublic(): bool
    {
        return $this->visibility === RepositoryVisibility::Public;
    }

    public function isPrivate(): bool
    {
        return $this->visibility === RepositoryVisibility::Private;
    }

    public function isInternal(): bool
    {
        return $this->visibility === RepositoryVisibility::Internal;
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function canAccess(User $user): bool
    {
        if ($this->isPublic()) {
            return true;
        }

        if ($this->hasMember($user)) {
            return true;
        }

        $org = $this->organization;

        if ($org->isOwner($user) || $org->hasMember($user)) {
            return true;
        }

        return false;
    }
}
