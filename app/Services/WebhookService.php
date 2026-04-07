<?php

namespace App\Services;

use App\Jobs\DeliverWebhookJob;
use App\Models\Repository;
use App\Models\Webhook;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * All supported event types.
     */
    public const SUPPORTED_EVENTS = [
        'push',
        'pull_request.opened',
        'pull_request.merged',
        'pull_request.closed',
        'review.submitted',
        'file.locked',
        'file.unlocked',
    ];

    /**
     * Dispatch webhook deliveries for an event on a repository.
     *
     * Finds all active webhooks that subscribe to the given event and
     * queues a delivery job for each.
     *
     * @param  array  $payload  The event payload to deliver.
     * @return int  Number of deliveries queued.
     */
    public function dispatch(Repository $repository, string $event, array $payload): int
    {
        $webhooks = $repository->webhooks()
            ->where('is_active', true)
            ->get()
            ->filter(fn (Webhook $wh) => $wh->subscribesTo($event));

        if ($webhooks->isEmpty()) {
            return 0;
        }

        $count = 0;

        foreach ($webhooks as $webhook) {
            try {
                DeliverWebhookJob::dispatch($webhook, $event, $payload);
                $count++;
            } catch (\Throwable $e) {
                Log::error('[WebhookService] Failed to queue delivery', [
                    'webhook_id' => $webhook->id,
                    'event'      => $event,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Compute the HMAC-SHA256 signature for a payload.
     */
    public static function computeSignature(string $payload, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Verify an incoming signature against expected.
     */
    public static function verifySignature(string $payload, string $secret, string $signature): bool
    {
        $expected = self::computeSignature($payload, $secret);

        return hash_equals($expected, $signature);
    }
}
