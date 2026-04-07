<?php

namespace App\Enums;

enum ReviewState: string
{
    case Pending          = 'pending';
    case Approved         = 'approved';
    case ChangesRequested = 'changes_requested';
    case Commented        = 'commented';

    public function label(): string
    {
        return match ($this) {
            self::Pending          => 'Pending',
            self::Approved         => 'Approved',
            self::ChangesRequested => 'Changes Requested',
            self::Commented        => 'Commented',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending          => 'text-bg-secondary',
            self::Approved         => 'text-bg-success',
            self::ChangesRequested => 'text-bg-warning',
            self::Commented        => 'text-bg-info',
        };
    }

    public function iconName(): string
    {
        return match ($this) {
            self::Pending          => 'clock',
            self::Approved         => 'check-circle',
            self::ChangesRequested => 'x-circle',
            self::Commented        => 'comment',
        };
    }
}
