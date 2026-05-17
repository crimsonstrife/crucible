<?php

namespace App\Models;

use App\Enums\ReleaseCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseEntry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'release_id',
        'category',
        'description',
        'position',
    ];

    protected $casts = [
        'category' => ReleaseCategory::class,
        'position' => 'integer',
    ];

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }
}
