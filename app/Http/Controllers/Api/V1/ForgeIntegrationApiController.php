<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithForgeApi;
use App\Http\Controllers\Controller;
use App\Models\ForgeIntegration;
use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API endpoint for Forge to register/manage its integration with a Crucible repository.
 *
 * This allows Forge to programmatically create the ForgeIntegration record when
 * a user links a Crucible repo to a Forge project, removing the need to
 * configure the link on both sides manually.
 */
class ForgeIntegrationApiController extends Controller
{
    use InteractsWithForgeApi;

    /**
     * POST /api/v1/{organization}/{repository}/forge-integration
     *
     * Creates or updates the ForgeIntegration for this repository.
     * Returns the integration details including a newly issued API token.
     */
    public function store(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);

        $user = $this->apiUser($request);
        abort_unless($user->can('update', $repository), 403);

        $data = $request->validate([
            'forge_project_id' => ['required', 'string', 'max:255'],
            'forge_project_name' => ['nullable', 'string', 'max:255'],
            'forge_url' => ['nullable', 'url', 'max:500'],
        ]);

        $integration = ForgeIntegration::query()->updateOrCreate(
            ['repository_id' => $repository->id],
            [
                'forge_project_id' => $data['forge_project_id'],
                'forge_project_name' => $data['forge_project_name'] ?? null,
                'forge_url' => $data['forge_url'] ?? config('crucible.forge.url'),
                'is_active' => true,
            ]
        );

        $plainToken = null;
        if (! $integration->hasApiToken()) {
            $plainToken = $integration->issueApiToken();
        }

        return response()->json([
            'data' => [
                'id' => $integration->id,
                'repository_id' => $integration->repository_id,
                'forge_project_id' => $integration->forge_project_id,
                'forge_project_name' => $integration->forge_project_name,
                'forge_url' => $integration->forge_url,
                'is_active' => $integration->is_active,
                'api_token' => $plainToken,
            ],
        ], $integration->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * DELETE /api/v1/{organization}/{repository}/forge-integration
     *
     * Removes the ForgeIntegration for this repository.
     */
    public function destroy(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);

        $user = $this->apiUser($request);
        abort_unless($user->can('update', $repository), 403);

        $integration = $repository->forgeIntegration;

        if (! $integration) {
            return response()->json(['message' => 'No Forge integration found.'], 404);
        }

        $integration->delete();

        return response()->json(['message' => 'Forge integration removed.']);
    }
}
