<?php

namespace App\Events;

use App\Models\FileLock;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FileLocked
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly FileLock $fileLock,
    ) {}

    public function toWebhookPayload(): array
    {
        return [
            'event'      => 'file.locked',
            'repository' => [
                'id'   => $this->fileLock->repository->id,
                'name' => $this->fileLock->repository->name,
                'slug' => $this->fileLock->repository->slug,
            ],
            'lock'       => [
                'id'    => $this->fileLock->id,
                'path'  => $this->fileLock->path,
                'owner' => $this->fileLock->owner?->name ?? $this->fileLock->owner_name,
            ],
        ];
    }
}
