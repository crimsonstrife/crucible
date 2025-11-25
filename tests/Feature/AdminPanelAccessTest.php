<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the permission
        Permission::create(['name' => 'access-admin-panel']);
        Role::create(['name' => 'admin'])->givePermissionTo('access-admin-panel');
    }

    public function test_guests_cannot_access_admin_panel(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/admin/login');
    }

    public function test_users_without_permission_cannot_access_admin_panel(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertForbidden();
    }

    public function test_users_with_permission_can_access_admin_panel(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
    }
}
