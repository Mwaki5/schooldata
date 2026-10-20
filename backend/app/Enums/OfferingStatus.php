<?php

namespace App\Enums;

/**
 * Whether an offering row explicitly admits registrations.
 */
enum OfferingStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    /**
     * The statuses as plain strings, for validation and filters.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}
