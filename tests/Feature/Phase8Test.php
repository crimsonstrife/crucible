<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Repository;
use App\Models\SparseCheckoutProfile;
use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase8Test extends TestCase
{
    use RefreshDatabase;

    // ── SparseCheckoutProfile Model ─────────────────────────────────────────

    public function test_profile_casts_paths_as_arrays(): void
    {
        $profile = new SparseCheckoutProfile([
            'include_paths' => ['/Content/Textures', '/Content/Materials'],
            'exclude_paths' => ['/Content/Developers'],
            'is_default'    => true,
        ]);

        $this->assertIsArray($profile->include_paths);
        $this->assertIsArray($profile->exclude_paths);
        $this->assertTrue($profile->is_default);
        $this->assertCount(2, $profile->include_paths);
    }

    public function test_to_sparse_checkout_rules_generates_correct_format(): void
    {
        $profile = new SparseCheckoutProfile([
            'name'          => 'Art Only',
            'include_paths' => ['/Content/Textures', '/Content/Materials'],
            'exclude_paths' => ['/Content/Textures/Debug'],
        ]);

        $rules = $profile->toSparseCheckoutRules();

        $this->assertStringContainsString('# Crucible sparse-checkout profile: Art Only', $rules);
        $this->assertStringContainsString('/Content/Textures', $rules);
        $this->assertStringContainsString('/Content/Materials', $rules);
        $this->assertStringContainsString('!/Content/Textures/Debug', $rules);
    }

    public function test_to_sparse_checkout_rules_handles_already_negated_paths(): void
    {
        $profile = new SparseCheckoutProfile([
            'name'          => 'Test',
            'include_paths' => ['/src'],
            'exclude_paths' => ['!/src/test'],
        ]);

        $rules = $profile->toSparseCheckoutRules();

        // Should not double-negate
        $this->assertStringContainsString('!/src/test', $rules);
        $this->assertStringNotContainsString('!!/src/test', $rules);
    }

    public function test_to_clone_commands_generates_valid_git_commands(): void
    {
        $profile = new SparseCheckoutProfile([
            'name'          => 'Code Only',
            'include_paths' => ['/Source', '/Config'],
            'exclude_paths' => [],
        ]);

        $commands = $profile->toCloneCommands('https://crucible.test/git/org/repo', 'main');

        $this->assertCount(2, $commands);
        $this->assertStringContainsString('git clone --filter=blob:none --sparse', $commands[0]);
        $this->assertStringContainsString('--branch main', $commands[0]);
        $this->assertStringContainsString('git sparse-checkout set', $commands[1]);
        $this->assertStringContainsString('/Source', $commands[1]);
        $this->assertStringContainsString('/Config', $commands[1]);
    }

    public function test_matches_path_includes_matching_paths(): void
    {
        $profile = new SparseCheckoutProfile([
            'name'          => 'Art',
            'include_paths' => ['/Content/Textures', '/Content/Materials'],
            'exclude_paths' => ['/Content/Textures/Debug'],
        ]);

        $this->assertTrue($profile->matchesPath('/Content/Textures/T_Hero.png'));
        $this->assertTrue($profile->matchesPath('/Content/Materials/M_Base.uasset'));
        $this->assertFalse($profile->matchesPath('/Source/MyGame.cpp'));
        $this->assertFalse($profile->matchesPath('/Content/Textures/Debug/T_Test.png'));
    }

    public function test_matches_path_handles_paths_without_leading_slash(): void
    {
        $profile = new SparseCheckoutProfile([
            'name'          => 'Test',
            'include_paths' => ['/Content'],
            'exclude_paths' => [],
        ]);

        $this->assertTrue($profile->matchesPath('Content/file.txt'));
        $this->assertTrue($profile->matchesPath('/Content/file.txt'));
    }

    public function test_estimate_coverage_calculates_correct_percentage(): void
    {
        $profile = new SparseCheckoutProfile([
            'name'          => 'Art',
            'include_paths' => ['/Content/Textures'],
            'exclude_paths' => [],
        ]);

        $allPaths = [
            '/Content/Textures/T_Hero.png',
            '/Content/Textures/T_Env.png',
            '/Content/Materials/M_Base.uasset',
            '/Source/MyGame.cpp',
            '/Source/MyGame.h',
        ];

        $coverage = $profile->estimateCoverage($allPaths);

        $this->assertSame(40.0, $coverage); // 2 out of 5
    }

    public function test_estimate_coverage_returns_zero_for_empty_paths(): void
    {
        $profile = new SparseCheckoutProfile([
            'name'          => 'Test',
            'include_paths' => ['/Content'],
            'exclude_paths' => [],
        ]);

        $this->assertSame(0.0, $profile->estimateCoverage([]));
    }

    // ── WorkspaceService Validation ─────────────────────────────────────────

    public function test_validate_accepts_valid_config(): void
    {
        $service = app(WorkspaceService::class);

        $config = [
            'version' => 1,
            'sparse_profiles' => [
                [
                    'name'          => 'Art Only',
                    'include_paths' => ['/Content/Textures'],
                ],
            ],
            'lfs' => [
                'max_object_size_mb' => 2048,
            ],
            'engine' => [
                'type' => 'unreal',
            ],
        ];

        $errors = $service->validate($config);
        $this->assertEmpty($errors);
    }

    public function test_validate_rejects_invalid_sparse_profiles(): void
    {
        $service = app(WorkspaceService::class);

        $config = [
            'sparse_profiles' => [
                [
                    // Missing name
                    'include_paths' => ['/Content'],
                ],
                [
                    'name'          => 'Bad',
                    // Missing include_paths
                ],
            ],
        ];

        $errors = $service->validate($config);
        $this->assertNotEmpty($errors);
        $this->assertCount(2, $errors);
    }

    public function test_validate_rejects_non_array_sparse_profiles(): void
    {
        $service = app(WorkspaceService::class);

        $errors = $service->validate(['sparse_profiles' => 'not an array']);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('must be an array', $errors[0]);
    }

    public function test_validate_rejects_invalid_engine_type(): void
    {
        $service = app(WorkspaceService::class);

        $errors = $service->validate(['engine' => ['type' => 'cryengine']]);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('engine.type', $errors[0]);
    }

    public function test_validate_rejects_non_numeric_max_object_size(): void
    {
        $service = app(WorkspaceService::class);

        $errors = $service->validate(['lfs' => ['max_object_size_mb' => 'huge']]);
        $this->assertCount(1, $errors);
    }

    // ── WorkspaceService Sample Config ──────────────────────────────────────

    public function test_sample_config_for_unreal(): void
    {
        $config = WorkspaceService::sampleConfig('unreal');

        $this->assertSame(1, $config['version']);
        $this->assertSame('unreal', $config['engine']['type']);
        $this->assertNotEmpty($config['sparse_profiles']);

        $names = array_column($config['sparse_profiles'], 'name');
        $this->assertContains('Art Only', $names);
        $this->assertContains('Code Only', $names);
        $this->assertContains('Level Design', $names);
    }

    public function test_sample_config_for_unity(): void
    {
        $config = WorkspaceService::sampleConfig('unity');

        $this->assertSame('unity', $config['engine']['type']);
        $names = array_column($config['sparse_profiles'], 'name');
        $this->assertContains('Art Only', $names);
        $this->assertContains('Code Only', $names);
    }

    public function test_sample_config_for_godot(): void
    {
        $config = WorkspaceService::sampleConfig('godot');

        $this->assertSame('godot', $config['engine']['type']);
        $this->assertNotEmpty($config['sparse_profiles']);
    }

    // ── WorkspaceService Sync ───────────────────────────────────────────────

    public function test_sync_profiles_creates_profiles_from_config(): void
    {
        $org = $this->createOrganization();
        $user = $this->createUser();
        $repo = $this->createRepository($org, $user);

        $service = app(WorkspaceService::class);

        $config = [
            'sparse_profiles' => [
                [
                    'name'          => 'Art Only',
                    'description'   => 'Art assets',
                    'include_paths' => ['/Content/Textures', '/Content/Materials'],
                    'exclude_paths' => [],
                ],
                [
                    'name'          => 'Code Only',
                    'description'   => 'Source code',
                    'include_paths' => ['/Source'],
                    'exclude_paths' => [],
                ],
            ],
            'defaults' => [
                'sparse_profile' => 'Art Only',
            ],
        ];

        $result = $service->syncProfiles($repo, $config);

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(2, $repo->sparseCheckoutProfiles()->count());

        $artProfile = $repo->sparseCheckoutProfiles()->where('name', 'Art Only')->first();
        $this->assertNotNull($artProfile);
        $this->assertTrue($artProfile->is_default);
        $this->assertCount(2, $artProfile->include_paths);
    }

    public function test_sync_profiles_updates_existing(): void
    {
        $org = $this->createOrganization();
        $user = $this->createUser();
        $repo = $this->createRepository($org, $user);

        // Create an initial profile
        $repo->sparseCheckoutProfiles()->create([
            'name'          => 'Art Only',
            'include_paths' => ['/Content/Textures'],
            'exclude_paths' => [],
        ]);

        $service = app(WorkspaceService::class);

        $config = [
            'sparse_profiles' => [
                [
                    'name'          => 'Art Only',
                    'description'   => 'Updated art assets',
                    'include_paths' => ['/Content/Textures', '/Content/VFX'],
                    'exclude_paths' => ['/Content/Textures/Debug'],
                ],
            ],
        ];

        $result = $service->syncProfiles($repo, $config);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);

        $artProfile = $repo->sparseCheckoutProfiles()->where('name', 'Art Only')->first();
        $this->assertCount(2, $artProfile->include_paths);
        $this->assertCount(1, $artProfile->exclude_paths);
    }

    // ── Repository Relationship ─────────────────────────────────────────────

    public function test_repository_has_sparse_checkout_profiles(): void
    {
        $repo = new Repository;
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $repo->sparseCheckoutProfiles());
    }

    // ── Profile Persistence ─────────────────────────────────────────────────

    public function test_profile_persists_and_retrieves_correctly(): void
    {
        $org = $this->createOrganization();
        $user = $this->createUser();
        $repo = $this->createRepository($org, $user);

        $profile = $repo->sparseCheckoutProfiles()->create([
            'name'          => 'Level Design',
            'description'   => 'Maps and blueprints',
            'include_paths' => ['/Content/Maps', '/Content/Blueprints'],
            'exclude_paths' => ['/Content/Maps/Test'],
            'is_default'    => false,
            'sort_order'    => 2,
        ]);

        $loaded = SparseCheckoutProfile::find($profile->id);

        $this->assertNotNull($loaded);
        $this->assertSame('Level Design', $loaded->name);
        $this->assertSame(2, $loaded->sort_order);
        $this->assertCount(2, $loaded->include_paths);
        $this->assertCount(1, $loaded->exclude_paths);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function createOrganization(array $extra = []): Organization
    {
        return Organization::create(array_merge([
            'id'   => (string) Str::uuid(),
            'name' => 'Test Org',
            'slug' => 'test-org-' . Str::random(6),
        ], $extra));
    }

    private function createUser(): User
    {
        return User::create([
            'id'       => (string) Str::uuid(),
            'name'     => 'Test User',
            'email'    => 'user-' . Str::random(6) . '@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    private function createRepository(Organization $org, User $user, array $extra = []): Repository
    {
        return Repository::create(array_merge([
            'id'              => (string) Str::uuid(),
            'organization_id' => $org->id,
            'owner_id'        => $user->id,
            'name'            => 'Test Repo',
            'slug'            => 'test-repo-' . Str::random(6),
        ], $extra));
    }
}
