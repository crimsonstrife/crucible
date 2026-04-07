<?php

namespace App\Services;

use App\Models\Repository;
use App\Models\SparseCheckoutProfile;
use Illuminate\Support\Facades\Log;

/**
 * Reads and interprets .crucible/workspace.json from a repository's tree.
 *
 * The workspace config can define:
 *   - sparse_profiles: predefined checkout profiles
 *   - lfs: recommended LFS settings
 *   - engine: engine-specific configuration
 *   - defaults: default profile name, branch, etc.
 */
class WorkspaceService
{
    private const CONFIG_PATH = '.crucible/workspace.json';

    public function __construct(
        protected NativeGitRepositoryService $git,
    ) {}

    /**
     * Read and parse the workspace config from the repository.
     *
     * @return array|null  Parsed config, or null if not found / invalid
     */
    public function readConfig(Repository $repository, ?string $ref = null): ?array
    {
        $ref ??= $repository->default_branch ?? 'main';

        $contents = $this->git->readFile($repository, $ref, self::CONFIG_PATH);

        if ($contents === null) {
            return null;
        }

        $config = json_decode($contents, true);

        if (! is_array($config)) {
            Log::warning('[WorkspaceService] Invalid workspace.json — not a valid JSON object', [
                'repository' => $repository->id,
            ]);

            return null;
        }

        return $config;
    }

    /**
     * Validate the workspace config structure.
     *
     * Returns an array of validation error messages, empty if valid.
     */
    public function validate(array $config): array
    {
        $errors = [];

        // Validate sparse_profiles
        if (isset($config['sparse_profiles'])) {
            if (! is_array($config['sparse_profiles'])) {
                $errors[] = '"sparse_profiles" must be an array.';
            } else {
                foreach ($config['sparse_profiles'] as $index => $profile) {
                    if (! is_array($profile)) {
                        $errors[] = "sparse_profiles[{$index}] must be an object.";

                        continue;
                    }

                    if (empty($profile['name'])) {
                        $errors[] = "sparse_profiles[{$index}].name is required.";
                    }

                    if (empty($profile['include_paths']) || ! is_array($profile['include_paths'])) {
                        $errors[] = "sparse_profiles[{$index}].include_paths must be a non-empty array.";
                    }

                    if (isset($profile['exclude_paths']) && ! is_array($profile['exclude_paths'])) {
                        $errors[] = "sparse_profiles[{$index}].exclude_paths must be an array.";
                    }
                }
            }
        }

        // Validate lfs section
        if (isset($config['lfs'])) {
            if (! is_array($config['lfs'])) {
                $errors[] = '"lfs" must be an object.';
            } else {
                if (isset($config['lfs']['max_object_size_mb']) && ! is_numeric($config['lfs']['max_object_size_mb'])) {
                    $errors[] = '"lfs.max_object_size_mb" must be a number.';
                }
            }
        }

        // Validate engine section
        if (isset($config['engine'])) {
            if (! is_array($config['engine'])) {
                $errors[] = '"engine" must be an object.';
            } else {
                if (isset($config['engine']['type'])) {
                    $valid = ['unreal', 'unity', 'godot'];
                    if (! in_array($config['engine']['type'], $valid, true)) {
                        $errors[] = '"engine.type" must be one of: ' . implode(', ', $valid);
                    }
                }
            }
        }

        // Validate defaults
        if (isset($config['defaults'])) {
            if (! is_array($config['defaults'])) {
                $errors[] = '"defaults" must be an object.';
            }
        }

        return $errors;
    }

