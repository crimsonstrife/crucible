<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LfsUploadSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'repository_id',
        'oid',
        'total_size',
        'uploaded_bytes',
        'expires_at',
    ];

    protected $casts = [
        'total_size'     => 'integer',
        'uploaded_bytes' => 'integer',
        'expires_at'     => 'datetime',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isComplete(): bool
    {
        return $this->uploaded_bytes >= $this->total_size;
    }

    public function remainingBytes(): int
    {
        return max(0, $this->total_size - $this->uploaded_bytes);
    }
}
