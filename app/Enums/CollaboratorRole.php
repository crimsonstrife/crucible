<?php

namespace App\Enums;

enum CollaboratorRole: string
{
    case Read     = 'read';
    case Write    = 'write';
    case Maintain = 'maintain';
    case Admin    = 'admin';

    /**
     * Numeric rank used for hierarchy comparisons.
     * Keeps the string DB values stable while allowing >= checks.
     */
    public function rank(): int
    {
        return match($this) {
            CollaboratorRole::Read     => 10,
            CollaboratorRole::Write    => 20,
            CollaboratorRole::Maintain => 30,
            CollaboratorRole::Admin    => 40,
        };
    }

    public function label(): string
    {
        return match($this) {
            CollaboratorRole::Read     => 'Read',
            CollaboratorRole::Write    => 'Write',
            CollaboratorRole::Maintain => 'Maintain',
            CollaboratorRole::Admin    => 'Admin',
        };
    }

    public function canWrite(): bool
    {
        return in_array($this, [self::Write, self::Maintain, self::Admin]);
    }

    public function canMaintain(): bool
    {
        return in_array($this, [self::Maintain, self::Admin]);
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }
}
