<?php

namespace App\Observers;

use App\Models\Release;

class ReleaseObserver
{
    public function saved(Release $release): void
    {
        $this->refreshLatest($release->repository_id);
    }

    public function deleted(Release $release): void
    {
        $this->refreshLatest($release->repository_id);
    }

    public function restored(Release $release): void
    {
        $this->refreshLatest($release->repository_id);
    }

    /**
     * Recompute is_latest for a repository: exactly one published, non-draft,
     * non-prerelease release with the newest published_at gets the flag.
     */
    protected function refreshLatest(string $repositoryId): void
    {
        $newest = Release::query()
            ->where('repository_id', $repositoryId)
            ->where('is_draft', false)
            ->where('is_prerelease', false)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->first();

        Release::withoutEvents(function () use ($repositoryId, $newest) {
            Release::query()
                ->where('repository_id', $repositoryId)
                ->where('is_latest', true)
                ->when($newest, fn ($q) => $q->where('id', '!=', $newest->id))
                ->update(['is_latest' => false]);

            if ($newest && ! $newest->is_latest) {
                $newest->forceFill(['is_latest' => true])->saveQuietly();
            }
        });
    }
}
