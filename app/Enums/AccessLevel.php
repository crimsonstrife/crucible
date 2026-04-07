<?php

namespace App\Enums;

enum AccessLevel: int
{
    case View    = 10;
    case Comment = 20;
    case Edit    = 30;
    case Delete  = 40;
    case Manage  = 50;

    public function allows(string $ability): bool
    {
        return match(strtolower($ability)) {
            'view'    => $this->value >= self::View->value,
            'comment' => $this->value >= self::Comment->value,
            'edit',
            'update'  => $this->value >= self::Edit->value,
            'delete'  => $this->value >= self::Delete->value,
            'manage'  => $this->value >= self::Manage->value,
            default   => false,
        };
    }

    public static function max(?self $a, ?self $b): ?self
    {
        if ($a === null) {
            return $b;
        }
        if ($b === null) {
            return $a;
        }

        return $a->value >= $b->value ? $a : $b;
    }
}
