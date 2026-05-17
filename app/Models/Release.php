<?php

namespace App\Models;

use App\Enums\ReleaseCategory;
use App\Observers\ReleaseObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

#[ObservedBy([ReleaseObserver::class])]
class Release extends BaseModel
{
    use HasFactory, HasSlug, HasUuids;

    protected $fillable = [
        'repository_id',
        'author_id',
        'tag_name',
        'commit_sha',
        'name',
        'slug',
        'body',
        'is_draft',
        'is_prerelease',
        'is_latest',
        'published_at',
    ];

    protected $casts = [
        'is_draft' => 'boolean',
        'is_prerelease' => 'boolean',
        'is_latest' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(fn (Release $r) => str_replace('.', '-', (string) $r->tag_name))
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(255);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ReleaseEntry::class)
            ->orderByRaw(ReleaseCategory::sortOrderSql())
            ->orderBy('position');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_draft', false)->whereNotNull('published_at');
    }
}
