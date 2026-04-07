<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\SparseCheckoutProfile;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SparseCheckoutApiController extends Controller
{
    /**
     * List all sparse checkout profiles for a repository.
     */
    public function index(Organization $organization, Repository $repository): JsonResponse
    {
        $profiles = $repository->sparseCheckoutProfiles()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (SparseCheckoutProfile $p) => $this->profileToArray($p));

        return response()->json(['data' => $profiles]);
    }

    /**
     * Show a single profile with its sparse-checkout rules and clone commands.
     */
    public function show(Organization $organization, Repository $repository, SparseCheckoutProfile $profile): JsonResponse
    {
        abort_unless($profile->repository_id === $repository->id, 404);

        $data = $this->profileToArray($profile);
        $data['sparse_checkout_rules'] = $profile->toSparseCheckoutRules();
        $data['clone_commands'] = $profile->toCloneCommands(
            url('/git/' . $organization->slug . '/' . $repository->slug),
            $repository->default_branch,
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Create a new sparse checkout profile.
     */
    public function store(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:100'],
            'description'     => ['nullable', 'string', 'max:500'],
            'include_paths'   => ['required', 'array', 'min:1'],
            'include_paths.*' => ['required', 'string', 'max:500'],
            'exclude_paths'   => ['nullable', 'array'],
            'exclude_paths.*' => ['string', 'max:500'],
            'is_default'      => ['boolean'],
            'sort_order'      => ['integer', 'min:0'],
        ]);

        // If setting as default, unset other defaults
        if ($validated['is_default'] ?? false) {
            $repository->sparseCheckoutProfiles()
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $profile = $repository->sparseCheckoutProfiles()->create([
            'name'          => $validated['name'],
            'description'   => $validated['description'] ?? null,
            'include_paths' => $validated['include_paths'],
            'exclude_paths' => $validated['exclude_paths'] ?? [],
            'is_default'    => $validated['is_default'] ?? false,
            'sort_order'    => $validated['sort_order'] ?? 0,
        ]);

        return response()->json(['data' => $this->profileToArray($profile)], 201);
    }

    /**
     * Update an existing profile.
     */
    public function update(Request $request, Organization $organization, Repository $repository, SparseCheckoutProfile $profile): JsonResponse
    {
        abort_unless($profile->repository_id === $repository->id, 404);

        $validated = $request->validate([
            'name'            => ['sometimes', 'string', 'max:100'],
            'description'     => ['nullable', 'string', 'max:500'],
            'include_paths'   => ['sometimes', 'array', 'min:1'],
            'include_paths.*' => ['required', 'string', 'max:500'],
            'exclude_paths'   => ['nullable', 'array'],
            'exclude_paths.*' => ['string', 'max:500'],
            'is_default'      => ['boolean'],
            'sort_order'      => ['integer', 'min:0'],
        ]);

        if ($validated['is_default'] ?? false) {
            $repository->sparseCheckoutProfiles()
                ->where('is_default', true)
                ->where('id', '!=', $profile->id)
                ->update(['is_default' => false]);
        }

        $profile->update($validated);

        return response()->json(['data' => $this->profileToArray($profile)]);
    }

    /**
     * Delete a profile.
     */
    public function destroy(Organization $organization, Repository $repository, SparseCheckoutProfile $profile): JsonResponse
    {
        abort_unless($profile->repository_id === $repository->id, 404);

        $profile->delete();

        return response()->json(null, 204);
    }

    /**
     * Sync profiles from the repository's .crucible/workspace.json.
     */
    public function sync(Organization $organization, Repository $repository, WorkspaceService $workspaceService): JsonResponse
    {
        $config = $workspaceService->readConfig($repository);

        if ($config === null) {
            return response()->json([
                'error' => 'No .crucible/workspace.json found in this repository.',
            ], 404);
        }

        $errors = $workspaceService->validate($config);

        if (! empty($errors)) {
            return response()->json([
                'error'      => 'Invalid workspace.json configuration.',
                'violations' => $errors,
            ], 422);
        }

        $result = $workspaceService->syncProfiles($repository, $config);

        return response()->json([
            'message' => "Synced profiles: {$result['created']} created, {$result['updated']} updated.",
            'created' => $result['created'],
            'updated' => $result['updated'],
        ]);
    }

    /**
     * Get a sample workspace.json for a given engine type.
     */
    public function sampleConfig(Request $request): JsonResponse
    {
        $engine = $request->input('engine', 'unreal');

        return response()->json([
            'data' => WorkspaceService::sampleConfig($engine),
        ]);
    }

    /**
     * Read and validate the workspace config from the repository.
     */
    public function workspaceConfig(Organization $organization, Repository $repository, WorkspaceService $workspaceService): JsonResponse
    {
        $config = $workspaceService->readConfig($repository);

        if ($config === null) {
            return response()->json([
                'found'  => false,
                'config' => null,
            ]);
        }

        $errors = $workspaceService->validate($config);

        return response()->json([
            'found'      => true,
            'valid'      => empty($errors),
            'config'     => $config,
            'violations' => $errors,
        ]);
    }

    private function profileToArray(SparseCheckoutProfile $profile): array
    {
        return [
            'id'            => $profile->id,
            'name'          => $profile->name,
            'slug'          => $profile->slug,
            'description'   => $profile->description,
            'include_paths' => $profile->include_paths,
            'exclude_paths' => $profile->exclude_paths,
            'is_default'    => $profile->is_default,
            'sort_order'    => $profile->sort_order,
            'created_at'    => $profile->created_at,
            'updated_at'    => $profile->updated_at,
        ];
    }
}
