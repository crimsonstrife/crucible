<?php

namespace App\Services;

use App\Enums\AccessLevel;
use App\Models\Organization;
use App\Models\RecordShare;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class RecordAccessService
{
    public function levelFor(User $user, Model $record): ?AccessLevel
    {
        if (! $this->isAvailable() || ! $this->hasAnyShare($record)) {
            return null;
        }

        return $this->cache()->remember(
            $this->cacheKeyFor($user, $record),
            now()->addMinutes(10),
            function () use ($user, $record): ?AccessLevel {
                $pairs = $this->principalPairsFor($user);
                $levels = collect([
                    $this->highestLevelFromShares($record, $pairs),
                ])->filter();

                if (method_exists($record, 'parentShareable')) {
                    $parent = $record->parentShareable();

                    if ($parent instanceof Model && $this->hasAnyShare($parent)) {
                        $levels->push($this->highestLevelFromShares($parent, $pairs, true));
                    }
                }

                return $levels
                    ->filter()
                    ->reduce(
                        fn (?AccessLevel $carry, AccessLevel $level) => AccessLevel::max($carry, $level),
                        null,
                    );
            },
        );
    }

    public function hasAnyShare(Model $record): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        return $this->cache()->remember(
            sprintf('rs:any:%s:%s', $record::class, $record->getKey()),
            now()->addMinutes(10),
            fn (): bool => RecordShare::query()
                ->where('shareable_type', $record::class)
                ->where('shareable_id', (string) $record->getKey())
                ->notExpired()
                ->exists(),
        );
    }

    public function scopeVisibleTo(Builder $query, User $user, string $ability = 'view'): Builder
    {
        if (! $this->isAvailable()) {
            return $query;
        }

        $model = $query->getModel();
        $pairs = $this->principalPairsFor($user);
        $pairList = $pairs->all();

        $directIds = RecordShare::query()
            ->where('shareable_type', $model::class)
            ->where(function ($query) use ($pairList) {
                foreach ($pairList as $pair) {
                    $query->orWhere(function ($query) use ($pair) {
                        $query
                            ->where('principal_type', $pair['type'])
                            ->where('principal_id', $pair['id']);
                    });
                }
            })
            ->notExpired()
            ->get()
            ->filter(fn (RecordShare $share) => $share->access_level?->allows($ability))
            ->pluck('shareable_id')
            ->all();

        if (empty($directIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereKey($directIds);
    }

    public function bustCacheForShareable(Model $record): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        $this->cache()->forget(sprintf('rs:any:%s:%s', $record::class, $record->getKey()));

        RecordShare::query()
            ->where('shareable_type', $record::class)
            ->where('shareable_id', (string) $record->getKey())
            ->where('principal_type', User::class)
            ->pluck('principal_id')
            ->each(fn (string $userId) => $this->cache()->forget(sprintf('record-shares:%s:%s:%s', $userId, $record::class, $record->getKey())));
    }

    private function highestLevelFromShares(Model $record, Collection $pairs, bool $requirePropagation = false): ?AccessLevel
    {
        $pairList = $pairs->all();

        $rows = RecordShare::query()
            ->where('shareable_type', $record::class)
            ->where('shareable_id', (string) $record->getKey())
            ->when($requirePropagation, fn ($query) => $query->where('propagate_to_children', true))
            ->where(function ($query) use ($pairList) {
                foreach ($pairList as $pair) {
                    $query->orWhere(function ($query) use ($pair) {
                        $query
                            ->where('principal_type', $pair['type'])
                            ->where('principal_id', $pair['id']);
                    });
                }
            })
            ->notExpired()
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        return $rows->reduce(
            fn (?AccessLevel $carry, RecordShare $share) => AccessLevel::max($carry, $share->access_level),
            null,
        );
    }

    private function principalPairsFor(User $user): Collection
    {
        $user->loadMissing('roles', 'teams', 'organizations');

        return collect()
            ->push(['type' => User::class, 'id' => (string) $user->id])
            ->merge($user->roles->map(fn (Role $role): array => ['type' => Role::class, 'id' => (string) $role->id]))
            ->merge($user->teams->map(fn (Team $team): array => ['type' => Team::class, 'id' => (string) $team->id]))
            ->merge($user->organizations->map(fn (Organization $organization): array => ['type' => Organization::class, 'id' => (string) $organization->id]));
    }

    private function cache(): CacheRepository
    {
        $store = Cache::getStore();

        if ($store instanceof TaggableStore) {
            return Cache::tags(['record-shares']);
        }

        return Cache::store();
    }

    private function cacheKeyFor(User $user, Model $record): string
    {
        return sprintf('record-shares:%s:%s:%s', $user->id, $record::class, $record->getKey());
    }

    private function isAvailable(): bool
    {
        return Schema::hasTable('record_shares');
    }
}
