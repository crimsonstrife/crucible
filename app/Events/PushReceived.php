<?php

namespace App\Events;

use App\Models\Repository;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PushReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Repository $repository,
        public readonly string $ref,
        public readonly string $beforeSha,
        public readonly string $afterSha,
        public readonly ?string $pusherName = null,
        public readonly ?string $pusherEmail = null,
    ) {}

    /**
     * Payload for webhook delivery.
     */
    public function toWebhookPayload(): array
    {
        return [
            'event'      => 'push',
            'repository' => [
                'id'   => $this->repository->id,
                'name' => $this->repository->name,
                'slug' => $this->repository->slug,
            ],
            'ref'        => $this->ref,
            'before'     => $this->beforeSha,
            'after'      => $this->afterSha,
            'pusher'     => [
                'name'  => $this->pusherName,
                'email' => $this->pusherEmail,
            ],
        ];
    }
}
