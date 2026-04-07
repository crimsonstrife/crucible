<?php

namespace App\Enums;

enum PullRequestStatus: string
{
    case Open   = 'open';
    case Merged = 'merged';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open   => 'Open',
            self::Merged => 'Merged',
            self::Closed => 'Closed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open   => 'text-bg-success',
            self::Merged => 'text-bg-primary',
            self::Closed => 'text-bg-secondary',
        };
    }

    public function iconName(): string
    {
        return match ($this) {
            self::Open   => 'git-pull-request',
            self::Merged => 'git-merge',
            self::Closed => 'git-pull-request-closed',
        };
    }
}
