<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\RepositoryLfsPolicy;
use App\Services\LfsService;
use App\Support\GameEngineTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LfsPolicyApiController extends Controller
{
    /**
     * GET /{org}/{repo}/lfs-policies
     */
    public function index(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        $this->authorize('view', $repository);

        return response()->json([
            'data' => $repository->lfsPolicies()->orderBy('pattern')->get(),
        ]);
    }

    /**
     * POST /{org}/{repo}/lfs-policies
     */
    public function store(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        $this->authorize('update', $repository);

        $validated = $request->validate([
            'pattern'        => ['required', 'string', 'max:255'],
            'min_size_bytes' => ['nullable', 'integer', 'min:0'],
            'description'    => ['nullable', 'string', 'max:255'],
        ]);

        $policy = $repository->lfsPolicies()->create($validated);

        return response()->json(['data' => $policy], 201);
    }

    /**
     * DELETE /{org}/{repo}/lfs-policies/{policy}
     */
    public function destroy(Request $request, Organization $organization, Repository $repository, RepositoryLfsPolicy $policy): JsonResponse
    {
        $this->authorize('update', $repository);

        abort_unless($policy->repository_id === $repository->id, 404);

        $policy->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /{org}/{repo}/lfs-policies/apply-template
     *
     * Applies a named engine template (unreal, unity, godot, general),
     * creating LFS policies for all patterns in the template.
     */
    public function applyTemplate(Request $request, Organization $organization, Repository $repository, LfsService $lfsService): JsonResponse
    {
        $this->authorize('update', $repository);

        $validated = $request->validate([
            'template' => ['required', 'string', 'in:'.implode(',', GameEngineTemplates::available())],
        ]);

        $result = $lfsService->applyTemplate($repository, $validated['template']);

        return response()->json([
            'message'  => count($result['created']).' LFS policies created from "'.$validated['template'].'" template.',
            'created'  => count($result['created']),
            'skipped'  => $result['skipped'],
            'data'     => $result['created'],
        ], 201);
    }

    /**
     * GET /{org}/{repo}/lfs-policies/templates
     *
     * List available engine templates and their patterns.
     */
    public function templates(): JsonResponse
    {
        $templates = [];

        foreach (GameEngineTemplates::available() as $name) {
            $entries = GameEngineTemplates::get($name);
            $templates[] = [
                'name'          => $name,
                'pattern_count' => count($entries),
                'patterns'      => collect($entries)->pluck('pattern')->all(),
            ];
        }

        return response()->json(['data' => $templates]);
    }

    /**
     * GET /{org}/{repo}/lfs-policies/gitattributes
     *
     * Generate .gitattributes content from the repository's LFS policies.
     */
    public function gitattributes(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        $this->authorize('view', $repository);

        $lfsService = app(\App\Services\LfsService::class);
        $content = $lfsService->generateGitattributes($repository);

        return response()->json([
            'content' => $content,
        ]);
    }
}
