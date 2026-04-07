<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Repository;
use App\Models\LfsObject;
use App\Models\LfsUploadSession;
use App\Models\User;
use App\Services\StorageQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase7Test extends TestCase
{
    use RefreshDatabase;

    // ── StorageQuotaService ─────────────────────────────────────────────────

    public function test_has_quota_returns_false_when_null(): void
    {
        $org = $this->createOrganization(['storage_quota_gb' => null]);
        $service = app(StorageQuotaService::class);

        $this->assertFalse($service->hasQuota($org));
    }

    public function test_has_quota_returns_true_when_set(): void
    {
        $org = $this->createOrganization(['storage_quota_gb' => 50]);
        $service = app(StorageQuotaService::class);

        $this->assertTrue($service->hasQuota($org));
    }

    public function test_quota_bytes_returns_correct_value(): void
    {
        $org = $this->createOrganization(['storage_quota_gb' => 10]);
        $service = app(StorageQuotaService::class);

        $expected = 10 * 1024 * 1024 * 1024; // 10 GB in bytes
        $this->assertSame($expected, $service->quotaBytes($org));
    }

    public function test_quota_bytes_returns_null_when_no_quota(): void
    {
        $org = $this->createOrganization(['storage_quota_gb' => null]);
        $service = app(StorageQuotaService::class);

        $this->assertNull($service->quotaBytes($org));
    }

    public function test_can_upload_returns_true_with_no_quota(): void
    {
        $org = $this->createOrganization(['storage_quota_gb' => null]);
        $service = app(StorageQuotaService::class);

        $this->assertTrue($service->canUpload($org, 1_000_000_000)); // 1 GB
    }

    public function test_can_upload_returns_true_within_quota(): void
    {
        $org = $this->createOrganization(['storage_quota_gb' => 10]);
        $service = app(StorageQuotaService::class);

        // No repos yet, so usage is 0
        $this->assertTrue($service->canUpload($org, 1_000_000_000)); // 1 GB
    }

    public function test_can_upload_returns_false_when_exceeding_quota(): void
    {
        $org = $this->createOrganization(['storage_quota_gb' => 1]); // 1 GB quota
        $user = $this->createUser();

        // Create a repo with existing usage near the limit
        Repository::create([
            'id'              => (string) Str::uuid(),
            'organization_id' => $org->id,
            'owner_id'        => $user->id,
            'name'            => 'Big Repo',
            'slug'            => 'big-repo',
            'size_kb'         => 500_000,     // ~500 MB
            'lfs_size_kb'     => 500_000,     // ~500 MB
        ]);

        $service = app(StorageQuotaService::class);

        // Trying to upload 200 MB (more than remaining ~24 MB)
        $this->assertFalse($service->canUpload($org, 200 * 1024 * 1024));
    }

    public function test_used_bytes_sums_all_repos(): void
    {
        $org = $this->createOrganization();
        $user = $this->createUser();

        Repository::create([
            'id'              => (string) Str::uuid(),
            'organization_id' => $org->id,
            'owner_id'        => $user->id,
            'name'            => 'Repo A',
            'slug'            => 'repo-a',
            'size_kb'         => 100,
            'lfs_size_kb'     => 200,
        ]);

        Repository::create([
            'id'              => (string) Str::uuid(),
            'organization_id' => $org->id,
            'owner_id'        => $user->id,
            'name'            => 'Repo B',
            'slug'            => 'repo-b',
            'size_kb'         => 300,
            'lfs_size_kb'     => 400,
        ]);

        $service = app(StorageQuotaService::class);
        $usedBytes = $service->usedBytes($org);

        // (100 + 200 + 300 + 400) = 1000 KB * 1024 = 1,024,000 bytes
        $this->assertSame(1000 * 1024, $usedBytes);
    }

    public function test_remaining_bytes_is_null_without_quota(): void
    {
        $org = $this->createOrganization(['storage_quota_gb' => null]);
        $service = app(StorageQuotaService::class);

        $this->assertNull($service->remainingBytes($org));
    }

    public function test_remaining_bytes_calculated_correctly(): void
    {
        $org = $this->createOrganization(['storage_quota_gb' => 1]); // 1 GB
        $user = $this->createUser();

        Repository::create([
            'id'              => (string) Str::uuid(),
            'organization_id' => $org->id,
            'owner_id'        => $user->id,
            'name'            => 'Repo',
            'slug'            => 'repo',
            'size_kb'         => 100_000,  // ~100 MB
            'lfs_size_kb'     => 0,
        ]);

        $service = app(StorageQuotaService::class);
        $remaining = $service->remainingBytes($org);

        $quotaBytes = 1 * 1024 * 1024 * 1024;
        $usedBytes = 100_000 * 1024;
        $expected = $quotaBytes - $usedBytes;

        $this->assertSame($expected, $remaining);
    }

    public function test_usage_summary_includes_breakdown(): void
    {
        $org = $this->createOrganization(['storage_quota_gb' => 5]);
        $user = $this->createUser();

        Repository::create([
            'id'              => (string) Str::uuid(),
            'organization_id' => $org->id,
            'owner_id'        => $user->id,
            'name'            => 'Game Assets',
            'slug'            => 'game-assets',
            'size_kb'         => 50_000,
            'lfs_size_kb'     => 200_000,
        ]);

        $service = app(StorageQuotaService::class);
        $summary = $service->usageSummary($org);

        $this->assertSame(5, $summary['quota_gb']);
        $this->assertArrayHasKey('used_bytes', $summary);
        $this->assertArrayHasKey('used_human', $summary);
        $this->assertArrayHasKey('usage_percent', $summary);
        $this->assertCount(1, $summary['repositories']);
        $this->assertSame('game-assets', $summary['repositories'][0]['slug']);
        $this->assertSame(250_000, $summary['repositories'][0]['total_kb']);
    }

    // ── Organization Model ──────────────────────────────────────────────────

    public function test_organization_casts_storage_quota(): void
    {
        $org = new Organization(['storage_quota_gb' => 100]);
        $this->assertSame(100, $org->storage_quota_gb);
    }

    public function test_organization_storage_quota_nullable(): void
    {
        $org = new Organization(['storage_quota_gb' => null]);
        $this->assertNull($org->storage_quota_gb);
    }

    // ── Repository Model ────────────────────────────────────────────────────

    public function test_repository_has_lfs_size_kb(): void
    {
        $repo = new Repository(['lfs_size_kb' => 12345]);
        $this->assertSame(12345, $repo->lfs_size_kb);
    }

    // ── UpdateRepositorySizesCommand ────────────────────────────────────────

    public function test_update_repo_sizes_command_updates_lfs_size(): void
    {
        $org = $this->createOrganization();
        $user = $this->createUser();

        $repo = Repository::create([
            'id'              => (string) Str::uuid(),
            'organization_id' => $org->id,
            'owner_id'        => $user->id,
            'name'            => 'Size Test',
            'slug'            => 'size-test',
            'size_kb'         => 0,
            'lfs_size_kb'     => 0,
        ]);

        // Add some LFS objects
        LfsObject::create([
            'id'            => (string) Str::uuid(),
            'repository_id' => $repo->id,
            'oid'           => str_repeat('a', 64),
            'size'          => 1_048_576, // 1 MB
            'storage_path'  => 'test/path',
        ]);

        LfsObject::create([
            'id'            => (string) Str::uuid(),
            'repository_id' => $repo->id,
            'oid'           => str_repeat('b', 64),
            'size'          => 2_097_152, // 2 MB
            'storage_path'  => 'test/path2',
        ]);

        $this->artisan('crucible:update-repo-sizes', ['--repository' => $repo->id])
            ->assertSuccessful();

        $repo->refresh();

        // LFS size should be ~3 MB = 3072 KB (ceil(3145728 / 1024))
        $this->assertSame(3072, $repo->lfs_size_kb);
    }

    // ── CleanExpiredUploadsCommand ──────────────────────────────────────────

    public function test_clean_uploads_removes_expired_sessions(): void
    {
        $org = $this->createOrganization();
        $user = $this->createUser();

        $repo = Repository::create([
            'id'              => (string) Str::uuid(),
            'organization_id' => $org->id,
            'owner_id'        => $user->id,
            'name'            => 'Upload Test',
            'slug'            => 'upload-test',
        ]);

        // Create an expired session
        LfsUploadSession::create([
            'id'             => (string) Str::uuid(),
            'repository_id'  => $repo->id,
            'oid'            => str_repeat('c', 64),
            'total_size'     => 1000,
            'uploaded_bytes' => 500,
            'expires_at'     => now()->subHour(),
        ]);

        // Create a non-expired session
        LfsUploadSession::create([
            'id'             => (string) Str::uuid(),
            'repository_id'  => $repo->id,
            'oid'            => str_repeat('d', 64),
            'total_size'     => 2000,
            'uploaded_bytes' => 0,
            'expires_at'     => now()->addHours(24),
        ]);

        $this->artisan('crucible:clean-uploads')->assertSuccessful();

        $this->assertSame(1, LfsUploadSession::count());
        $this->assertNull(LfsUploadSession::where('oid', str_repeat('c', 64))->first());
        $this->assertNotNull(LfsUploadSession::where('oid', str_repeat('d', 64))->first());
    }

    // ── Quota Check in LfsService ───────────────────────────────────────────

    public function test_can_upload_to_repository_uses_organization_quota(): void
    {
        $org = $this->createOrganization(['storage_quota_gb' => 1]);
        $user = $this->createUser();

        $repo = Repository::create([
            'id'              => (string) Str::uuid(),
            'organization_id' => $org->id,
            'owner_id'        => $user->id,
            'name'            => 'Quota Repo',
            'slug'            => 'quota-repo',
            'size_kb'         => 0,
            'lfs_size_kb'     => 0,
        ]);

        $service = app(StorageQuotaService::class);

        // Small upload should be fine
        $this->assertTrue($service->canUploadToRepository($repo, 1024));

        // Massive upload should fail
        $this->assertFalse($service->canUploadToRepository($repo, 2 * 1024 * 1024 * 1024)); // 2 GB > 1 GB quota
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
}
