<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LfsApiTest extends TestCase
{
    use RefreshDatabase;

    protected string $diskRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->diskRoot = storage_path('framework/testing/lfs-disk-'.Str::uuid());

        config()->set('filesystems.disks.local.root', $this->diskRoot);
    }

    protected function tearDown(): void
    {
        app('files')->deleteDirectory($this->diskRoot);

        parent::tearDown();
    }

    public function test_lfs_objects_can_be_uploaded_and_downloaded(): void
    {
        [$owner, $repository, $organization] = $this->createRepository();

        Sanctum::actingAs($owner);

        $oid = str_repeat('a', 64);

        $batchResponse = $this->postJson("/api/v1/{$organization->slug}/{$repository->slug}/info/lfs/objects/batch", [
            'operation' => 'upload',
            'objects' => [
                [
                    'oid' => $oid,
                    'size' => 4,
                ],
            ],
        ]);

        $batchResponse->assertOk();
        $uploadUrl = parse_url($batchResponse->json('objects.0.actions.upload.href'), PHP_URL_PATH);

        $uploadResponse = $this->call(
            'PUT',
            $uploadUrl,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/octet-stream',
                'CONTENT_LENGTH' => 4,
            ],
            'test',
        );

        $uploadResponse->assertOk();

        $downloadBatchResponse = $this->postJson("/api/v1/{$organization->slug}/{$repository->slug}/info/lfs/objects/batch", [
            'operation' => 'download',
            'objects' => [
                [
                    'oid' => $oid,
                    'size' => 4,
                ],
            ],
        ]);

        $downloadBatchResponse->assertOk();
        $downloadUrl = parse_url($downloadBatchResponse->json('objects.0.actions.download.href'), PHP_URL_PATH);

        $downloadResponse = $this->get($downloadUrl);

        $downloadResponse->assertOk();
        $downloadResponse->assertHeader('Content-Length', '4');
        $this->assertSame('test', $downloadResponse->streamedContent());
    }

    protected function createRepository(): array
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
            'lfs_enabled' => true,
        ]);

        return [$owner, $repository, $organization];
    }
}
