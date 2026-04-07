<?php

namespace Tests\Feature;

use App\Models\FileLock;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FileLockApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lock_creation_returns_the_current_user_as_owner(): void
    {
        [$owner, $repository] = $this->createRepository();

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/{$repository->slug}/locks", [
            'path' => 'Content/Maps/Level01.umap',
            'ref' => 'refs/heads/main',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('lock.owner.name', $owner->name);
        $response->assertJsonPath('lock.owner.identifier', $owner->email);
        $response->assertJsonPath('lock.owner.external', false);
    }

    public function test_lock_listing_marks_missing_users_as_external_and_preserves_identifier(): void
    {
        [$owner, $repository] = $this->createRepository();

        Sanctum::actingAs($owner);

        FileLock::create([
            'repository_id' => $repository->id,
            'locked_by' => null,
            'owner_name' => 'Forge Import',
            'owner_identifier' => 'forge:user-42',
            'owner_is_external' => true,
            'path' => 'Content/Characters/Hero.uasset',
            'locked_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/{$repository->slug}/locks");

        $response->assertOk();
        $response->assertJsonPath('locks.0.owner.name', 'Forge Import');
        $response->assertJsonPath('locks.0.owner.identifier', 'forge:user-42');
        $response->assertJsonPath('locks.0.owner.external', true);
    }

    public function test_verify_splits_our_locks_from_external_or_other_users(): void
    {
        [$owner, $repository] = $this->createRepository();
        $otherUser = User::factory()->create();

        Sanctum::actingAs($owner);

        FileLock::create([
            'repository_id' => $repository->id,
            'locked_by' => $owner->id,
            'owner_name' => $owner->name,
            'owner_identifier' => $owner->email,
            'owner_is_external' => false,
            'path' => 'Content/Ours.asset',
            'locked_at' => now(),
        ]);

        FileLock::create([
            'repository_id' => $repository->id,
            'locked_by' => $otherUser->id,
            'owner_name' => $otherUser->name,
            'owner_identifier' => $otherUser->email,
            'owner_is_external' => false,
            'path' => 'Content/Theirs.asset',
            'locked_at' => now()->addSecond(),
        ]);

        FileLock::create([
            'repository_id' => $repository->id,
            'locked_by' => null,
            'owner_name' => 'External Artist',
            'owner_identifier' => 'vendor:artist-17',
            'owner_is_external' => true,
            'path' => 'Content/External.asset',
            'locked_at' => now()->addSeconds(2),
        ]);

        $response = $this->postJson("/api/v1/{$repository->slug}/locks/verify");

        $response->assertOk();
        $response->assertJsonCount(1, 'ours');
        $response->assertJsonCount(2, 'theirs');
        $response->assertJsonPath('theirs.1.owner.external', true);
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

        return [$owner, $repository];
    }
}
