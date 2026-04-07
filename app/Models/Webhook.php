<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends BaseModel
{
    use HasUuids;

    protected $fillable = [
        'repository_id',
        'url',
        'secret',
        'events',
        'is_active',
        'description',
    ];

    protected $casts = [
        'events'    => 'array',
        'is_active' => 'boolean',
        'secret'    => 'encrypted',
    ];

    protected $hidden = [
        'secret',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Check if this webhook subscribes to a given event type.
     */
    public function subscribesTo(string $event): bool
    {
        if (! is_array($this->events)) {
            return false;
        }

        // Support wildcard "*" subscriptions
        if (in_array('*', $this->events, true)) {
            return true;
        }

        // Support prefix matching: "pull_request" matches "pull_request.opened"
        foreach ($this->events as $subscribed) {
            if ($subscribed === $event || str_starts_with($event, $subscribed . '.')) {
                return true;
            }
        }

        return false;
    }
}
