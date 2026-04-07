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
            RepositoryVisibility::Public => 'Public',
            RepositoryVisibility::Private => 'Private',
            RepositoryVisibility::Internal => 'Internal',
        };
    }

    public function description(): string
    {
        return match ($this) {
            RepositoryVisibility::Public => 'Anyone can view and clone. Pushes still require an authorized account.',
            RepositoryVisibility::Private => 'Only explicitly authorized users can view or clone. Pushes still require authorization.',
            RepositoryVisibility::Internal => 'All organization members can view and clone. Pushes still require authorization.',
        };
    }
}
