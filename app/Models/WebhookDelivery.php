<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends BaseModel
{
    use HasUuids;

    protected $fillable = [
        'webhook_id',
        'event',
        'payload',
        'response_status',
        'response_body',
        'duration_ms',
        'success',
        'error',
        'delivered_at',
    ];

    protected $casts = [
        'payload'         => 'array',
        'response_status' => 'integer',
        'duration_ms'     => 'float',
        'success'         => 'boolean',
        'delivered_at'    => 'datetime',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
