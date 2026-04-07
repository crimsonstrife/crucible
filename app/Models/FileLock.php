<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileLock extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'repository_id',
        'locked_by',
        'owner_name',
        'owner_identifier',
        'owner_is_external',
        'path',
        'ref',
        'locked_at',
        'expires_at',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
        'expires_at' => 'datetime',
        'owner_is_external' => 'boolean',
    ];

    protected $appends = [
        'owner_display_name',
        'owner_display_identifier',
        'owner_external',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->locked_by === $user->id;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function getOwnerDisplayNameAttribute(): string
    {
        return $this->lockedBy?->name
            ?? $this->owner_name
            ?? $this->owner_identifier
            ?? 'Unknown user';
    }

    public function getOwnerDisplayIdentifierAttribute(): ?string
    {
        return $this->lockedBy?->email
            ?? $this->owner_identifier
            ?? $this->locked_by;
    }

    public function getOwnerExternalAttribute(): bool
    {
        return $this->owner_is_external || $this->lockedBy === null;
    }

    public function toLfsArray(): array
    {
        return [
            'id' => $this->id,
            'path' => $this->path,
            'locked_at' => $this->locked_at?->toAtomString(),
            'owner' => [
                'name' => $this->owner_display_name,
                'identifier' => $this->owner_display_identifier,
                'external' => $this->owner_external,
                'user_id' => $this->lockedBy?->id,
            ],
        ];
    }
}
