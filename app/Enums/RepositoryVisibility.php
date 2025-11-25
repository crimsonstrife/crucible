<?php

namespace App\Enums;

enum RepositoryVisibility: string
{
    case Public = 'public';
    case Private = 'private';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Private => 'Private',
            self::Internal => 'Internal (Organization only)',
        };
    }
}
