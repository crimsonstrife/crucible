<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GitBackendSettings extends Settings
{
    public string $backend;
    public string $serviceUrl;
    public ?string $serviceToken;
    public string $reposPath;
    public string $defaultBranch;
    public int $maxRepoSizeMb;

    public static function group(): string
    {
        return 'git';
    }

    public static function encrypted(): array
    {
        return ['serviceToken'];
    }
}
