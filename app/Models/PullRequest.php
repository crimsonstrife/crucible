<?php

namespace App\Models;

use App\Enums\MergeStrategy;
use App\Enums\PullRequestStatus;
use App\Enums\ReviewState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PullRequest extends BaseModel
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'repository_id',
        'number',
        'title',
        'description',
        'author_id',
        'source_branch',
        'target_branch',
        'status',
        'merge_strategy',
        'is_draft',
        'head_sha',
        'base_sha',
        'merged_by_id',
        'merge_commit_sha',
        'merged_at',
        'forge_issue_key',
    ];

    protected $attributes = [
        'merge_strategy' => 'merge_commit',
        'is_draft'       => false,
    ];

    protected $casts = [
        'status'         => PullRequestStatus::class,
        'merge_strategy' => MergeStrategy::class,
        'is_draft'       => 'boolean',
        'merged_at'      => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function mergedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_by_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PullRequestComment::class)->oldest();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PullRequestReview::class)->latest();
    }

    /**
     * Users who have been explicitly requested for review.
     */
    public function requestedReviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pull_request_reviewers')
            ->withTimestamps();
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === PullRequestStatus::Open;
    }

    public function isMerged(): bool
    {
        return $this->status === PullRequestStatus::Merged;
    }

    public function isClosed(): bool
    {
        return $this->status === PullRequestStatus::Closed;
    }

    public function isDraft(): bool
    {
        return (bool) $this->is_draft;
    }

    /**
     * Count the number of current approvals (latest review per reviewer).
     */
    public function approvalCount(): int
    {
        return $this->latestReviewPerReviewer()
            ->filter(fn (PullRequestReview $r) => $r->isApproval())
            ->count();
    }

    /**
     * Check if any reviewer has requested changes (latest review per reviewer).
     */
    public function hasChangesRequested(): bool
    {
        return $this->latestReviewPerReviewer()
            ->contains(fn (PullRequestReview $r) => $r->isChangesRequested());
    }

    /**
     * Get the latest review from each distinct reviewer.
     *
     * @return \Illuminate\Support\Collection<int, PullRequestReview>
     */
    /**
     * Get the latest review from each distinct reviewer.
     * Uses the review ID (UUID, v7 = time-ordered) as tiebreaker.
     *
     * @return \Illuminate\Support\Collection<int, PullRequestReview>
     */
    public function latestReviewPerReviewer(): \Illuminate\Support\Collection
    {
        return $this->reviews()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('reviewer_id')
            ->map(fn ($reviews) => $reviews->first())
            ->values();
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }
}
