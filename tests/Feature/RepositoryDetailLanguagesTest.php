<?php

namespace Tests\Feature;

use App\Contracts\RepositoryDriverInterface;
use App\Drivers\NativeGitDriver;
use App\Jobs\ComputeRepositoryLanguageStatsJob;
use App\Models\Organization;
use App\Models\User;
use App\Services\NativeGitRepositoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RepositoryDetailLanguagesTest extends TestCase
{
    use RefreshDatabase;

    protected string $reposPath;

    protected string $workspacePath;

    protected function setUp(): void
    {
        parent::setUp();

        $root = storage_path('framework/testing/repo-detail-languages-'.Str::uuid());
        $this->reposPath = $root.'/repositories';
        $this->workspacePath = $root.'/workspaces';
        config()->set('crucible.git.repos_path', $this->reposPath);
        $this->app->bind(RepositoryDriverInterface::class, NativeGitDriver::class);
    }

    protected function tearDown(): void
    {
        app('files')->deleteDirectory(dirname($this->reposPath));

        parent::tearDown();
    }

    public function test_languages_card_renders_with_cached_stats(): void
    {
        [$owner, $organization, $repository] = $this->seedRepositoryWithContent();

        // Pre-populate cached stats that match current HEAD so card renders in 'ready' state.
        $sha = app(NativeGitRepositoryService::class)->headSha($repository);
        $this->assertNotNull($sha);

        $repository->forceFill([
            'language_stats' => ['PHP' => 3000, 'JavaScript' => 1000],
            'language_stats_head_sha' => $sha,
            'language_stats_updated_at' => now(),
        ])->saveWithoutTouch();

        $response = $this->actingAs($owner)->get(route('repositories.show', [$organization, $repository]));

        $response->assertOk();
        $response->assertSee('Languages');
        $response->assertSee('PHP');
        $response->assertSee('JavaScript');
        $response->assertSee('75%');
        $response->assertSee('25%');
    }

    public function test_languages_card_shows_pending_state_and_dispatches_job_when_no_cache(): void
    {
        Queue::fake();

        [$owner, $organization, $repository] = $this->seedRepositoryWithContent();

        $response = $this->actingAs($owner)->get(route('repositories.show', [$organization, $repository]));

        $response->assertOk();
        $response->assertSee('Languages');
        $response->assertSee('Calculating languages');

        Queue::assertPushed(ComputeRepositoryLanguageStatsJob::class);
    }

    public function test_languages_card_is_hidden_when_repo_has_no_revision(): void
    {
        [$owner, $organization, $repository] = $this->createBareRepository();

        $response = $this->actingAs($owner)->get(route('repositories.show', [$organization, $repository]));

        $response->assertOk();
        $response->assertDontSee('Languages');
        $response->assertDontSee('Calculating languages');
    }

    protected function createBareRepository(): array
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
            'default_branch' => 'main',
            'lfs_enabled' => false,
        ]);

        return [$owner, $organization, $repository];
    }

    protected function seedRepositoryWithContent(): array
    {
        [$owner, $organization, $repository] = $this->createBareRepository();

        $git = app(NativeGitRepositoryService::class);
        $git->initialize($repository);

        $workingDirectory = $this->workspacePath.'/'.Str::uuid();
        app('files')->ensureDirectoryExists($workingDirectory.'/src');

        $this->runCommand(['git', 'init', '--initial-branch=main', $workingDirectory]);
        $this->runCommand(['git', '-C', $workingDirectory, 'config', 'user.name', 'Crucible Test']);
        $this->runCommand(['git', '-C', $workingDirectory, 'config', 'user.email', 'test@example.com']);

        file_put_contents($workingDirectory.'/README.md', "# Hello\n");
        file_put_contents($workingDirectory.'/src/App.php', "<?php\nclass App {}\n");
        file_put_contents($workingDirectory.'/src/app.js', "export const x = 1;\n");

        $this->runCommand(['git', '-C', $workingDirectory, 'add', '.']);
        $this->runCommand(['git', '-C', $workingDirectory, 'commit', '-m', 'Initial']);
        $this->runCommand(['git', '-C', $workingDirectory, 'remote', 'add', 'origin', $git->pathFor($repository)]);
        $this->runCommand(['git', '-C', $workingDirectory, 'push', 'origin', 'main']);

        return [$owner, $organization, $repository->fresh()];
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
