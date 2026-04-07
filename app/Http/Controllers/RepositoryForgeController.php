<?php

namespace App\Http\Controllers;

use App\Jobs\SyncForgeIntegrationJob;
use App\Models\ForgeIntegration;
use App\Models\Organization;
use App\Models\Repository;
use App\Services\ForgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manages the 1:1 Forge project integration for a single repository.
 *
 * Routes are scoped under /{organization}/{repository}, so both models are
 * resolved (and their relationship verified) before reaching any action.
 */
class RepositoryForgeController extends Controller
{
    public function __construct(protected ForgeService $forge) {}

    /**
     * Show the Forge integration settings page for a repository.
     */
    public function show(Organization $organization, Repository $repository): View
    {
        $this->authorize('update', $repository);

        $integration = $repository->forgeIntegration;
        $forgeEnabled = $this->forge->isConfigured();

        // Scope the project list to the signed-in user's Forge identity.
        // Returns [] if the user hasn't signed in via Forge SSO yet.
        $forgeUserId = auth()->user()?->forge_user_id;
        $projects = ($forgeEnabled && $forgeUserId)
            ? $this->forge->getProjects($forgeUserId)
            : [];

        return view('repositories.forge', compact(
            'organization', 'repository', 'integration', 'forgeEnabled', 'projects',
        ));
    }

    /**
     * Save / update the Forge integration for a repository.
     */
    public function update(Request $request, Organization $organization, Repository $repository): RedirectResponse
    {
        $this->authorize('update', $repository);

        $data = $request->validate([
            'forge_project_id' => ['required', 'string', 'max:255'],
            'forge_project_name' => ['nullable', 'string', 'max:255'],
        ]);

        $forgeUrl = rtrim((string) config('crucible.forge.url'), '/');

        ForgeIntegration::updateOrCreate(
            ['repository_id' => $repository->id],
            [
                'forge_project_id' => $data['forge_project_id'],
                'forge_project_name' => $data['forge_project_name'] ?? $data['forge_project_id'],
                'forge_url' => $forgeUrl,
                'is_active' => true,
            ]
        );

        return redirect()
            ->route('repositories.forge.show', [$organization, $repository])
            ->with('success', 'Forge integration saved.');
    }

    /**
     * Remove the Forge integration from a repository.
     */
    public function destroy(Organization $organization, Repository $repository): RedirectResponse
    {
        $this->authorize('update', $repository);

        $integration = $repository->forgeIntegration;

        if ($integration) {
            $integration->delete();
        }

        return redirect()
            ->route('repositories.forge.show', [$organization, $repository])
            ->with('success', 'Forge integration removed.');
    }

    /**
     * Manually trigger a sync of the linked Forge project metadata.
     */
    public function sync(Organization $organization, Repository $repository): RedirectResponse
    {
        $this->authorize('update', $repository);

        $integration = $repository->forgeIntegration;

        if (! $integration) {
            return back()->with('error', 'No Forge integration configured for this repository.');
        }

        SyncForgeIntegrationJob::dispatch($integration);

        return back()->with('success', 'Sync queued — Forge project data will update shortly.');
    }

    /**
     * Generate (or regenerate) a machine token for Forge to call this repo.
     *
     * The plaintext token is flashed to the session and shown exactly once.
     * After the redirect only the hash remains stored.
     */
    public function generateToken(Organization $organization, Repository $repository): RedirectResponse
    {
        $this->authorize('update', $repository);

        $integration = $repository->forgeIntegration;

        if (! $integration) {
            return back()->with('error', 'Set up a Forge integration before generating a token.');
        }

        session()->flash('forge_api_token', $integration->issueApiToken());

        return redirect()
            ->route('repositories.forge.show', [$organization, $repository])
            ->with('success', 'Forge API token generated. Copy it now — it will not be shown again.');
    }

    /**
     * Revoke the Forge API token without removing the integration.
     */
    public function revokeToken(Organization $organization, Repository $repository): RedirectResponse
    {
        $this->authorize('update', $repository);

        $integration = $repository->forgeIntegration;

        if (! $integration) {
            return back()->with('error', 'No Forge integration found.');
        }

        $integration->revokeApiToken();

        return back()->with('success', 'API token revoked.');
    }
}
