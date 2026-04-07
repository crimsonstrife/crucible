<?php

namespace Tests\Feature;

use App\Contracts\RepositoryDriverInterface;
use App\Drivers\NativeGitDriver;
use App\Jobs\InitializeRepositoryJob;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InitializeRepositoryJobTest extends TestCase
{
    use RefreshDatabase;

    protected string $reposPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reposPath = storage_path('framework/testing/initialize-job-'.Str::uuid().'/repositories');
        config()->set('crucible.git.repos_path', $this->reposPath);
        $this->app->bind(RepositoryDriverInterface::class, NativeGitDriver::class);
    }

    protected function tearDown(): void
    {
        app('files')->deleteDirectory(dirname($this->reposPath));

        parent::tearDown();
    }

    public function test_initialize_repository_job_persists_native_git_metadata(): void
    {
        $owner = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Studio '.Str::random(6),
            'slug' => 'studio-'.Str::lower(Str::random(8)),
        ]);

        $organization->members()->attach($owner, ['role' => 'owner']);

        $repository = $organization->repositories()->create([
            'owner_id' => $owner->id,
            'name' => 'Repo '.Str::random(6),
            'slug' => 'repo-'.Str::lower(Str::random(8)),
            'vcs_type' => 'git',
            'visibility' => 'private',
            'default_branch' => 'develop',
            'lfs_enabled' => false,
            'size_kb' => 0,
        ]);

        $job = new InitializeRepositoryJob($repository);
        $job->handle(app(RepositoryDriverInterface::class));

        $repository->refresh();

        $this->assertSame('develop', $repository->default_branch);
        $this->assertGreaterThan(0, $repository->size_kb);
    }
}
