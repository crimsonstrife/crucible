<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ForgeIntegrationSettings extends Settings
{
    public bool $enabled;
    public string $url;
    public ?string $token;
    public string $clientId;
    public ?string $clientSecret;
    public string $redirectUri;

    public static function group(): string
    {
        return 'forge_integration';
    }

    public static function encrypted(): array
    {
        return ['token', 'clientSecret'];
    }
}
