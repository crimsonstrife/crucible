<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Global admin permissions ───────────────────────────────────────
        $isSuperAdmin = Permission::firstOrCreate(['name' => 'is-super-admin', 'guard_name' => 'web']);
        $isAdmin      = Permission::firstOrCreate(['name' => 'is-admin',       'guard_name' => 'web']);

        // ── Organization permissions ───────────────────────────────────────
        Permission::firstOrCreate(['name' => 'org.view',   'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'org.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'org.update', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'org.delete', 'guard_name' => 'web']);

        // ── Repository permissions ─────────────────────────────────────────
        Permission::firstOrCreate(['name' => 'repo.view',          'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'repo.create',        'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'repo.update',        'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'repo.delete',        'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'repo.push',          'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'repo.branch',        'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'repo.lfs.upload',    'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'repo.lfs.download',  'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'repo.lock.acquire',  'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'repo.lock.release',  'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'repo.lock.force',    'guard_name' => 'web']);

        // ── SSH key permissions ────────────────────────────────────────────
        Permission::firstOrCreate(['name' => 'ssh-key.manage', 'guard_name' => 'web']);

        // ── Roles ──────────────────────────────────────────────────────────
        $roleSuperAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $roleSuperAdmin->syncPermissions([$isSuperAdmin, $isAdmin]);

        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $roleAdmin->syncPermissions([
            $isAdmin,
            'org.view', 'org.create', 'org.update', 'org.delete',
            'repo.view', 'repo.create', 'repo.update', 'repo.delete',
            'repo.push', 'repo.branch',
            'repo.lfs.upload', 'repo.lfs.download',
            'repo.lock.acquire', 'repo.lock.release', 'repo.lock.force',
            'ssh-key.manage',
        ]);

        $roleDeveloper = Role::firstOrCreate(['name' => 'developer', 'guard_name' => 'web']);
        $roleDeveloper->syncPermissions([
            'org.view',
            'repo.view', 'repo.create', 'repo.update',
            'repo.push', 'repo.branch',
            'repo.lfs.upload', 'repo.lfs.download',
            'repo.lock.acquire', 'repo.lock.release',
            'ssh-key.manage',
        ]);

        $roleViewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $roleViewer->syncPermissions([
            'org.view',
            'repo.view',
            'repo.lfs.download',
        ]);

        $this->command->info('  Roles: super-admin, admin, developer, viewer');
        $this->command->info('  Permissions: ' . Permission::count() . ' total');
    }
}
