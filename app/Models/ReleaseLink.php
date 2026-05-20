<?php

namespace App\Models;

use App\Enums\ReleaseLinkPlatform;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseLink extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'release_id',
        'label',
        'url',
        'platform',
        'position',
    ];

    protected $casts = [
        'platform' => ReleaseLinkPlatform::class,
        'position' => 'integer',
    ];

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }
}
