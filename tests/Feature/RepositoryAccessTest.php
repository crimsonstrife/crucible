<?php

namespace Tests\Feature;

use App\Enums\RepositoryVisibility;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_can_be_created(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $organization = Organization::factory()->create(['owner_id' => $user->id]);

        $repository = Repository::create([
            'name' => 'Test Repository',
            'slug' => 'test-repository',
            'description' => 'A test repository',
            'organization_id' => $organization->id,
            'visibility' => RepositoryVisibility::Private,
        ]);

        $this->assertDatabaseHas('repositories', [
            'id' => $repository->id,
            'name' => 'Test Repository',
            'organization_id' => $organization->id,
        ]);
    }

    public function test_repository_belongs_to_organization(): void
    {
        $repository = Repository::factory()->create();

        $this->assertInstanceOf(Organization::class, $repository->organization);
    }

    public function test_public_repository_can_be_accessed_by_anyone(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $organization = Organization::factory()->create(['owner_id' => $owner->id]);
        $repository = Repository::factory()->public()->create(['organization_id' => $organization->id]);

        $randomUser = User::factory()->withPersonalTeam()->create();

        $this->assertTrue($repository->canAccess($randomUser));
    }

    public function test_private_repository_cannot_be_accessed_by_non_members(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $organization = Organization::factory()->create(['owner_id' => $owner->id]);
        $repository = Repository::factory()->private()->create(['organization_id' => $organization->id]);

        $randomUser = User::factory()->withPersonalTeam()->create();

        $this->assertFalse($repository->canAccess($randomUser));
    }

    public function test_private_repository_can_be_accessed_by_org_owner(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $organization = Organization::factory()->create(['owner_id' => $owner->id]);
        $repository = Repository::factory()->private()->create(['organization_id' => $organization->id]);

        $this->assertTrue($repository->canAccess($owner));
    }

    public function test_repository_can_have_members(): void
    {
        $repository = Repository::factory()->create();
        $member = User::factory()->withPersonalTeam()->create();

        $repository->members()->attach($member->id, ['role' => 'write']);

        $this->assertTrue($repository->hasMember($member));
    }

    public function test_visibility_enum_values(): void
    {
        $this->assertEquals('public', RepositoryVisibility::Public->value);
        $this->assertEquals('private', RepositoryVisibility::Private->value);
        $this->assertEquals('internal', RepositoryVisibility::Internal->value);
    }
}
