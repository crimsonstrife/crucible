<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Support\Facades\DB;

/**
 * Manages storage quotas at the organization level.
 *
 * Quota is checked before LFS uploads and optionally during pre-receive
 * to prevent organizations from exceeding their allocated storage.
 */
class StorageQuotaService
{
    /**
     * Check whether the organization has a quota configured.
     */
    public function hasQuota(Organization $organization): bool
    {
        return $organization->storage_quota_gb !== null && $organization->storage_quota_gb > 0;
    }

    /**
     * Get the quota in bytes.
     */
    public function quotaBytes(Organization $organization): ?int
    {
        if (! $this->hasQuota($organization)) {
            return null;
        }

        return $organization->storage_quota_gb * 1024 * 1024 * 1024;
    }

    /**
     * Calculate total storage used by an organization across all repositories.
     *
     * Returns bytes used: sum of (size_kb + lfs_size_kb) * 1024 for all repos.
     */
    public function usedBytes(Organization $organization): int
    {
        $result = $organization->repositories()
            ->selectRaw('COALESCE(SUM(size_kb), 0) + COALESCE(SUM(lfs_size_kb), 0) as total_kb')
            ->first();

        return (int) ($result->total_kb ?? 0) * 1024;
    }

    /**
     * Get remaining bytes before quota is reached.
     * Returns null if no quota is set (unlimited).
     */
    public function remainingBytes(Organization $organization): ?int
    {
        $quota = $this->quotaBytes($organization);

        if ($quota === null) {
            return null;
        }

        return max(0, $quota - $this->usedBytes($organization));
    }

    /**
     * Check whether an upload of the given size would exceed the quota.
     *
     * Returns true if the upload is allowed (within quota or no quota set).
     */
    public function canUpload(Organization $organization, int $sizeBytes): bool
    {
        if (! $this->hasQuota($organization)) {
            return true; // No quota = unlimited
        }

        $remaining = $this->remainingBytes($organization);

        return $remaining !== null && $sizeBytes <= $remaining;
    }

    /**
     * Check quota for a specific repository's organization.
     *
     * Convenience method that loads the organization if not already loaded.
     */
    public function canUploadToRepository(Repository $repository, int $sizeBytes): bool
    {
        $repository->loadMissing('organization');

        return $this->canUpload($repository->organization, $sizeBytes);
    }

    /**
     * Get storage usage summary for an organization.
     */
    public function usageSummary(Organization $organization): array
    {
        $used = $this->usedBytes($organization);
        $quota = $this->quotaBytes($organization);

        $repoBreakdown = $organization->repositories()
            ->select('id', 'name', 'slug', 'size_kb', 'lfs_size_kb')
            ->orderByRaw('(COALESCE(size_kb, 0) + COALESCE(lfs_size_kb, 0)) DESC')
            ->get()
            ->map(fn ($repo) => [
                'id'           => $repo->id,
                'name'         => $repo->name,
                'slug'         => $repo->slug,
                'git_size_kb'  => (int) $repo->size_kb,
                'lfs_size_kb'  => (int) $repo->lfs_size_kb,
                'total_kb'     => (int) $repo->size_kb + (int) $repo->lfs_size_kb,
            ])
            ->all();

        return [
            'used_bytes'       => $used,
            'used_human'       => $this->humanSize($used),
            'quota_bytes'      => $quota,
            'quota_human'      => $quota ? $this->humanSize($quota) : null,
            'quota_gb'         => $organization->storage_quota_gb,
            'remaining_bytes'  => $quota ? max(0, $quota - $used) : null,
            'usage_percent'    => $quota ? round(($used / $quota) * 100, 1) : null,
            'repositories'     => $repoBreakdown,
        ];
    }

    /**
     * Format bytes into a human-readable string.
     */
    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return round($value, 2) . ' ' . $units[$unit];
    }
}
