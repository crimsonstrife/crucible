<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReleaseToken extends BaseModel
{
    use HasFactory, HasUuids;

    public const TOKEN_PREFIX = 'crl_';
    public const TOKEN_RANDOM_LENGTH = 32;
    public const DISPLAY_PREFIX_LENGTH = 12;

    protected $fillable = [
        'repository_id',
        'created_by',
        'name',
        'token_hash',
        'token_prefix',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    public static function generate(
        Repository $repository,
        ?User $author,
        string $name,
        ?Carbon $expiresAt = null,
    ): array {
        $raw = self::TOKEN_PREFIX.Str::random(self::TOKEN_RANDOM_LENGTH);

        $token = static::create([
            'repository_id' => $repository->id,
            'created_by' => $author?->id,
            'name' => $name,
            'token_hash' => hash('sha256', $raw),
            'token_prefix' => substr($raw, 0, self::DISPLAY_PREFIX_LENGTH),
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $token, 'raw' => $raw];
    }

    public static function findByRawToken(string $raw): ?self
    {
        if (! str_starts_with($raw, self::TOKEN_PREFIX)) {
            return null;
        }

        return static::query()
            ->where('token_hash', hash('sha256', $raw))
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
