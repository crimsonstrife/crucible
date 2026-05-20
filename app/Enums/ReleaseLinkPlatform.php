<?php

namespace App\Enums;

/**
 * Known external platforms a release can link to. Drives the UI icon and label;
 * 'Other' is the catch-all for anything not in this list.
 *
 * Why an enum: keeps the set canonical so we can render the right icon and
 * group/filter in admin UIs. The DB column is nullable string so future
 * platforms can be added (or stored as a free string under 'other') without
 * blocking inserts on an enum migration.
 */
enum ReleaseLinkPlatform: string
{
    case Steam      = 'steam';
    case Itch       = 'itch';
    case GameJolt   = 'gamejolt';
    case Patreon    = 'patreon';
    case Discord    = 'discord';
    case YouTube    = 'youtube';
    case Twitter    = 'twitter';
    case Website    = 'website';
    case Other      = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Steam    => 'Steam',
            self::Itch     => 'itch.io',
            self::GameJolt => 'GameJolt',
            self::Patreon  => 'Patreon',
            self::Discord  => 'Discord',
            self::YouTube  => 'YouTube',
            self::Twitter  => 'X / Twitter',
            self::Website  => 'Website',
            self::Other    => 'Other',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Steam    => 'bi-steam',
            self::Itch     => 'bi-controller',
            self::GameJolt => 'bi-joystick',
            self::Patreon  => 'bi-heart',
            self::Discord  => 'bi-discord',
            self::YouTube  => 'bi-youtube',
            self::Twitter  => 'bi-twitter-x',
            self::Website  => 'bi-globe',
            self::Other    => 'bi-link-45deg',
        };
    }
}
