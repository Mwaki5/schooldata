<?php

namespace App\Enums;

/**
 * What an invoice line charges for. The kind is a fixed vocabulary so
 * billing can be reported on later; the free-text description on each
 * line carries the specifics ("Term 2 tuition", "Hostel Block A").
 */
enum InvoiceItemType: string
{
    case Fee = 'fee';
    case Penalty = 'penalty';
    case Hostel = 'hostel';
    case Transport = 'transport';
    case Exam = 'exam';
    case Other = 'other';

    /**
     * The line kinds as plain strings, for validation.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }
}
