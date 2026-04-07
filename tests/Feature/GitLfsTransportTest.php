<?php

namespace Tests\Feature;

use App\Contracts\RepositoryDriverInterface;
use App\Drivers\NativeGitDriver;
use App\Drivers\StubRepositoryDriver;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class GitLfsTransportTest extends TestCase
{
    use RefreshDatabase;

    protected string $reposPath;

    protected string $diskRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $root = storage_path('framework/testing/git-lfs-transport-'.Str::uuid());

        $this->reposPath = $root.'/repositories';
        $this->diskRoot = $root.'/lfs-disk';

        config()->set('crucible.git.repos_path', $this->reposPath);
        config()->set('filesystems.disks.local.root', $this->diskRoot);
    }

    protected function tearDown(): void
    {
        app('files')->deleteDirectory(dirname($this->reposPath));

        parent::tearDown();
    }

    public function test_transport_batch_returns_not_implemented_when_native_backend_is_inactive(): void
    {
        [$owner, $repository, $password] = $this->createRepository();

        $this->app->bind(RepositoryDriverInterface::class, StubRepositoryDriver::class);

        $response = $this->withHeaders([
            'Authorization' => $this->basicAuthHeader($owner->email, $password),
            'Accept' => 'application/vnd.git-lfs+json',
        ])->postJson(sprintf(
            '/%s/%s.git/info/lfs/objects/batch',
            $repository->organization->slug,
            $repository->slug,
        ), [
            'operation' => 'upload',
            'objects' => [
                [
                    'oid' => str_repeat('a', 64),
                    'size' => 4,
                ],
            ],
        ]);

        $response->assertStatus(501);
        $response->assertSeeText('native git backend is active');
    }

    public function test_transport_lfs_objects_can_be_uploaded_and_downloaded_over_repo_urls(): void
    {
        [$owner, $repository, $password] = $this->createRepository();

        $this->app->bind(RepositoryDriverInterface::class, NativeGitDriver::class);

        $oid = str_repeat('b', 64);
        $basicAuth = $this->basicAuthHeader($owner->email, $password);

        $batchResponse = $this->withHeaders([
            'Authorization' => $basicAuth,
            'Accept' => 'application/vnd.git-lfs+json',
        ])->postJson(sprintf(
            '/%s/%s.git/info/lfs/objects/batch',
            $repository->organization->slug,
            $repository->slug,
        ), [
            'operation' => 'upload',
            'objects' => [
                [
                    'oid' => $oid,
                    'size' => 4,
                ],
            ],
        ]);

        $batchResponse->assertOk();
        $uploadPath = parse_url($batchResponse->json('objects.0.actions.upload.href'), PHP_URL_PATH);

        $this->assertSame(
            sprintf('/%s/%s.git/info/lfs/objects/%s', $repository->organization->slug, $repository->slug, $oid),
            $uploadPath,
        );

        $uploadResponse = $this->call(
            'PUT',
            $uploadPath,
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $basicAuth,
                'CONTENT_TYPE' => 'application/octet-stream',
                'CONTENT_LENGTH' => 4,
            ],
            'test',
        );

        $uploadResponse->assertOk();

        $downloadBatchResponse = $this->withHeaders([
            'Authorization' => $basicAuth,
            'Accept' => 'application/vnd.git-lfs+json',
        ])->postJson(sprintf(
            '/%s/%s.git/info/lfs/objects/batch',
            $repository->organization->slug,
            $repository->slug,
        ), [
            'operation' => 'download',
            'objects' => [
                [
                    'oid' => $oid,
                    'size' => 4,
                ],
            ],
        ]);

        $downloadBatchResponse->assertOk();
        $downloadPath = parse_url($downloadBatchResponse->json('objects.0.actions.download.href'), PHP_URL_PATH);

        $downloadResponse = $this->withHeaders([
            'Authorization' => $basicAuth,
        ])->get($downloadPath);

        $downloadResponse->assertOk();
        $downloadResponse->assertHeader('Content-Length', '4');
        $this->assertSame('test', $downloadResponse->streamedContent());
    }

    protected function createRepository(): array
    {
        $password = 'secret-pass';
        $owner = User::factory()->create([
            'password' => Hash::make($password),
        ]);

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
            'lfs_enabled' => true,
        ]);

        return [$owner, $repository, $password];
    }

    protected function basicAuthHeader(string $username, string $password): string
    {
        return 'Basic '.base64_encode($username.':'.$password);
    }
}
