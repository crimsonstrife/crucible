<?php

namespace App\Services;

use App\Enums\LockMode;
use App\Models\FileLock;
use App\Models\Repository;
use App\Models\RepositoryLockPolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class FileLockService
{
    public function lock(
        Repository $repo,
        ?User $user,
        string $path,
        ?string $ref = null,
        ?string $ownerName = null,
        ?string $ownerIdentifier = null,
        bool $ownerIsExternal = false,
    ): FileLock {
        $path = trim($path);

        if ($this->isLocked($repo, $path)) {
            throw new RuntimeException("File '{$path}' is already locked.");
        }

        // Compute lock expiry from the matching policy, if any.
        $expiresAt = null;
        $policy = $this->matchingPolicy($repo, $path);
        if ($policy?->lock_timeout_hours) {
            $expiresAt = now()->addHours($policy->lock_timeout_hours);
        }

        return $repo->fileLocks()->create([
            'locked_by' => $user?->id,
            'owner_name' => $ownerName ?? $user?->name,
            'owner_identifier' => $ownerIdentifier ?? $user?->email ?? $user?->id,
            'owner_is_external' => $ownerIsExternal || $user === null,
            'path' => $path,
            'ref' => $ref,
            'locked_at' => now(),
            'expires_at' => $expiresAt,
        ])->load('lockedBy');
    }

    public function unlock(Repository $repo, User $user, string $path): void
    {
        $lock = $this->getLock($repo, $path);

        if (! $lock) {
            return;
        }

        if (! $lock->isOwnedBy($user)) {
            throw new RuntimeException("You do not own the lock on '{$path}'.");
        }

        $lock->delete();
    }

    public function forceUnlock(Repository $repo, User $actor, string $path): ?FileLock
    {
        $lock = $this->getLock($repo, $path);

        return $lock ? $this->deleteLock($lock) : null;
    }

    public function listLocks(Repository $repo, array $filters = []): Collection
    {
        return $this->locksQuery($repo, $filters)->get();
    }

    public function paginateLocks(
        Repository $repo,
        ?string $cursor = null,
        int $limit = 100,
        array $filters = [],
    ): array {
        $limit = max(1, min($limit, 100));
        $offset = max((int) ($cursor ?? 0), 0);

        $locks = $this->locksQuery($repo, $filters)
            ->skip($offset)
            ->take($limit + 1)
            ->get();

        $hasMore = $locks->count() > $limit;
        $visibleLocks = $hasMore ? $locks->take($limit)->values() : $locks->values();

        return [
            'locks' => $visibleLocks,
            'next_cursor' => $hasMore ? (string) ($offset + $visibleLocks->count()) : null,
        ];
    }

    public function verifyLocks(Repository $repo, User $user, ?string $cursor = null, int $limit = 100): array
    {
        $page = $this->paginateLocks($repo, $cursor, $limit);

        [$ours, $theirs] = $page['locks']->partition(
            fn (FileLock $lock) => $lock->isOwnedBy($user)
        );

        return [
            'ours' => $ours->values(),
            'theirs' => $theirs->values(),
            'next_cursor' => $page['next_cursor'],
        ];
    }

    public function isLocked(Repository $repo, string $path): bool
    {
        $lock = $repo->fileLocks()->where('path', trim($path))->first();

        if (! $lock) {
            return false;
        }

        // Expired locks are not considered active.
        if ($lock->isExpired()) {
            $lock->delete();

            return false;
        }

        return true;
    }

    public function lockedBy(Repository $repo, string $path): ?User
    {
        return $this->getLock($repo, $path)?->lockedBy;
    }

    public function findLockById(Repository $repo, string $id): ?FileLock
    {
        return $repo->fileLocks()->with('lockedBy')->find($id);
    }

    public function findLockByPath(Repository $repo, string $path): ?FileLock
    {
        return $this->getLock($repo, $path);
    }

    public function deleteLock(FileLock $lock): FileLock
    {
        $lock->loadMissing('lockedBy');
        $lock->delete();

        return $lock;
    }

    /**
     * Determine whether the given path requires a mandatory lock before push.
     */
    public function requiresLock(Repository $repo, string $path): bool
    {
        return $repo->lockPolicies()
            ->where('lock_mode', LockMode::Mandatory)
            ->get()
            ->contains(fn (RepositoryLockPolicy $policy) => $policy->matches($path));
    }

    /**
     * Find the matching lock policy for a path (most specific first).
     */
    public function matchingPolicy(Repository $repo, string $path): ?RepositoryLockPolicy
    {
        return $repo->lockPolicies()
            ->get()
            ->first(fn (RepositoryLockPolicy $policy) => $policy->matches($path));
    }

    /**
     * Validate that a set of changed file paths comply with the repository's
     * lock policies. Returns an array of violations; empty means compliant.
     *
     * Each violation is: ['path' => string, 'reason' => string, 'locked_by' => ?string]
     */
    public function checkPushCompliance(Repository $repo, ?User $user, array $changedPaths): array
    {
        $mandatoryPolicies = $repo->lockPolicies()
            ->where('lock_mode', LockMode::Mandatory)
            ->get();

        if ($mandatoryPolicies->isEmpty()) {
            return [];
        }

        $locks = $repo->fileLocks()->with('lockedBy')->get()->keyBy('path');
        $violations = [];

        foreach ($changedPaths as $path) {
            $path = trim($path);
            $needsLock = $mandatoryPolicies->contains(
                fn (RepositoryLockPolicy $p) => $p->matches($path)
            );

            if (! $needsLock) {
                continue;
            }

            $lock = $locks->get($path);

            if (! $lock || $lock->isExpired()) {
                $violations[] = [
                    'path'      => $path,
                    'reason'    => 'File requires a lock but is not locked.',
                    'locked_by' => null,
                ];
            } elseif ($user && ! $lock->isOwnedBy($user)) {
                $violations[] = [
                    'path'      => $path,
                    'reason'    => "File is locked by {$lock->owner_display_name}.",
                    'locked_by' => $lock->owner_display_name,
                ];
            }
        }

        return $violations;
    }

    /**
     * Delete all expired locks for a repository and return the count removed.
     */
    public function expireStale(Repository $repo): int
    {
        return $repo->fileLocks()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();
    }

    protected function locksQuery(Repository $repo, array $filters = [])
    {
        return $repo->fileLocks()
            ->with('lockedBy')
            ->when(
                filled($filters['path'] ?? null),
                fn (Builder $query) => $query->where('path', trim((string) $filters['path']))
            )
            ->when(
                filled($filters['id'] ?? null),
                fn (Builder $query) => $query->whereKey((string) $filters['id'])
            )
            ->orderBy('locked_at')
            ->orderBy('path');
    }

    protected function getLock(Repository $repo, string $path): ?FileLock
    {
        return $repo->fileLocks()->with('lockedBy')->where('path', trim($path))->first();
    }
}
