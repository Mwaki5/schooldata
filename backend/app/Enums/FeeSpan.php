<?php

namespace App\Enums;

/**
 * How often an assigned fee structure charges: once per academic
 * session, or once per year. Stored on the course fee assignment —
 * the same structure may charge either way depending on the course
 * it is assigned to.
 */
enum FeeSpan: string
{
    case Session = 'session';
    case Year = 'year';

    /**
     * The values stored in the database.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Human-readable label for the span.
     */
    public function label(): string
    {
        return match ($this) {
            self::Session => 'Per Session',
            self::Year => 'Per Year',
        };
    }
}
