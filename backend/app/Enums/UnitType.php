<?php

namespace App\Enums;

/**
 * How a unit functions inside the course it is linked into: a basic
 * unit grounds the trade, a core unit carries it, a common unit is
 * shared across programmes. Stored on the course-unit link, never on
 * the unit itself — the same unit may play different roles in
 * different courses.
 */
enum UnitType: string
{
    case Basic = 'basic';
    case Core = 'core';
    case Common = 'common';

    /**
     * The values stored in the database.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
