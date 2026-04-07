<?php

namespace Tests\Feature;

use App\Contracts\RepositoryDriverInterface;
use App\Drivers\NativeGitDriver;
use App\Drivers\StubRepositoryDriver;
use App\Enums\RepositoryVisibility;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use App\Services\NativeGitRepositoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GitHttpControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $reposPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reposPath = storage_path('framework/testing/git-http-'.Str::uuid().'/repositories');
        config()->set('crucible.git.repos_path', $this->reposPath);
    }

    protected function tearDown(): void
    {
        app('files')->deleteDirectory(dirname($this->reposPath));

        parent::tearDown();
    }

    public function test_info_refs_returns_not_implemented_when_native_backend_is_inactive(): void
    {
        $repository = $this->createRepository();

        $this->app->bind(RepositoryDriverInterface::class, StubRepositoryDriver::class);

        $response = $this->get(sprintf(
            '/%s/%s.git/info/refs?service=git-upload-pack',
            $repository->organization->slug,
            $repository->slug,
        ));

        $response->assertStatus(501);
        $response->assertSeeText('native git backend is active');
    }

    public function test_info_refs_serves_advertisement_when_native_backend_is_active(): void
    {
        $repository = $this->createRepository(defaultBranch: 'develop');

        $this->app->bind(RepositoryDriverInterface::class, NativeGitDriver::class);

        /** @var NativeGitRepositoryService $nativeGit */
        $nativeGit = app(NativeGitRepositoryService::class);
        $nativeGit->initialize($repository);

        $response = $this->get(sprintf(
            '/%s/%s.git/info/refs?service=git-upload-pack',
            $repository->organization->slug,
            $repository->slug,
        ));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/x-git-upload-pack-advertisement');
        $response->assertSee('# service=git-upload-pack', false);
    }

    protected function createRepository(string $defaultBranch = 'main'): Repository
    {
        $owner = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Studio '.Str::random(6),
            'slug' => 'studio-'.Str::lower(Str::random(8)),
        ]);

        $organization->members()->attach($owner, ['role' => 'owner']);

        return $organization->repositories()->create([
            'owner_id' => $owner->id,
            'name' => 'Repo '.Str::random(6),
            'slug' => 'repo-'.Str::lower(Str::random(8)),
            'vcs_type' => 'git',
            'visibility' => RepositoryVisibility::Public,
            'default_branch' => $defaultBranch,
            'lfs_enabled' => false,
        ]);
    }
}
