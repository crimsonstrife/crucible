<?php

namespace App\Enums;

enum ReleaseCategory: string
{
    case New        = 'new';
    case Improved   = 'improved';
    case Fixed      = 'fixed';
    case Removed    = 'removed';
    case Security   = 'security';
    case Deprecated = 'deprecated';

    public function label(): string
    {
        return match ($this) {
            ReleaseCategory::New        => 'New',
            ReleaseCategory::Improved   => 'Improved',
            ReleaseCategory::Fixed      => 'Fixed',
            ReleaseCategory::Removed    => 'Removed',
            ReleaseCategory::Security   => 'Security',
            ReleaseCategory::Deprecated => 'Deprecated',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            ReleaseCategory::New        => 'heroicon-o-sparkles',
            ReleaseCategory::Improved   => 'heroicon-o-arrow-trending-up',
            ReleaseCategory::Fixed      => 'heroicon-o-wrench-screwdriver',
            ReleaseCategory::Removed    => 'heroicon-o-minus-circle',
            ReleaseCategory::Security   => 'heroicon-o-shield-check',
            ReleaseCategory::Deprecated => 'heroicon-o-exclamation-triangle',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            ReleaseCategory::New        => 10,
            ReleaseCategory::Improved   => 20,
            ReleaseCategory::Fixed      => 30,
            ReleaseCategory::Security   => 40,
            ReleaseCategory::Deprecated => 50,
            ReleaseCategory::Removed    => 60,
        };
    }

    /**
     * SQL `CASE … END` expression that maps the `category` column to its
     * semantic sort order. Safe to pass to `orderByRaw()` — the values come
     * from enum cases (trusted input), not user data.
     */
    public static function sortOrderSql(string $column = 'category'): string
    {
        $cases = collect(self::cases())
            ->map(fn (self $c) => "WHEN '{$c->value}' THEN {$c->sortOrder()}")
            ->implode(' ');

        return "CASE {$column} {$cases} END";
    }
}
