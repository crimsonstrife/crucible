<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('forge_integration.enabled', false);
        $this->migrator->add('forge_integration.url', '');
        $this->migrator->addEncrypted('forge_integration.token', null);
        $this->migrator->add('forge_integration.clientId', '');
        $this->migrator->addEncrypted('forge_integration.clientSecret', null);
        $this->migrator->add('forge_integration.redirectUri', '');
    }
};
