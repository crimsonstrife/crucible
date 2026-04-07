<?php

namespace App\Events;

use App\Models\PullRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PullRequestMerged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PullRequest $pullRequest,
        public readonly string $mergeSha,
    ) {}

    public function toWebhookPayload(): array
    {
        return [
            'event'        => 'pull_request.merged',
            'repository'   => [
                'id'   => $this->pullRequest->repository->id,
                'name' => $this->pullRequest->repository->name,
                'slug' => $this->pullRequest->repository->slug,
            ],
            'pull_request' => [
                'id'             => $this->pullRequest->id,
                'number'         => $this->pullRequest->number,
                'title'          => $this->pullRequest->title,
                'source_branch'  => $this->pullRequest->source_branch,
                'target_branch'  => $this->pullRequest->target_branch,
                'merge_sha'      => $this->mergeSha,
                'merge_strategy' => $this->pullRequest->merge_strategy?->value,
            ],
        ];
    }
}
