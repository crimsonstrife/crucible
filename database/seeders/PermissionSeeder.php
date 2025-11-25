<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        Permission::create(['name' => 'access-admin-panel']);
        Permission::create(['name' => 'manage-organizations']);
        Permission::create(['name' => 'manage-repositories']);
        Permission::create(['name' => 'manage-users']);

        // Create admin role and assign permissions
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo([
            'access-admin-panel',
            'manage-organizations',
            'manage-repositories',
            'manage-users',
        ]);

        // Create moderator role with limited permissions
        $moderatorRole = Role::create(['name' => 'moderator']);
        $moderatorRole->givePermissionTo([
            'access-admin-panel',
            'manage-organizations',
            'manage-repositories',
        ]);
    }
}
