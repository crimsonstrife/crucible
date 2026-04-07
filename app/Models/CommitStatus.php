<?php

namespace App\Models;

use App\Enums\CommitStatusState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommitStatus extends BaseModel
{
    use HasUuids;

    protected $fillable = [
        'repository_id',
        'sha',
        'context',
        'state',
        'description',
        'target_url',
        'creator_id',
    ];

    protected $casts = [
        'state' => CommitStatusState::class,
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
