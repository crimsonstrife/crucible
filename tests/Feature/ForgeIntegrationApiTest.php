<?php

namespace Tests\Feature;

use App\Models\AppToken;
use App\Models\ForgeIntegration;
use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\User;
use App\Services\ForgeService;
use App\Services\NativeGitRepositoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ForgeIntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    protected string $reposPath;

    protected string $workspacePath;

    protected function setUp(): void
    {
        parent::setUp();

        $root = storage_path('framework/testing/forge-integration-'.Str::uuid());

        $this->reposPath = $root.'/repositories';
        $this->workspacePath = $root.'/workspaces';

        config()->set('crucible.git.repos_path', $this->reposPath);
    }

    protected function tearDown(): void
    {
        app('files')->deleteDirectory(dirname($this->reposPath));

        parent::tearDown();
    }

    public function test_generate_token_route_stores_only_a_hash_for_the_repo_integration(): void
    {
        [$owner, $organization, $repository] = $this->createRepository();

        $integration = ForgeIntegration::create([
            'repository_id' => $repository->id,
            'forge_project_id' => 'forge-project-1',
            'forge_project_name' => 'Forge Project',
            'forge_url' => 'https://forge.example.test',
            'is_active' => true,
        ]);

        $response = $this->actingAs($owner)->post(route('repositories.forge.token.generate', [$organization, $repository]));

        $response->assertRedirect(route('repositories.forge.show', [$organization, $repository]));
        $response->assertSessionHas('forge_api_token');

        $plainTextToken = session('forge_api_token');

        $this->assertIsString($plainTextToken);
        $this->assertStringStartsWith('cru_forge_', $plainTextToken);
        $this->assertSame(hash('sha256', $plainTextToken), $integration->fresh()->getRawOriginal('api_token_hash'));
    }

    public function test_repo_machine_token_is_scoped_to_its_linked_repository(): void
    {
        [$owner, $organization, $repository] = $this->createRepository();
        [, $otherOrganization, $otherRepository] = $this->createRepository(owner: $owner);

        $nativeGit = app(NativeGitRepositoryService::class);
        $nativeGit->initialize($repository);
        $nativeGit->initialize($otherRepository);
        $this->pushCommitToRemote($repository, $nativeGit);
        $this->pushCommitToRemote($otherRepository, $nativeGit);

        $token = $this->issueIntegrationToken($repository);

        $allowed = $this->withToken($token)->getJson(
            "/api/v1/{$organization->slug}/{$repository->slug}/branches?for_user={$owner->forge_user_id}"
        );

        $allowed->assertOk();
        $allowed->assertJsonPath('data.0.name', 'main');

        $forbidden = $this->withToken($token)->getJson(
            "/api/v1/{$otherOrganization->slug}/{$otherRepository->slug}/branches?for_user={$owner->forge_user_id}"
        );

        $forbidden->assertForbidden();
    }

    public function test_global_app_token_can_access_a_linked_repository_with_a_forge_user_scope(): void
    {
        [$owner, $organization, $repository] = $this->createRepository();

        $nativeGit = app(NativeGitRepositoryService::class);
        $nativeGit->initialize($repository);
        $this->pushCommitToRemote($repository, $nativeGit);

        ForgeIntegration::create([
            'repository_id' => $repository->id,
            'forge_project_id' => 'forge-project-1',
            'forge_project_name' => 'Forge Project',
            'forge_url' => 'https://forge.example.test',
            'is_active' => true,
        ]);

        ['plaintext' => $token] = AppToken::generate('Forge', ['forge.api']);

        $response = $this->withToken($token)->getJson(
            "/api/v1/{$organization->slug}/{$repository->slug}/branches?for_forge_user_id={$owner->forge_user_id}"
        );

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'main');
    }

    public function test_global_app_token_can_list_repositories_visible_to_the_scoped_user(): void
    {
        [$owner, $organization, $repository] = $this->createRepository();

        ForgeIntegration::create([
            'repository_id' => $repository->id,
            'forge_project_id' => 'forge-project-1',
            'forge_project_name' => 'Forge Project',
            'forge_url' => 'https://forge.example.test',
            'is_active' => true,
        ]);

        ['plaintext' => $token] = AppToken::generate('Forge', ['forge.api']);

        $response = $this->withToken($token)->getJson(
            "/api/v1/repositories?for_forge_user_id={$owner->forge_user_id}"
        );

        $response->assertOk();
        $response->assertJsonPath('data.0.slug', $repository->slug);
        $response->assertJsonPath('data.0.forge_integration.forge_project_id', 'forge-project-1');
    }

    public function test_global_app_token_is_rejected_for_repositories_without_an_active_forge_link(): void
    {
        [$owner, $organization, $repository] = $this->createRepository();

        $nativeGit = app(NativeGitRepositoryService::class);
        $nativeGit->initialize($repository);
        $this->pushCommitToRemote($repository, $nativeGit);

        ['plaintext' => $token] = AppToken::generate('Forge', ['forge.api']);

        $response = $this->withToken($token)->getJson(
            "/api/v1/{$organization->slug}/{$repository->slug}/branches?for_forge_user_id={$owner->forge_user_id}"
        );

        $response->assertForbidden();
    }

    public function test_repo_machine_token_can_create_and_link_a_branch_as_the_mapped_user(): void
    {
        [$owner, $organization, $repository] = $this->createRepository();

        $nativeGit = app(NativeGitRepositoryService::class);
        $nativeGit->initialize($repository);
        $this->pushCommitToRemote($repository, $nativeGit);

        $token = $this->issueIntegrationToken($repository);

        $mock = Mockery::mock(ForgeService::class);
        $mock->shouldReceive('linkBranchToIssue')
            ->once()
            ->with(
                'PROJ-123',
                Mockery::on(fn (array $payload) => ($payload['name'] ?? null) === 'feature/PROJ-123'),
                Mockery::on(fn ($user) => $user instanceof User && $user->is($owner)),
            )
            ->andReturnTrue();
        $this->app->instance(ForgeService::class, $mock);

        $response = $this->withToken($token)->postJson(
            "/api/v1/{$organization->slug}/{$repository->slug}/branches",
            [
                'name' => 'feature/PROJ-123',
                'from_ref' => 'main',
                'forge_issue_key' => 'PROJ-123',
                'for_user' => $owner->forge_user_id,
            ]
        );

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'feature/PROJ-123');
        $response->assertJsonPath('data.forge_issue_key', 'PROJ-123');
        $this->assertContains('feature/PROJ-123', $nativeGit->branches($repository));
    }

    public function test_repo_machine_token_can_open_and_update_pull_requests(): void
    {
        [$owner, $organization, $repository] = $this->createRepository();

        $nativeGit = app(NativeGitRepositoryService::class);
        $nativeGit->initialize($repository);
        $this->pushCommitToRemote($repository, $nativeGit);

        $mainSha = $nativeGit->resolveSha($repository, 'main');
        $nativeGit->createBranch($repository, 'feature/proj-123', $mainSha);

        $token = $this->issueIntegrationToken($repository);

        $mock = Mockery::mock(ForgeService::class);
        $mock->shouldReceive('linkPrToIssue')
            ->once()
            ->with(
                'PROJ-123',
                Mockery::on(fn (array $payload) => ($payload['title'] ?? null) === 'Ship PROJ-123'),
                Mockery::on(fn ($user) => $user instanceof User && $user->is($owner)),
            )
            ->andReturnTrue();
        $this->app->instance(ForgeService::class, $mock);

        $create = $this->withToken($token)->postJson(
            "/api/v1/{$organization->slug}/{$repository->slug}/pull-requests",
            [
                'title' => 'Ship PROJ-123',
                'head' => 'feature/proj-123',
                'base' => 'main',
                'body' => 'Initial description',
                'forge_issue_key' => 'PROJ-123',
                'for_user' => $owner->forge_user_id,
            ]
        );

        $create->assertCreated();
        $create->assertJsonPath('data.body', 'Initial description');
        $create->assertJsonPath('data.forge_issue_key', 'PROJ-123');

        $update = $this->withToken($token)->patchJson(
            "/api/v1/{$organization->slug}/{$repository->slug}/pull-requests/1",
            [
                'title' => 'Ship PROJ-123 Today',
                'body' => 'Updated description',
                'for_user' => $owner->forge_user_id,
            ]
        );

        $update->assertOk();
        $update->assertJsonPath('data.title', 'Ship PROJ-123 Today');
        $update->assertJsonPath('data.body', 'Updated description');

        $pullRequest = PullRequest::query()->where('repository_id', $repository->id)->where('number', 1)->firstOrFail();

        $this->assertSame('Ship PROJ-123 Today', $pullRequest->title);
        $this->assertSame('Updated description', $pullRequest->description);
    }

    protected function createRepository(?User $owner = null): array
    {
        $owner ??= User::factory()->create([
            'forge_user_id' => 'forge-user-'.Str::lower(Str::random(8)),
        ]);

        if (! $owner->forge_user_id) {
            $owner->forceFill([
                'forge_user_id' => 'forge-user-'.Str::lower(Str::random(8)),
            ])->save();
        }

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

    protected function issueIntegrationToken(Repository $repository): string
    {
        $integration = ForgeIntegration::create([
            'repository_id' => $repository->id,
            'forge_project_id' => 'forge-project-'.Str::lower(Str::random(6)),
            'forge_project_name' => 'Forge Project',
            'forge_url' => 'https://forge.example.test',
            'is_active' => true,
        ]);

        return $integration->issueApiToken();
    }

    protected function pushCommitToRemote(Repository $repository, NativeGitRepositoryService $nativeGit): void
    {
        $workingDirectory = $this->workspacePath.'/'.Str::uuid();

        app('files')->ensureDirectoryExists($workingDirectory);

        $this->runCommand(['git', 'init', '--initial-branch=main', $workingDirectory]);
        $this->runCommand(['git', '-C', $workingDirectory, 'config', 'user.name', 'Crucible Test']);
        $this->runCommand(['git', '-C', $workingDirectory, 'config', 'user.email', 'test@example.com']);

        file_put_contents($workingDirectory.'/README.md', "# Test\n");

        $this->runCommand(['git', '-C', $workingDirectory, 'add', 'README.md']);
        $this->runCommand(['git', '-C', $workingDirectory, 'commit', '-m', 'Initial commit']);
        $this->runCommand(['git', '-C', $workingDirectory, 'remote', 'add', 'origin', $nativeGit->pathFor($repository)]);
        $this->runCommand(['git', '-C', $workingDirectory, 'push', 'origin', 'main']);
    }

    protected function runCommand(array $command): string
    {
        $process = new Process($command);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->fail($process->getErrorOutput() ?: $process->getOutput());
        }

        return trim($process->getOutput());
    }
}
