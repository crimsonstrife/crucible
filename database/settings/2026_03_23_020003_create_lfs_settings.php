<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('lfs.enabled', true);
        $this->migrator->add('lfs.storageDisk', 'local');
        $this->migrator->add('lfs.maxObjectSizeMb', 2048);
    }
};
