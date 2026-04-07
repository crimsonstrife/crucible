<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\Webhook;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookApiController extends Controller
{
    /**
     * List all webhooks for a repository.
     */
    public function index(Organization $organization, Repository $repository): JsonResponse
    {
        $webhooks = $repository->webhooks()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Webhook $wh) => [
                'id'          => $wh->id,
                'url'         => $wh->url,
                'events'      => $wh->events,
                'is_active'   => $wh->is_active,
                'description' => $wh->description,
                'created_at'  => $wh->created_at,
                'updated_at'  => $wh->updated_at,
            ]);

        return response()->json(['data' => $webhooks]);
    }

    /**
     * Create a new webhook.
     */
    public function store(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        $validated = $request->validate([
            'url'         => ['required', 'url', 'max:2048'],
            'secret'      => ['nullable', 'string', 'max:255'],
            'events'      => ['required', 'array', 'min:1'],
            'events.*'    => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active'   => ['boolean'],
        ]);

        // Validate event names
        $invalidEvents = array_diff(
            $validated['events'],
            array_merge(WebhookService::SUPPORTED_EVENTS, ['*']),
        );

        if (! empty($invalidEvents)) {
            return response()->json([
                'error'          => 'Invalid event types: ' . implode(', ', $invalidEvents),
                'valid_events'   => WebhookService::SUPPORTED_EVENTS,
            ], 422);
        }

        $webhook = $repository->webhooks()->create([
            'url'         => $validated['url'],
            'secret'      => $validated['secret'] ?? null,
            'events'      => $validated['events'],
            'description' => $validated['description'] ?? null,
            'is_active'   => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'data' => [
                'id'          => $webhook->id,
                'url'         => $webhook->url,
                'events'      => $webhook->events,
                'is_active'   => $webhook->is_active,
                'description' => $webhook->description,
                'created_at'  => $webhook->created_at,
            ],
        ], 201);
    }

    /**
     * Update an existing webhook.
     */
    public function update(Request $request, Organization $organization, Repository $repository, Webhook $webhook): JsonResponse
    {
        $validated = $request->validate([
            'url'         => ['sometimes', 'url', 'max:2048'],
            'secret'      => ['nullable', 'string', 'max:255'],
            'events'      => ['sometimes', 'array', 'min:1'],
            'events.*'    => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active'   => ['boolean'],
        ]);

        if (isset($validated['events'])) {
            $invalidEvents = array_diff(
                $validated['events'],
                array_merge(WebhookService::SUPPORTED_EVENTS, ['*']),
            );

            if (! empty($invalidEvents)) {
                return response()->json([
                    'error' => 'Invalid event types: ' . implode(', ', $invalidEvents),
                ], 422);
            }
        }

        $webhook->update($validated);

        return response()->json([
            'data' => [
                'id'          => $webhook->id,
                'url'         => $webhook->url,
                'events'      => $webhook->events,
                'is_active'   => $webhook->is_active,
                'description' => $webhook->description,
                'updated_at'  => $webhook->updated_at,
            ],
        ]);
    }

    /**
     * Delete a webhook.
     */
    public function destroy(Organization $organization, Repository $repository, Webhook $webhook): JsonResponse
    {
        $webhook->delete();

        return response()->json(null, 204);
    }

    /**
     * List recent deliveries for a webhook.
     */
    public function deliveries(Organization $organization, Repository $repository, Webhook $webhook): JsonResponse
    {
        $deliveries = $webhook->deliveries()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($d) => [
                'id'              => $d->id,
                'event'           => $d->event,
                'response_status' => $d->response_status,
                'success'         => $d->success,
                'duration_ms'     => $d->duration_ms,
                'error'           => $d->error,
                'delivered_at'    => $d->delivered_at,
            ]);

        return response()->json(['data' => $deliveries]);
    }

    /**
     * List supported event types.
     */
    public function events(): JsonResponse
    {
        return response()->json(['data' => WebhookService::SUPPORTED_EVENTS]);
    }
}
