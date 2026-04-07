<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * Number of times to retry delivery.
     */
    public int $tries = 3;

    /**
     * Retry backoff in seconds.
     *
     * @return int[]
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function __construct(
        public readonly Webhook $webhook,
        public readonly string $event,
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        $webhook = $this->webhook;

        // Skip if webhook was deactivated between queuing and execution
        if (! $webhook->is_active) {
            return;
        }

        $jsonPayload = json_encode($this->payload, JSON_UNESCAPED_SLASHES);
        $startTime = microtime(true);

        $headers = [
            'Content-Type'          => 'application/json',
            'X-Crucible-Event'      => $this->event,
            'X-Crucible-Delivery'   => (string) \Illuminate\Support\Str::uuid(),
            'User-Agent'            => 'Crucible-Webhook/1.0',
        ];

        // Add HMAC signature if secret is configured
        if ($webhook->secret) {
            $headers['X-Crucible-Signature-256'] = WebhookService::computeSignature($jsonPayload, $webhook->secret);
        }

        $delivery = new WebhookDelivery([
            'webhook_id' => $webhook->id,
            'event'      => $this->event,
            'payload'    => $this->payload,
        ]);

        try {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->connectTimeout(10)
                ->withBody($jsonPayload, 'application/json')
                ->post($webhook->url);

            $durationMs = (microtime(true) - $startTime) * 1000;

            $delivery->fill([
                'response_status' => $response->status(),
                'response_body'   => mb_substr($response->body(), 0, 10_000),
                'duration_ms'     => round($durationMs, 2),
                'success'         => $response->successful(),
                'delivered_at'    => now(),
            ]);

            $delivery->save();

            if (! $response->successful()) {
                Log::warning('[DeliverWebhookJob] Non-2xx response', [
                    'webhook_id' => $webhook->id,
                    'status'     => $response->status(),
                    'url'        => $webhook->url,
                ]);
            }
        } catch (\Throwable $e) {
            $durationMs = (microtime(true) - $startTime) * 1000;

            $delivery->fill([
                'duration_ms'  => round($durationMs, 2),
                'success'      => false,
                'error'        => mb_substr($e->getMessage(), 0, 2000),
                'delivered_at' => now(),
            ]);

            $delivery->save();

            Log::error('[DeliverWebhookJob] Delivery failed', [
                'webhook_id' => $webhook->id,
                'error'      => $e->getMessage(),
            ]);

            // Rethrow so the job retries
            throw $e;
        }
    }
}
