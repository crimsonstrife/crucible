<?php

namespace App\Traits;

use App\Enums\AccessLevel;
use App\Models\RecordShare;
use App\Models\User;
use App\Services\RecordAccessService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasRecordShares
{
    public function shares(): MorphMany
    {
        return $this->morphMany(RecordShare::class, 'shareable');
    }

    public function shareWith(
        object $principal,
        AccessLevel $level,
        ?CarbonInterface $expiresAt = null,
        bool $propagate = false,
        ?string $grantorId = null
    ): RecordShare {
        /** @var RecordShare $share */
        $share = $this->shares()->updateOrCreate(
            [
                'principal_type' => $principal::class,
                'principal_id' => (string) $principal->id,
            ],
            [
                'access_level' => $level,
                'propagate_to_children' => $propagate,
                'expires_at' => $expiresAt,
                'grantor_id' => $grantorId,
            ],
        );

        app(RecordAccessService::class)->bustCacheForShareable($this);

        return $share;
    }

    public function revokeShare(object $principal): void
    {
        $this->shares()
            ->where('principal_type', $principal::class)
            ->where('principal_id', (string) $principal->id)
            ->delete();

        app(RecordAccessService::class)->bustCacheForShareable($this);
    }

    public function parentShareable(): ?object
    {
        return null;
    }

    public function scopeVisibleTo(Builder $query, User $user, string $ability = 'view'): Builder
    {
        return app(RecordAccessService::class)->scopeVisibleTo($query, $user, $ability);
    }

    public function effectiveAccessFor(User $user): ?AccessLevel
    {
        return app(RecordAccessService::class)->levelFor($user, $this);
    }
}
