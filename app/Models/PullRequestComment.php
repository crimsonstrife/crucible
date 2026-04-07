<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PullRequestComment extends BaseModel
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'pull_request_id',
        'user_id',
        'body',
        'in_reply_to_id',
        'file_path',
        'diff_position',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PullRequestComment::class, 'in_reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(PullRequestComment::class, 'in_reply_to_id')->oldest();
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isTopLevel(): bool
    {
        return $this->in_reply_to_id === null;
    }

    public function isInlineComment(): bool
    {
        return $this->file_path !== null;
    }
}
