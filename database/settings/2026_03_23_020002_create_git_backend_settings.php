<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('git.backend', 'stub');
        $this->migrator->add('git.serviceUrl', '');
        $this->migrator->addEncrypted('git.serviceToken', null);
        $this->migrator->add('git.reposPath', '');
        $this->migrator->add('git.defaultBranch', 'main');
        $this->migrator->add('git.maxRepoSizeMb', 1024);
    }
};
