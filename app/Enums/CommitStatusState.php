<?php

namespace App\Enums;

enum CommitStatusState: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failure = 'failure';
    case Error   = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Success => 'Success',
            self::Failure => 'Failure',
            self::Error   => 'Error',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-yellow-100 text-yellow-800',
            self::Success => 'bg-green-100 text-green-800',
            self::Failure => 'bg-red-100 text-red-800',
            self::Error   => 'bg-red-100 text-red-800',
        };
    }

    public function isPassed(): bool
    {
        return $this === self::Success;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Success, self::Failure, self::Error], true);
    }
}
