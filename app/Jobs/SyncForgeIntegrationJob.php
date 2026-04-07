<?php

namespace App\Jobs;

use App\Models\ForgeIntegration;
use App\Services\ForgeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncForgeIntegrationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ForgeIntegration $integration
    ) {}

    public function handle(ForgeService $forgeService): void
    {
        if (!$forgeService->isConfigured()) {
            Log::info('[SyncForgeIntegrationJob] skipped — Forge not configured');

            return;
        }

        $project = $forgeService->getProject($this->integration->forge_project_id);

        if ($project) {
            $this->integration->update([
                'forge_project_name' => $project['name'] ?? $this->integration->forge_project_name,
                'last_synced_at'     => now(),
            ]);
        }

        Log::info('[SyncForgeIntegrationJob] completed', ['integration' => $this->integration->id]);
    }
}
