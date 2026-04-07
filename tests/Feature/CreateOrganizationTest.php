<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizations_can_be_created(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('organizations.store'), [
            'name'        => 'Helical Games',
            'website_url' => 'https://helicalgames.net',
        ]);

        $organization = Organization::firstOrFail();
        $membership = OrganizationMember::firstOrFail();

        $response->assertRedirect(route('organizations.show', $organization));

        $this->assertDatabaseHas('organizations', [
            'id'          => $organization->id,
            'name'        => 'Helical Games',
            'slug'        => 'helical-games',
            'website_url' => 'https://helicalgames.net',
        ]);

        $this->assertDatabaseHas('organization_members', [
            'id'              => $membership->id,
            'organization_id' => $organization->id,
            'user_id'         => $user->id,
            'role'            => 'owner',
        ]);
    }

    public function test_organization_pages_can_be_viewed_by_members(): void
    {
        $user = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Helical Games',
            'slug' => 'helical-games',
        ]);

        $organization->members()->attach($user->id, ['role' => 'owner']);

        $response = $this->actingAs($user)->get(route('organizations.show', $organization));

        $response->assertOk();
        $response->assertSee('Helical Games');
    }
}
