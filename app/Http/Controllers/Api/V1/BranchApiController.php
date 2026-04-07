<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithForgeApi;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Repository;
use App\Services\ForgeService;
use App\Services\NativeGitRepositoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * API endpoints for branch operations.
 * Consumed by Forge (and any other authorised API client) to search/create branches.
 */
class BranchApiController extends Controller
{
    use InteractsWithForgeApi;

    public function __construct(
        protected NativeGitRepositoryService $git,
        protected ForgeService $forge,
    ) {}

    /**
     * GET /api/v1/{organization}/{repository}/branches?q=
     *
     * Returns [{name, default, url}]
     */
    public function index(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        $this->ensureForgeIntegrationMatchesRepository($request, $repository);

        $user = $this->apiUser($request);

        abort_unless($user->can('view', $repository), 403);

        $q = (string) $request->query('q', '');
        $limit = min((int) $request->query('per_page', 20), 100);

        try {
            $branches = $this->git->branches($repository);
        } catch (\Exception) {
            $branches = [];
        }

        $default = $repository->default_branch ?? 'main';

        // Filter by query substring
        if ($q !== '') {
            $branches = array_values(array_filter($branches, fn ($b) => str_contains($b, $q)));
        }

        $branches = array_slice($branches, 0, $limit);

        $data = array_map(fn ($name) => [
            'name' => $name,
            'default' => $name === $default,
            'url' => route('repositories.commits', [$organization, $repository, $name]),
        ], $branches);

        return response()->json(['data' => array_values($data)]);
    }

    /**
     * POST /api/v1/{organization}/{repository}/branches
     *
     * Body: { name: string, from_ref?: string, forge_issue_key?: string }
     * Returns {name, forge_issue_key, url}
     */
    public function store(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        $this->ensureForgeIntegrationMatchesRepository($request, $repository);

        $user = $this->apiUser($request);

        abort_unless($user->can('push', $repository), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9._\-\/]+$/'],
            'from_ref' => ['nullable', 'string', 'max:255'],
            'forge_issue_key' => ['nullable', 'string', 'max:50'],
        ]);

        $fromRef = $data['from_ref'] ?? $repository->default_branch ?? 'main';
        $newBranch = $data['name'];

        try {
            $fromSha = $this->git->resolveSha($repository, $fromRef);
            $this->git->createBranch($repository, $newBranch, $fromSha);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $branchUrl = route('repositories.commits', [$organization, $repository, $newBranch]);

        if (! empty($data['forge_issue_key'])) {
            $this->forge->linkBranchToIssue($data['forge_issue_key'], [
                'name' => $newBranch,
                'ref' => $newBranch,
                'url' => $branchUrl,
            ], $user);
        }

        return response()->json([
            'data' => [
                'name' => $newBranch,
                'forge_issue_key' => $data['forge_issue_key'] ?? null,
                'url' => $branchUrl,
            ],
        ], 201);
    }

    /**
     * GET /api/v1/{organization}/{repository}/default-branch
     *
     * Returns {default: string}
     */
    public function defaultBranch(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        $this->ensureForgeIntegrationMatchesRepository($request, $repository);

        $user = $this->apiUser($request);

        abort_unless($user->can('view', $repository), 403);

        $default = $repository->default_branch ?? 'main';

        return response()->json(['data' => ['default' => $default]]);
    }
}
