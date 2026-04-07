<?php

namespace App\Enums;

enum LockMode: string
{
    case Mandatory = 'mandatory';
    case Advisory = 'advisory';

    public function label(): string
    {
        return match ($this) {
            self::Mandatory => 'Mandatory',
            self::Advisory  => 'Advisory',
        };
    }
}
