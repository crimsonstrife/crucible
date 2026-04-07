<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\AppToken;
use App\Models\ForgeIntegration;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Http\Request;

trait InteractsWithForgeApi
{
    protected function apiUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    protected function ensureForgeIntegrationMatchesRepository(Request $request, Repository $repository): void
    {
        $appToken = $request->attributes->get('app_token');

        if ($appToken instanceof AppToken) {
            abort_unless($repository->forgeIntegration?->is_active, 403, 'This repository is not linked to Forge.');

            return;
        }

        $integration = $request->attributes->get('forge_integration');

        if ($integration instanceof ForgeIntegration && $integration->repository_id !== $repository->id) {
            abort(403, 'This Forge token is not linked to the requested repository.');
        }

        if (! $integration && ! $repository->forgeIntegration?->is_active) {
            abort(403, 'This repository is not linked to Forge.');
        }
    }
}
