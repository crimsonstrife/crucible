<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SshKey extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'title',
        'fingerprint',
        'public_key',
        'key_type',
        'last_used_at',
        'is_deploy_key',
        'deploy_repo_id',
    ];

    protected $casts = [
        'last_used_at'  => 'datetime',
        'is_deploy_key' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deployRepository(): BelongsTo
    {
        return $this->belongsTo(Repository::class, 'deploy_repo_id');
    }
}
