<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_can_be_created(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $organization = Organization::create([
            'name' => 'Test Organization',
            'slug' => 'test-organization',
            'description' => 'A test organization',
            'owner_id' => $user->id,
        ]);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Test Organization',
            'owner_id' => $user->id,
        ]);
    }

    public function test_organization_has_owner(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $organization = Organization::factory()->create(['owner_id' => $user->id]);

        $this->assertTrue($organization->isOwner($user));
    }

    public function test_organization_can_have_members(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->withPersonalTeam()->create();

        $organization = Organization::factory()->create(['owner_id' => $owner->id]);
        $organization->users()->attach($member->id, ['role' => 'member']);

        $this->assertTrue($organization->hasMember($member));
        $this->assertFalse($organization->hasMember($owner));
    }

    public function test_user_can_have_multiple_organizations(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $org1 = Organization::factory()->create(['owner_id' => $user->id]);
        $org2 = Organization::factory()->create(['owner_id' => $user->id]);

        $this->assertEquals(2, $user->ownedOrganizations()->count());
    }
}
