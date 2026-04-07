<?php

namespace App\Listeners;

use App\Events\FileLocked;
use App\Events\FileUnlocked;
use App\Events\PullRequestMerged;
use App\Events\PullRequestOpened;
use App\Events\PushReceived;
use App\Events\ReviewSubmitted;
use App\Models\Repository;
use App\Services\WebhookService;
use Illuminate\Events\Dispatcher;

class DispatchWebhooks
{
    public function __construct(
        protected WebhookService $webhookService,
    ) {}

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(PushReceived::class, [self::class, 'handlePush']);
        $events->listen(PullRequestOpened::class, [self::class, 'handlePullRequestOpened']);
        $events->listen(PullRequestMerged::class, [self::class, 'handlePullRequestMerged']);
        $events->listen(ReviewSubmitted::class, [self::class, 'handleReviewSubmitted']);
        $events->listen(FileLocked::class, [self::class, 'handleFileLocked']);
        $events->listen(FileUnlocked::class, [self::class, 'handleFileUnlocked']);
    }

    public function handlePush(PushReceived $event): void
    {
        $this->dispatch($event->repository, $event);
    }

    public function handlePullRequestOpened(PullRequestOpened $event): void
    {
        $this->dispatch($event->pullRequest->repository, $event);
    }

    public function handlePullRequestMerged(PullRequestMerged $event): void
    {
        $this->dispatch($event->pullRequest->repository, $event);
    }

    public function handleReviewSubmitted(ReviewSubmitted $event): void
    {
        $this->dispatch($event->review->pullRequest->repository, $event);
    }

    public function handleFileLocked(FileLocked $event): void
    {
        $this->dispatch($event->fileLock->repository, $event);
    }

    public function handleFileUnlocked(FileUnlocked $event): void
    {
        $this->dispatch($event->repository, $event);
    }

    private function dispatch(Repository $repository, object $event): void
    {
        $payload = $event->toWebhookPayload();
        $eventName = $payload['event'] ?? 'unknown';

        $this->webhookService->dispatch($repository, $eventName, $payload);
    }
}
