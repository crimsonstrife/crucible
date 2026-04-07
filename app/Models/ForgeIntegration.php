<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ForgeIntegration extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'repository_id',
        'forge_project_id',
        'forge_project_name',
        'forge_url',
        'is_active',
        'last_synced_at',
        'api_token_hash',
        'api_token_last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        'api_token_last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'api_token_hash',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function hasApiToken(): bool
    {
        return filled($this->api_token_hash);
    }

    public function issueApiToken(): string
    {
        $plainTextToken = 'cru_forge_'.Str::random(40);

        $this->forceFill([
            'api_token_hash' => hash('sha256', $plainTextToken),
            'api_token_last_used_at' => null,
        ])->save();

        return $plainTextToken;
    }

    public function revokeApiToken(): void
    {
        $this->forceFill([
            'api_token_hash' => null,
            'api_token_last_used_at' => null,
        ])->save();
    }
}
