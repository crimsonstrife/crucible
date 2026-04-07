<?php

namespace App\Models;

use App\Enums\ReviewState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PullRequestReview extends BaseModel
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'pull_request_id',
        'reviewer_id',
        'state',
        'body',
    ];

    protected $casts = [
        'state' => ReviewState::class,
    ];

    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function isApproval(): bool
    {
        return $this->state === ReviewState::Approved;
    }

    public function isChangesRequested(): bool
    {
        return $this->state === ReviewState::ChangesRequested;
    }
}
