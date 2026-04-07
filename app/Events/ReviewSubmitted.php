<?php

namespace App\Events;

use App\Models\PullRequestReview;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReviewSubmitted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PullRequestReview $review,
    ) {}

    public function toWebhookPayload(): array
    {
        $pr = $this->review->pullRequest;

        return [
            'event'        => 'review.submitted',
            'repository'   => [
                'id'   => $pr->repository->id,
                'name' => $pr->repository->name,
                'slug' => $pr->repository->slug,
            ],
            'pull_request' => [
                'id'     => $pr->id,
                'number' => $pr->number,
                'title'  => $pr->title,
            ],
            'review'       => [
                'id'       => $this->review->id,
                'state'    => $this->review->state->value,
                'body'     => $this->review->body,
                'reviewer' => $this->review->reviewer?->name,
            ],
        ];
    }
}
