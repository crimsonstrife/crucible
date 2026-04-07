<?php

namespace Tests\Feature;

use App\Contracts\RepositoryDriverInterface;
use App\Drivers\NativeGitDriver;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use App\Services\LfsService;
use App\Services\NativeGitRepositoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RepositoryBrowserUiTest extends TestCase
{
    use RefreshDatabase;

    protected string $reposPath;

    protected string $workspacePath;

    protected function setUp(): void
    {
        parent::setUp();

        $root = storage_path('framework/testing/repository-browser-'.Str::uuid());

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

    public function test_repository_show_displays_a_file_tree_and_renders_the_root_readme(): void
    {
        [$owner, $organization, $repository] = $this->createRepository();

        /** @var NativeGitRepositoryService $nativeGit */
        $nativeGit = app(NativeGitRepositoryService::class);
        $nativeGit->initialize($repository);
        $this->pushCommitToRemote($repository, $nativeGit);

        $response = $this->actingAs($owner)->get(route('repositories.show', [$organization, $repository]));

        $response->assertOk();
        $response->assertSee('Repository Browser');
        $response->assertSee('README.md');
        $response->assertSee('src');
        $response->assertSee('Crucible Test README');
        $response->assertSee('This is a browser test.');
    }

    public function test_repository_show_can_browse_nested_directories_and_preview_files(): void
    {
        [$owner, $organization, $repository] = $this->createRepository();

        /** @var NativeGitRepositoryService $nativeGit */
        $nativeGit = app(NativeGitRepositoryService::class);
        $nativeGit->initialize($repository);
        $this->pushCommitToRemote($repository, $nativeGit);

        $directoryResponse = $this->actingAs($owner)->get(route('repositories.show', [
            'organization' => $organization,
            'repository' => $repository,
            'path' => 'src',
        ]));

        $directoryResponse->assertOk();
        $directoryResponse->assertSee('Repository Browser');
        $directoryResponse->assertSee('src');
        $directoryResponse->assertSee('GameData.txt');

        $fileResponse = $this->actingAs($owner)->get(route('repositories.show', [
            'organization' => $organization,
            'repository' => $repository,
            'path' => 'src/GameData.txt',
        ]));

        $fileResponse->assertOk();
        $fileResponse->assertSee('File Preview');
        $fileResponse->assertSee('src/GameData.txt');
        $fileResponse->assertSee('build=42');
    }

    public function test_repository_show_can_switch_to_a_non_default_branch(): void
    {
        [$owner, $organization, $repository] = $this->createRepository();

        /** @var NativeGitRepositoryService $nativeGit */
        $nativeGit = app(NativeGitRepositoryService::class);
        $nativeGit->initialize($repository);
        $workingDirectory = $this->pushCommitToRemote($repository, $nativeGit);
        $this->pushDevelopBranchCommit($workingDirectory);

        $response = $this->actingAs($owner)->get(route('repositories.show', [
            'organization' => $organization,
            'repository' => $repository,
            'ref' => 'develop',
            'path' => 'docs/BranchNotes.md',
        ]));

        $response->assertOk();
        $response->assertSee('develop');
        $response->assertSee('BranchNotes.md');
        $response->assertSee('Exclusive to develop.');
    }

    public function test_markdown_files_offer_rendered_and_source_previews(): void
    {
        [$owner, $organization, $repository] = $this->createRepository();

        /** @var NativeGitRepositoryService $nativeGit */
        $nativeGit = app(NativeGitRepositoryService::class);
        $nativeGit->initialize($repository);
        $this->pushCommitToRemote($repository, $nativeGit);

        $renderedResponse = $this->actingAs($owner)->get(route('repositories.show', [
            'organization' => $organization,
            'repository' => $repository,
            'path' => 'README.md',
        ]));

        $renderedResponse->assertOk();
        $renderedResponse->assertSee('Rendered');
        $renderedResponse->assertSee('Source');
        $renderedResponse->assertSee('<h1>Crucible Test README</h1>', false);

        $sourceResponse = $this->actingAs($owner)->get(route('repositories.show', [
            'organization' => $organization,
            'repository' => $repository,
            'path' => 'README.md',
            'preview' => 'source',
        ]));

        $sourceResponse->assertOk();
        $sourceResponse->assertSee('# Crucible Test README');
        $sourceResponse->assertSee('This is a browser test.');
    }

    public function test_lfs_tracked_images_are_badged_and_previewed_inline(): void
    {
        [$owner, $organization, $repository] = $this->createRepository(lfsEnabled: true);

        /** @var NativeGitRepositoryService $nativeGit */
        $nativeGit = app(NativeGitRepositoryService::class);
        $nativeGit->initialize($repository);
        $this->pushLfsImageCommitToRemote($repository, $nativeGit);

        $listingResponse = $this->actingAs($owner)->get(route('repositories.show', [
            'organization' => $organization,
            'repository' => $repository,
            'path' => 'artwork',
        ]));

        $listingResponse->assertOk();
        $listingResponse->assertSee('logo.png');
        $listingResponse->assertSee('Git LFS');

        $fileResponse = $this->actingAs($owner)->get(route('repositories.show', [
            'organization' => $organization,
            'repository' => $repository,
            'path' => 'artwork/logo.png',
        ]));

        $fileResponse->assertOk();
        $fileResponse->assertSee('artwork/logo.png');
        $fileResponse->assertSee('Git LFS');
        $fileResponse->assertSee('data:image/png;base64,', false);
    }

    protected function createRepository(bool $lfsEnabled = false): array
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
            'lfs_enabled' => $lfsEnabled,
        ]);

        return [$owner, $organization, $repository];
    }

    protected function pushCommitToRemote(Repository $repository, NativeGitRepositoryService $nativeGit): string
    {
        $workingDirectory = $this->workspacePath.'/'.Str::uuid();

        app('files')->ensureDirectoryExists($workingDirectory.'/src');

        $this->runCommand(['git', 'init', '--initial-branch=main', $workingDirectory]);
        $this->runCommand(['git', '-C', $workingDirectory, 'config', 'user.name', 'Crucible Test']);
        $this->runCommand(['git', '-C', $workingDirectory, 'config', 'user.email', 'test@example.com']);

        file_put_contents($workingDirectory.'/README.md', "# Crucible Test README\n\nThis is a browser test.\n");
        file_put_contents($workingDirectory.'/src/GameData.txt', "build=42\n");

        $this->runCommand(['git', '-C', $workingDirectory, 'add', 'README.md']);
        $this->runCommand(['git', '-C', $workingDirectory, 'add', 'src/GameData.txt']);
        $this->runCommand(['git', '-C', $workingDirectory, 'commit', '-m', 'Initial commit']);
        $this->runCommand(['git', '-C', $workingDirectory, 'remote', 'add', 'origin', $nativeGit->pathFor($repository)]);
        $this->runCommand(['git', '-C', $workingDirectory, 'push', 'origin', 'main']);

        return $workingDirectory;
    }

    protected function pushDevelopBranchCommit(string $workingDirectory): void
    {
        app('files')->ensureDirectoryExists($workingDirectory.'/docs');

        $this->runCommand(['git', '-C', $workingDirectory, 'checkout', '-b', 'develop']);

        file_put_contents($workingDirectory.'/docs/BranchNotes.md', "# Develop Branch\n\nExclusive to develop.\n");

        $this->runCommand(['git', '-C', $workingDirectory, 'add', 'docs/BranchNotes.md']);
        $this->runCommand(['git', '-C', $workingDirectory, 'commit', '-m', 'Add develop notes']);
        $this->runCommand(['git', '-C', $workingDirectory, 'push', '-u', 'origin', 'develop']);
    }

    protected function pushLfsImageCommitToRemote(Repository $repository, NativeGitRepositoryService $nativeGit): void
    {
        $workingDirectory = $this->workspacePath.'/'.Str::uuid();
        $imageBytes = $this->tinyPng();
        $oid = hash('sha256', $imageBytes);
        $size = strlen($imageBytes);

        app('files')->ensureDirectoryExists($workingDirectory.'/artwork');
        app('files')->ensureDirectoryExists($workingDirectory.'/.githooks');

        $this->runCommand(['git', 'init', '--initial-branch=main', $workingDirectory]);
        $this->runCommand(['git', '-C', $workingDirectory, 'config', 'user.name', 'Crucible Test']);
        $this->runCommand(['git', '-C', $workingDirectory, 'config', 'user.email', 'test@example.com']);
        $this->runCommand(['git', '-C', $workingDirectory, 'config', 'core.hooksPath', $workingDirectory.'/.githooks']);
        $this->runCommand(['git', '-C', $workingDirectory, 'config', 'filter.lfs.clean', 'cat']);
        $this->runCommand(['git', '-C', $workingDirectory, 'config', 'filter.lfs.smudge', 'cat']);
        $this->runCommand(['git', '-C', $workingDirectory, 'config', 'filter.lfs.process', '']);
        $this->runCommand(['git', '-C', $workingDirectory, 'config', 'filter.lfs.required', 'false']);

        file_put_contents($workingDirectory.'/.gitattributes', "artwork/*.png filter=lfs diff=lfs merge=lfs -text\n");
        file_put_contents(
            $workingDirectory.'/artwork/logo.png',
            "version https://git-lfs.github.com/spec/v1\n".
            "oid sha256:{$oid}\n".
            "size {$size}\n"
        );

        $this->runCommand(['git', '-C', $workingDirectory, 'add', '.gitattributes']);
        $this->runCommand(['git', '-C', $workingDirectory, 'add', 'artwork/logo.png']);
        $this->runCommand(['git', '-C', $workingDirectory, 'commit', '-m', 'Add LFS tracked image']);
        $this->runCommand(['git', '-C', $workingDirectory, 'remote', 'add', 'origin', $nativeGit->pathFor($repository)]);
        $this->runCommand(['git', '-C', $workingDirectory, 'push', 'origin', 'main']);

        app(LfsService::class)->store($repository, $oid, $size, $imageBytes, 'image/png');
    }

    protected function tinyPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/qhoAAAAASUVORK5CYII=',
            true,
        ) ?: '';
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
