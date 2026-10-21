<?php

namespace App\Enums;

/**
 * The two papers a unit can be graded on. A unit is fully graded once
 * three of each have been recorded, but fewer may be awarded.
 */
enum UnitExamType: string
{
    case Theory = 'theory';
    case Practical = 'practical';

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
