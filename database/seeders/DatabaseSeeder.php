<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Always seed roles and permissions first
        $this->call(RolesAndPermissionsSeeder::class);

        // Create the default super-admin account (dev/local environments)
        $email = env('ADMIN_EMAIL', 'admin@crucible.test');

        /** @var User $admin */
        $admin = User::where('email', $email)->first();

        if (! $admin) {
            $admin = User::factory()->withPersonalTeam()->create([
                'name'              => 'Crucible Admin',
                'email'             => $email,
                'password'          => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'email_verified_at' => now(),
            ]);
        }

        $admin->syncRoles(['super-admin']);

        $this->command->info("Admin user ready: {$admin->email}");
    }
}
