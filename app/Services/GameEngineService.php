<?php

namespace App\Services;

use App\Enums\EngineType;
use App\Enums\LockMode;
use App\Models\Repository;
use App\Support\GameEngineTemplates;
use Illuminate\Support\Facades\Log;

/**
 * Detects game engine type from repository contents and recommends
 * appropriate LFS + lock policies.
 */
class GameEngineService
{
    public function __construct(
        protected NativeGitRepositoryService $git,
    ) {}

    /**
     * Detect the game engine used by a repository by scanning its file tree.
     *
     * Looks for engine-specific marker files:
     *   - Unreal: *.uproject
     *   - Unity: ProjectSettings/ProjectVersion.txt or Assets/ directory
     *   - Godot: project.godot
     *
     * Returns null if no engine is detected.
     */
    public function detectEngine(Repository $repository): ?EngineType
    {
        $ref = $repository->default_branch ?? 'main';

        if (! $this->git->hasRevision($repository, $ref)) {
            return null;
        }

        // Get a flat listing of top-level files and directories
        $topLevel = $this->listTopLevel($repository, $ref);

        // Check for Unreal: look for .uproject files
        foreach ($topLevel as $entry) {
            if (str_ends_with(strtolower($entry), '.uproject')) {
                return EngineType::Unreal;
            }
        }

        // Check for Unity: ProjectSettings/ directory or Assets/ directory
        if (in_array('ProjectSettings', $topLevel, true) || in_array('Assets', $topLevel, true)) {
            // Confirm by checking for ProjectVersion.txt
            $versionFile = $this->git->readFile($repository, $ref, 'ProjectSettings/ProjectVersion.txt');
            if ($versionFile !== null) {
                return EngineType::Unity;
            }
            // Also accept Assets/ + Library/ pattern
            if (in_array('Assets', $topLevel, true)) {
                return EngineType::Unity;
            }
        }

        // Check for Godot: project.godot file
        if (in_array('project.godot', $topLevel, true)) {
            return EngineType::Godot;
        }

        // Deep scan: look for engine files anywhere in the tree
        return $this->deepScan($repository, $ref);
    }

    /**
     * Detect engine and update the repository model.
     */
    public function detectAndSave(Repository $repository): ?EngineType
    {
        $engine = $this->detectEngine($repository);

        $repository->update(['engine_type' => $engine]);

        if ($engine) {
            Log::info('[GameEngineService] Detected engine', [
                'repository' => $repository->id,
                'engine'     => $engine->value,
            ]);
        }

        return $engine;
    }

    /**
     * Get recommended LFS and lock policies for a detected engine.
     *
     * @return array{lfs: array, locks: array, gitattributes: string}
     */
    public function recommendedPolicies(EngineType $engine): array
    {
        $templateName = $engine->templateName();
        $entries = GameEngineTemplates::get($templateName) ?? [];

        $lfsPolicies = [];
        $lockPolicies = [];

        foreach ($entries as $entry) {
            $lfsPolicies[] = [
                'pattern'     => $entry['pattern'],
                'description' => $entry['description'],
            ];

            if ($entry['lockable']) {
                $lockPolicies[] = [
                    'pattern'   => $entry['pattern'],
                    'lock_mode' => LockMode::Mandatory->value,
                    'auto_lock' => true,
                ];
            }
        }

        return [
            'engine'        => $engine->value,
            'engine_label'  => $engine->label(),
            'lfs'           => $lfsPolicies,
            'locks'         => $lockPolicies,
            'gitattributes' => GameEngineTemplates::toGitattributes($entries),
        ];
    }

    /**
     * Apply recommended policies to a repository.
     * Creates LFS policies and lock policies based on the engine template.
     *
     * @return array{lfs_count: int, lock_count: int}
     */
    public function applyRecommendedPolicies(Repository $repository, EngineType $engine): array
    {
        $recommendations = $this->recommendedPolicies($engine);
        $lfsCount = 0;
        $lockCount = 0;

        // Create LFS policies (skip duplicates)
        foreach ($recommendations['lfs'] as $lfs) {
            $exists = $repository->lfsPolicies()
                ->where('pattern', $lfs['pattern'])
                ->exists();

            if (! $exists) {
                $repository->lfsPolicies()->create([
                    'pattern'     => $lfs['pattern'],
                    'description' => $lfs['description'],
                ]);
                $lfsCount++;
            }
        }

        // Create lock policies (skip duplicates)
        foreach ($recommendations['locks'] as $lock) {
            $exists = $repository->lockPolicies()
                ->where('pattern', $lock['pattern'])
                ->exists();

            if (! $exists) {
                $repository->lockPolicies()->create([
                    'pattern'   => $lock['pattern'],
                    'lock_mode' => $lock['lock_mode'],
                    'auto_lock' => $lock['auto_lock'],
                ]);
                $lockCount++;
            }
        }

        // Update engine type
        $repository->update(['engine_type' => $engine]);

        return [
            'lfs_count'  => $lfsCount,
            'lock_count' => $lockCount,
        ];
    }

    /**
     * List top-level entries in the repository tree.
     *
     * @return string[]
     */
    private function listTopLevel(Repository $repository, string $ref): array
    {
        $repoPath = $this->git->pathFor($repository);

        try {
            $process = new \Symfony\Component\Process\Process([
                'git', '--git-dir', $repoPath,
                'ls-tree', '--name-only', $ref,
            ]);
            $process->setTimeout(15);
            $process->run();

            if ($process->isSuccessful()) {
                return array_filter(explode("\n", trim($process->getOutput())));
            }
        } catch (\Throwable) {
            // Empty repo or invalid ref
        }

        return [];
    }

    /**
     * Deep scan: search the full tree for engine markers.
     */
    private function deepScan(Repository $repository, string $ref): ?EngineType
    {
        $repoPath = $this->git->pathFor($repository);

        try {
            $process = new \Symfony\Component\Process\Process([
                'git', '--git-dir', $repoPath,
                'ls-tree', '-r', '--name-only', $ref,
            ]);
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $files = explode("\n", trim($process->getOutput()));

            foreach ($files as $file) {
                $lower = strtolower($file);

                if (str_ends_with($lower, '.uproject')) {
                    return EngineType::Unreal;
                }

                if ($lower === 'project.godot' || str_ends_with($lower, '/project.godot')) {
                    return EngineType::Godot;
                }
            }

            // Check Unity markers in the full list
            $hasAssets = false;
            $hasProjectSettings = false;

            foreach ($files as $file) {
                if (str_starts_with($file, 'Assets/')) {
                    $hasAssets = true;
                }
                if (str_starts_with($file, 'ProjectSettings/')) {
                    $hasProjectSettings = true;
                }
                if ($hasAssets && $hasProjectSettings) {
                    return EngineType::Unity;
                }
            }
        } catch (\Throwable) {
            // Unable to scan
        }

        return null;
    }
}
