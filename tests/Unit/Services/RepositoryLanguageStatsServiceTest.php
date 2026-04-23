<?php

namespace Tests\Unit\Services;

use App\Jobs\ComputeRepositoryLanguageStatsJob;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use App\Services\NativeGitRepositoryService;
use App\Services\RepositoryLanguageStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RepositoryLanguageStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected string $reposPath;

    protected string $workspacePath;

    protected NativeGitRepositoryService $git;

    protected function setUp(): void
    {
        parent::setUp();

        $root = storage_path('framework/testing/language-stats-'.Str::uuid());
        $this->reposPath = $root.'/repositories';
        $this->workspacePath = $root.'/workspaces';
        config()->set('crucible.git.repos_path', $this->reposPath);

        $this->git = app(NativeGitRepositoryService::class);
    }

    protected function tearDown(): void
    {
        app('files')->deleteDirectory(dirname($this->reposPath));

        parent::tearDown();
    }

    public function test_recompute_aggregates_bytes_per_language_and_excludes_vendored_paths(): void
    {
        $repository = $this->seedRepositoryWithContent();

        app(RepositoryLanguageStatsService::class)->recompute($repository);

        $repository->refresh();

        $stats = $repository->language_stats;
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('PHP', $stats);
        $this->assertArrayHasKey('JavaScript', $stats);
        $this->assertGreaterThan(0, $stats['PHP']);
        $this->assertGreaterThan(0, $stats['JavaScript']);
        // README.md counts as Markdown
        $this->assertArrayHasKey('Markdown', $stats);
        // node_modules/lib.js should have been excluded
        $jsBytes = $stats['JavaScript'];
        $this->assertLessThan(10_000, $jsBytes, 'node_modules content should not be counted');

        $this->assertNotNull($repository->language_stats_head_sha);
        $this->assertNotNull($repository->language_stats_updated_at);
    }

    public function test_snapshot_returns_ready_when_cache_matches_head(): void
    {
        Queue::fake();

        $repository = $this->seedRepositoryWithContent();

        app(RepositoryLanguageStatsService::class)->recompute($repository);
        $repository->refresh();

        $snapshot = app(RepositoryLanguageStatsService::class)->snapshot($repository);

        $this->assertSame('ready', $snapshot['status']);
        $this->assertNotEmpty($snapshot['segments']);
        $this->assertIsFloat($snapshot['segments'][0]['percent']);

        $percentSum = array_sum(array_column($snapshot['segments'], 'percent'));
        $this->assertEqualsWithDelta(100.0, $percentSum, 0.01);

        Queue::assertNotPushed(ComputeRepositoryLanguageStatsJob::class);
    }

    public function test_snapshot_returns_pending_and_dispatches_when_no_cache(): void
    {
        Queue::fake();

        $repository = $this->seedRepositoryWithContent();

        $snapshot = app(RepositoryLanguageStatsService::class)->snapshot($repository);

        $this->assertSame('pending', $snapshot['status']);
        $this->assertSame([], $snapshot['segments']);

        Queue::assertPushed(ComputeRepositoryLanguageStatsJob::class);
    }

    public function test_snapshot_returns_stale_and_dispatches_when_head_has_moved(): void
    {
        Queue::fake();

        $repository = $this->seedRepositoryWithContent();

        $repository->forceFill([
            'language_stats' => ['PHP' => 100, 'JavaScript' => 50],
            'language_stats_head_sha' => str_repeat('0', 40),
            'language_stats_updated_at' => now()->subHour(),
        ])->saveWithoutTouch();

        $snapshot = app(RepositoryLanguageStatsService::class)->snapshot($repository);

        $this->assertSame('stale', $snapshot['status']);
        $this->assertNotEmpty($snapshot['segments']);

        Queue::assertPushed(ComputeRepositoryLanguageStatsJob::class);
    }

    public function test_snapshot_returns_empty_when_repo_is_not_initialized(): void
    {
        $repository = $this->makeRepository($this->makeOrganization());

        $snapshot = app(RepositoryLanguageStatsService::class)->snapshot($repository);

        $this->assertSame('empty', $snapshot['status']);
    }

    protected function seedRepositoryWithContent(): Repository
    {
        $organization = $this->makeOrganization();
        $repository = $this->makeRepository($organization);

        $this->git->initialize($repository);

        $workingDirectory = $this->workspacePath.'/'.Str::uuid();
        app('files')->ensureDirectoryExists($workingDirectory.'/src');
        app('files')->ensureDirectoryExists($workingDirectory.'/node_modules');

        $this->runCommand(['git', 'init', '--initial-branch=main', $workingDirectory]);
        $this->runCommand(['git', '-C', $workingDirectory, 'config', 'user.name', 'Crucible Test']);
        $this->runCommand(['git', '-C', $workingDirectory, 'config', 'user.email', 'test@example.com']);

        file_put_contents($workingDirectory.'/README.md', "# Test\n".str_repeat('Hello world.  ', 50));
        file_put_contents($workingDirectory.'/src/App.php', "<?php\n\nclass App {\n".str_repeat("    public function foo() {}\n", 30)."}\n");
        file_put_contents($workingDirectory.'/src/app.js', "export function main() {\n".str_repeat("  console.log('hi');\n", 20)."}\n");
        file_put_contents($workingDirectory.'/node_modules/lib.js', str_repeat("// massive vendored content\n", 1000));

        $this->runCommand(['git', '-C', $workingDirectory, 'add', '.']);
        $this->runCommand(['git', '-C', $workingDirectory, 'commit', '-m', 'Seed content']);
        $this->runCommand(['git', '-C', $workingDirectory, 'remote', 'add', 'origin', $this->git->pathFor($repository)]);
        $this->runCommand(['git', '-C', $workingDirectory, 'push', 'origin', 'main']);

        return $repository->fresh();
    }

    protected function makeOrganization(): Organization
    {
        return Organization::create([
            'name' => 'Studio '.Str::random(6),
            'slug' => 'studio-'.Str::lower(Str::random(8)),
        ]);
    }

    protected function makeRepository(Organization $organization): Repository
    {
        $owner = User::factory()->create();
        $organization->members()->attach($owner, ['role' => 'owner']);

        return $organization->repositories()->create([
            'owner_id' => $owner->id,
            'name' => 'Repo '.Str::random(6),
            'slug' => 'repo-'.Str::lower(Str::random(8)),
            'vcs_type' => 'git',
            'visibility' => 'private',
            'default_branch' => 'main',
            'lfs_enabled' => false,
        ]);
    }

    protected function runCommand(array $command): void
    {
        $process = new Process($command);
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            'Command failed: '.implode(' ', $command).PHP_EOL.$process->getErrorOutput(),
        );
    }
}
