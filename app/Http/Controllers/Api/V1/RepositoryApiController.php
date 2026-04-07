<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RepositoryVisibility;
use App\Http\Controllers\Api\V1\Concerns\InteractsWithForgeApi;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Repository;
use App\Services\RepositoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepositoryApiController extends Controller
{
    use InteractsWithForgeApi;

    public function __construct(protected RepositoryService $service) {}

    /**
     * List repositories visible to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless(
            $request->attributes->get('forge_auth_mode') !== 'integration_token',
            403,
            'Repository listing requires a user token or app token.',
        );

        $repos = Repository::query()
            ->where(function ($q) use ($request) {
                $q->where('visibility', 'public')
                    ->orWhere('owner_id', $request->user()->id)
                    ->orWhereHas('collaborators', fn ($c) => $c->where('users.id', $request->user()->id))
                    ->orWhereHas('organization.members', fn ($m) => $m->where('users.id', $request->user()->id));
            })
            ->with('organization', 'owner', 'forgeIntegration')
            ->orderBy('name')
            ->paginate(50);

        return response()->json($repos);
    }

    /**
     * Get a single repository by {org}/{repo} slug.
     */
    public function show(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        $this->ensureForgeIntegrationMatchesRepository($request, $repository);

        $user = $this->apiUser($request);

        abort_unless($user->can('view', $repository), 403);

        return response()->json(['data' => $repository->load('organization', 'owner', 'forgeIntegration')]);
    }

    /**
     * Create a repository inside an organization.
     */
    public function store(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($request->user()->can('view', $organization), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_\-\.]+$/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['nullable', Rule::enum(RepositoryVisibility::class)],
            'vcs_type' => ['nullable', 'in:git,svn'],
            'lfs_enabled' => ['nullable', 'boolean'],
            'default_branch' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $repo = $this->service->create($organization, $request->user(), $data);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $repo->load('organization')], 201);
    }

    /**
     * Update repository metadata.
     */
    public function update(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        abort_unless($request->user()->can('update', $repository), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_\-\.]+$/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['nullable', Rule::enum(RepositoryVisibility::class)],
            'default_branch' => ['nullable', 'string', 'max:100'],
            'lfs_enabled' => ['nullable', 'boolean'],
        ]);

        $repository->update($data);

        return response()->json(['data' => $repository->fresh()->load('organization')]);
    }

    /**
     * Delete a repository.
     */
    public function destroy(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);
        abort_unless($request->user()->can('delete', $repository), 403);

        $this->service->delete($repository, $request->user());

        return response()->json(null, 204);
    }
}
