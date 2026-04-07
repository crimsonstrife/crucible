<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EngineType;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Repository;
use App\Services\GameEngineService;
use App\Services\NativeGitRepositoryService;
use App\Support\GameEngineTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameEngineApiController extends Controller
{
    public function __construct(
        protected GameEngineService $engineService,
        protected NativeGitRepositoryService $git,
    ) {}

    /**
     * Detect the game engine used by a repository.
     *
     * POST /{org}/{repo}/engine/detect
     */
    public function detect(Organization $organization, Repository $repository): JsonResponse
    {
        $engine = $this->engineService->detectAndSave($repository);

        if (! $engine) {
            return response()->json([
                'detected' => false,
                'message'  => 'No game engine detected in this repository.',
            ]);
        }

        return response()->json([
            'detected'    => true,
            'engine_type' => $engine->value,
            'engine_label' => $engine->label(),
        ]);
    }

    /**
     * Get the current engine type for a repository.
     *
     * GET /{org}/{repo}/engine
     */
    public function show(Organization $organization, Repository $repository): JsonResponse
    {
        return response()->json([
            'engine_type'  => $repository->engine_type?->value,
            'engine_label' => $repository->engine_type?->label(),
        ]);
    }

    /**
     * Get recommended LFS and lock policies for a given engine type.
     *
     * GET /{org}/{repo}/engine/recommend?engine=unreal
     */
    public function recommend(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        $engineValue = $request->input('engine', $repository->engine_type?->value);

        if (! $engineValue) {
            return response()->json([
                'error' => 'No engine type specified. Pass ?engine=unreal|unity|godot or detect first.',
            ], 422);
        }

        $engine = EngineType::tryFrom($engineValue);

        if (! $engine) {
            return response()->json([
                'error' => "Unknown engine type: {$engineValue}. Valid types: unreal, unity, godot.",
            ], 422);
        }

        $recommendations = $this->engineService->recommendedPolicies($engine);

        return response()->json(['data' => $recommendations]);
    }

    /**
     * Apply recommended LFS and lock policies for a detected or specified engine.
     *
     * POST /{org}/{repo}/engine/apply
     */
    public function apply(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        $engineValue = $request->input('engine', $repository->engine_type?->value);

        if (! $engineValue) {
            return response()->json([
                'error' => 'No engine type specified. Pass engine=unreal|unity|godot or detect first.',
            ], 422);
        }

        $engine = EngineType::tryFrom($engineValue);

        if (! $engine) {
            return response()->json([
                'error' => "Unknown engine type: {$engineValue}.",
            ], 422);
        }

        $result = $this->engineService->applyRecommendedPolicies($repository, $engine);

        return response()->json([
            'message'    => "Applied {$engine->label()} policies.",
            'lfs_count'  => $result['lfs_count'],
            'lock_count' => $result['lock_count'],
        ]);
    }

    /**
     * Generate and commit a .gitattributes file based on the repository's LFS policies.
     *
     * POST /{org}/{repo}/generate-gitattributes
     */
    public function generateGitattributes(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        // Use engine template if available, otherwise use repository's LFS policies
        $engineValue = $request->input('engine', $repository->engine_type?->value);
        $entries = [];

        if ($engineValue) {
            $engine = EngineType::tryFrom($engineValue);
            if ($engine) {
                $entries = GameEngineTemplates::get($engine->templateName()) ?? [];
            }
        }

        // If no engine template, build from existing LFS policies
        if (empty($entries)) {
            $policies = $repository->lfsPolicies()->get();

            if ($policies->isEmpty()) {
                return response()->json([
                    'error' => 'No LFS policies or engine type configured. Apply a template first.',
                ], 422);
            }

            $entries = $policies->map(fn ($p) => [
                'pattern'     => $p->pattern,
                'description' => $p->description,
                'lockable'    => false,
            ])->all();
        }

        $gitattributesContent = GameEngineTemplates::toGitattributes($entries);

        // Determine the branch to commit to
        $branch = $request->input('branch', $repository->default_branch ?? 'main');

        // Verify the branch exists
        if (! $this->git->hasRevision($repository, $branch)) {
            return response()->json([
                'error' => "Branch '{$branch}' does not exist or repository is empty.",
            ], 422);
        }

        $user = $request->user();
        $authorName = $user->name ?? 'Crucible';
        $authorEmail = $user->email ?? 'crucible@localhost';

        try {
            $sha = $this->git->commitFile(
                repository: $repository,
                branch: $branch,
                filePath: '.gitattributes',
                contents: $gitattributesContent,
                commitMessage: 'chore: generate .gitattributes for LFS tracking',
                authorName: $authorName,
                authorEmail: $authorEmail,
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'error'   => 'Failed to commit .gitattributes.',
                'details' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => '.gitattributes committed successfully.',
            'sha'     => $sha,
            'branch'  => $branch,
            'content' => $gitattributesContent,
        ], 201);
    }
}
