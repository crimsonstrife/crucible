<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('auth.allowRegistration', false);
        $this->migrator->add('auth.requireEmailVerification', true);
    }
};
