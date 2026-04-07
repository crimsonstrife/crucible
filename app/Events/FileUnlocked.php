<?php

namespace App\Events;

use App\Models\Repository;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FileUnlocked
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Repository $repository,
        public readonly string $path,
        public readonly ?string $ownerName = null,
    ) {}

    public function toWebhookPayload(): array
    {
        return [
            'event'      => 'file.unlocked',
            'repository' => [
                'id'   => $this->repository->id,
                'name' => $this->repository->name,
                'slug' => $this->repository->slug,
            ],
            'path'       => $this->path,
            'owner'      => $this->ownerName,
        ];
    }
}
