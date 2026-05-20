<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\Repository;
use App\Services\NativeGitRepositoryService;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class NativeGitRepositoryServiceTest extends TestCase
{
    protected string $reposPath;

    protected string $workspacePath;

    protected NativeGitRepositoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $root = storage_path('framework/testing/native-git-'.Str::uuid());

        $this->reposPath = $root.'/repositories';
        $this->workspacePath = $root.'/workspaces';

        config()->set('crucible.git.repos_path', $this->reposPath);

        $this->service = app(NativeGitRepositoryService::class);
    }

    protected function tearDown(): void
    {
        app('files')->deleteDirectory(dirname($this->reposPath));

        parent::tearDown();
    }

    public function test_initialize_creates_a_bare_repository_with_the_requested_default_branch(): void
    {
        $repository = $this->makeRepository(defaultBranch: 'develop');

        $this->service->initialize($repository);

        $this->assertTrue($this->service->exists($repository));
        $this->assertSame('develop', $this->service->defaultBranch($repository));
        $this->assertSame(['develop'], $this->service->branches($repository));
        $this->assertDirectoryExists($this->service->pathFor($repository));
    }

    public function test_clone_copies_remote_refs_and_delete_removes_the_repository_directory(): void
    {
        $remoteRepository = $this->makeRepository(
            organizationSlug: 'remote-org',
            repositorySlug: 'remote-repo',
            defaultBranch: 'main',
        );

        $this->service->initialize($remoteRepository);
        $this->pushCommitToRemote($remoteRepository);

        $clonedRepository = $this->makeRepository(
            organizationSlug: 'clone-org',
            repositorySlug: 'cloned-repo',
            defaultBranch: 'main',
        );

        $this->service->clone($this->service->pathFor($remoteRepository), $clonedRepository);

        $this->assertTrue($this->service->exists($clonedRepository));
        $this->assertSame('main', $this->service->defaultBranch($clonedRepository));
        $this->assertContains('main', $this->service->branches($clonedRepository));
        $this->assertGreaterThan(0, $this->service->size($clonedRepository));

        $this->service->delete($clonedRepository);

        $this->assertFalse($this->service->exists($clonedRepository));
    }

    public function test_recursive_tree_and_file_contents_are_available_for_committed_content(): void
    {
        $repository = $this->makeRepository(defaultBranch: 'main');

        $this->service->initialize($repository);
        $this->pushCommitToRemote($repository);

        $tree = $this->service->recursiveTree($repository, 'main');

        $this->assertTrue($this->service->hasRevision($repository, 'main'));
        $this->assertContains('README.md', collect($tree)->pluck('name')->all());
        $this->assertContains('src', collect($tree)->pluck('name')->all());

        $srcDirectory = collect($tree)->firstWhere('name', 'src');

        $this->assertNotNull($srcDirectory);
        $this->assertContains('GameData.txt', collect($srcDirectory['children'])->pluck('name')->all());
        $this->assertStringContainsString(
            '# Native Git',
            (string) $this->service->fileContents($repository, 'README.md', 'main'),
        );
    }

    // ── Source archive (Tier 1) ──────────────────────────────────────

    public function test_archive_streams_a_valid_zip_of_the_tagged_tree(): void
    {
        $repository = $this->makeRepository(defaultBranch: 'main');
        $this->service->initialize($repository);
        $this->pushCommitToRemote($repository);
        $sha = $this->service->resolveSha($repository, 'main');
        $this->assertNotNull($sha);

        $process = $this->service->archive($repository, $sha, 'zip', 'sample-repo-v1.0.0');

        $output = '';
        foreach ($process->getIterator(Process::ITER_KEEP_OUTPUT | Process::ITER_SKIP_ERR) as $chunk) {
            $output .= $chunk;
        }
        $process->wait();

        $this->assertTrue($process->isSuccessful(), 'git archive failed: '.$process->getErrorOutput());
        $this->assertNotEmpty($output);

        // Zip file signature: PK\x03\x04
        $this->assertSame("PK\x03\x04", substr($output, 0, 4), 'Output does not start with a zip signature');

        // Pipe the output through `unzip -l` to confirm the prefix and a known file.
        $tmpZip = tempnam(sys_get_temp_dir(), 'crucible-archive-test-').'.zip';
        file_put_contents($tmpZip, $output);
        try {
            $list = new Process(['unzip', '-l', $tmpZip]);
            $list->run();
            $listing = $list->getOutput();

            $this->assertStringContainsString('sample-repo-v1.0.0/README.md', $listing);
            $this->assertStringContainsString('sample-repo-v1.0.0/src/GameData.txt', $listing);
        } finally {
            @unlink($tmpZip);
        }
    }

    public function test_archive_produces_tar_gz_with_gzip_magic_header(): void
    {
        $repository = $this->makeRepository(defaultBranch: 'main');
        $this->service->initialize($repository);
        $this->pushCommitToRemote($repository);
        $sha = $this->service->resolveSha($repository, 'main');

        $process = $this->service->archive($repository, $sha, 'tar.gz', 'sample-repo-v1.0.0');

        $output = '';
        foreach ($process->getIterator(Process::ITER_KEEP_OUTPUT | Process::ITER_SKIP_ERR) as $chunk) {
            $output .= $chunk;
        }
        $process->wait();

        $this->assertTrue($process->isSuccessful(), 'git archive failed: '.$process->getErrorOutput());

        // gzip magic bytes 1f 8b
        $this->assertSame("\x1f\x8b", substr($output, 0, 2), 'Output does not start with gzip magic bytes');
    }

    public function test_archive_rejects_unknown_format(): void
    {
        $repository = $this->makeRepository(defaultBranch: 'main');
        $this->service->initialize($repository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unsupported archive format/');

        $this->service->archive($repository, str_repeat('a', 40), 'rar', 'prefix');
    }

    public function test_archive_rejects_unknown_sha(): void
    {
        $repository = $this->makeRepository(defaultBranch: 'main');
        $this->service->initialize($repository);
        $this->pushCommitToRemote($repository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found/');

        $this->service->archive($repository, str_repeat('f', 40), 'zip', 'prefix');
    }

    // ── URL normalisation ────────────────────────────────────────────

    public function test_normalize_rewrites_fine_grained_pat_to_x_access_token(): void
    {
        $url = 'https://github_pat_abc123@github.com/owner/repo.git';

        $this->assertSame(
            'https://x-access-token:github_pat_abc123@github.com/owner/repo.git',
            NativeGitRepositoryService::normalizeRemoteUrl($url),
        );
    }

    public function test_normalize_rewrites_classic_pat_to_x_access_token(): void
    {
        $url = 'https://ghp_abc123@github.com/owner/repo.git';

        $this->assertSame(
            'https://x-access-token:ghp_abc123@github.com/owner/repo.git',
            NativeGitRepositoryService::normalizeRemoteUrl($url),
        );
    }

    public function test_normalize_rewrites_gitlab_pat_to_x_access_token(): void
    {
        $url = 'https://glpat-abc123@gitlab.com/owner/repo.git';

        $this->assertSame(
            'https://x-access-token:glpat-abc123@gitlab.com/owner/repo.git',
            NativeGitRepositoryService::normalizeRemoteUrl($url),
        );
    }

    public function test_normalize_leaves_user_pass_format_unchanged(): void
    {
        $url = 'https://x-access-token:ghp_abc123@github.com/owner/repo.git';

        $this->assertSame($url, NativeGitRepositoryService::normalizeRemoteUrl($url));
    }

    public function test_normalize_leaves_plain_https_unchanged(): void
    {
        $url = 'https://github.com/owner/repo.git';

        $this->assertSame($url, NativeGitRepositoryService::normalizeRemoteUrl($url));
    }

    public function test_normalize_leaves_ssh_url_unchanged(): void
    {
        $url = 'git@github.com:owner/repo.git';

        $this->assertSame($url, NativeGitRepositoryService::normalizeRemoteUrl($url));
    }

    public function test_normalize_leaves_regular_username_unchanged(): void
    {
        $url = 'https://myuser@bitbucket.org/owner/repo.git';

        $this->assertSame($url, NativeGitRepositoryService::normalizeRemoteUrl($url));
    }

    public function test_normalize_preserves_port_and_path(): void
    {
        $url = 'https://github_pat_xyz@github.com:8443/owner/repo.git';

        $this->assertSame(
            'https://x-access-token:github_pat_xyz@github.com:8443/owner/repo.git',
            NativeGitRepositoryService::normalizeRemoteUrl($url),
        );
    }

    // ── Credential redaction ────────────────────────────────────────

    public function test_redact_command_strips_credentials_from_url(): void
    {
        $command = ['git', 'clone', '--bare', 'https://ghp_secret@github.com/owner/repo.git', '/tmp/repo'];

        $this->assertSame(
            ['git', 'clone', '--bare', 'https://***@github.com/owner/repo.git', '/tmp/repo'],
            NativeGitRepositoryService::redactCommand($command),
        );
    }

    public function test_redact_command_strips_user_pass_credentials(): void
    {
        $command = ['git', 'remote', 'set-url', 'origin', 'https://x-access-token:ghp_secret@github.com/owner/repo.git'];

        $this->assertSame(
            ['git', 'remote', 'set-url', 'origin', 'https://***@github.com/owner/repo.git'],
            NativeGitRepositoryService::redactCommand($command),
        );
    }

    public function test_redact_command_leaves_plain_urls_unchanged(): void
    {
        $command = ['git', 'clone', 'https://github.com/owner/repo.git'];

        $this->assertSame($command, NativeGitRepositoryService::redactCommand($command));
    }

    protected function makeRepository(
        string $organizationSlug = 'studio',
        string $repositorySlug = 'sample-repo',
        string $defaultBranch = 'main',
    ): Repository {
        $organization = new Organization([
            'id' => (string) Str::uuid(),
            'name' => Str::headline($organizationSlug),
            'slug' => $organizationSlug,
        ]);

        $repository = new Repository([
            'id' => (string) Str::uuid(),
            'name' => Str::headline($repositorySlug),
            'slug' => $repositorySlug,
            'default_branch' => $defaultBranch,
        ]);

        $repository->setRelation('organization', $organization);

        return $repository;
    }

    protected function pushCommitToRemote(Repository $repository): void
    {
        $workingDirectory = $this->workspacePath.'/'.Str::uuid();

        app('files')->ensureDirectoryExists($workingDirectory);

        $this->runCommand(['git', 'init', '--initial-branch=main', $workingDirectory]);
        $this->runCommand(['git', '-C', $workingDirectory, 'config', 'user.name', 'Crucible Test']);
        $this->runCommand(['git', '-C', $workingDirectory, 'config', 'user.email', 'test@example.com']);

        app('files')->ensureDirectoryExists($workingDirectory.'/src');
        file_put_contents($workingDirectory.'/README.md', "# Native Git\n");
        file_put_contents($workingDirectory.'/src/GameData.txt', "build=42\n");

        $this->runCommand(['git', '-C', $workingDirectory, 'add', 'README.md']);
        $this->runCommand(['git', '-C', $workingDirectory, 'add', 'src/GameData.txt']);
        $this->runCommand(['git', '-C', $workingDirectory, 'commit', '-m', 'Initial commit']);
        $this->runCommand(['git', '-C', $workingDirectory, 'remote', 'add', 'origin', $this->service->pathFor($repository)]);
        $this->runCommand(['git', '-C', $workingDirectory, 'push', 'origin', 'main']);
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