    /**
     * Sync sparse checkout profiles from workspace config into the database.
     *
     * Creates/updates profiles that exist in the config, preserving any
     * manually-created profiles not in the config.
     *
     * @return array{created: int, updated: int}
     */
    public function syncProfiles(Repository $repository, array $config): array
    {
        $profiles = $config['sparse_profiles'] ?? [];
        $created = 0;
        $updated = 0;

        $defaultProfile = $config['defaults']['sparse_profile'] ?? null;

        foreach ($profiles as $index => $profileData) {
            $name = $profileData['name'] ?? "Profile {$index}";

            $profile = $repository->sparseCheckoutProfiles()
                ->where('name', $name)
                ->first();

            $attributes = [
                'name'          => $name,
                'description'   => $profileData['description'] ?? null,
                'include_paths' => $profileData['include_paths'] ?? [],
                'exclude_paths' => $profileData['exclude_paths'] ?? [],
                'is_default'    => $defaultProfile === $name,
                'sort_order'    => $index,
            ];

            if ($profile) {
                $profile->update($attributes);
                $updated++;
            } else {
                $repository->sparseCheckoutProfiles()->create($attributes);
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Generate a sample workspace.json config.
     */
    public static function sampleConfig(string $engineType = 'unreal'): array
    {
        $profiles = match ($engineType) {
            'unreal' => [
                [
                    'name'          => 'Art Only',
                    'description'   => 'Textures, materials, meshes, and VFX — no C++ source',
                    'include_paths' => ['/Content/Textures', '/Content/Materials', '/Content/Meshes', '/Content/VFX', '/Content/UI'],
                    'exclude_paths' => ['/Content/Developers'],
                ],
                [
                    'name'          => 'Code Only',
                    'description'   => 'C++ source, configs, and build files — no large assets',
                    'include_paths' => ['/Source', '/Config', '/Plugins'],
                    'exclude_paths' => [],
                ],
                [
                    'name'          => 'Level Design',
                    'description'   => 'Maps, blueprints, and supporting assets',
                    'include_paths' => ['/Content/Maps', '/Content/Blueprints', '/Content/Meshes', '/Content/Materials'],
                    'exclude_paths' => [],
                ],
                [
                    'name'          => 'Audio',
                    'description'   => 'Sound effects, music, and audio blueprints',
                    'include_paths' => ['/Content/Audio', '/Content/Sound'],
                    'exclude_paths' => [],
                ],
            ],
            'unity' => [
                [
                    'name'          => 'Art Only',
                    'description'   => 'Textures, models, materials, and animations',
                    'include_paths' => ['/Assets/Art', '/Assets/Textures', '/Assets/Models', '/Assets/Materials', '/Assets/Animations'],
                    'exclude_paths' => [],
                ],
                [
                    'name'          => 'Code Only',
                    'description'   => 'Scripts, editor tools, and configuration',
                    'include_paths' => ['/Assets/Scripts', '/Assets/Editor', '/Assets/Plugins', '/ProjectSettings'],
                    'exclude_paths' => [],
                ],
                [
                    'name'          => 'Level Design',
                    'description'   => 'Scenes, prefabs, and level assets',
                    'include_paths' => ['/Assets/Scenes', '/Assets/Prefabs', '/Assets/Art'],
                    'exclude_paths' => [],
                ],
            ],
            'godot' => [
                [
                    'name'          => 'Art Only',
                    'description'   => 'Sprites, models, and visual assets',
                    'include_paths' => ['/assets/sprites', '/assets/models', '/assets/textures'],
                    'exclude_paths' => [],
                ],
                [
                    'name'          => 'Code Only',
                    'description'   => 'GDScript, C# scripts, and addons',
                    'include_paths' => ['/scripts', '/addons', '/autoload'],
                    'exclude_paths' => [],
                ],
            ],
            default => [
                [
                    'name'          => 'Full',
                    'description'   => 'Everything in the repository',
                    'include_paths' => ['/'],
                    'exclude_paths' => [],
                ],
            ],
        };

        return [
            'version' => 1,
            'engine'  => [
                'type' => $engineType,
            ],
            'sparse_profiles' => $profiles,
            'lfs' => [
                'max_object_size_mb' => 2048,
            ],
            'defaults' => [
                'sparse_profile' => $profiles[0]['name'] ?? null,
                'branch'         => 'main',
            ],
        ];
    }
}
