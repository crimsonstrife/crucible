<?php

namespace App\Models;

use App\Enums\AccessLevel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RecordShare extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'shareable_type',
        'shareable_id',
        'principal_type',
        'principal_id',
        'access_level',
        'propagate_to_children',
        'expires_at',
        'grantor_id',
    ];

    protected function casts(): array
    {
        return [
            'access_level' => AccessLevel::class,
            'expires_at' => 'immutable_datetime',
            'propagate_to_children' => 'boolean',
        ];
    }

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function principal(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($query) {
            $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }
}
