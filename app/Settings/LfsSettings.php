<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class LfsSettings extends Settings
{
    public bool $enabled;
    public string $storageDisk;
    public int $maxObjectSizeMb;

    public static function group(): string
    {
        return 'lfs';
    }
}
