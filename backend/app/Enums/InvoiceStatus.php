<?php

namespace App\Enums;

/**
 * The lifecycle states an invoice can hold. Every invoice is raised as
 * pending; the finance desk flips it to paid when a settlement lands
 * and voids one that must not be collected. Payments themselves are
 * recorded against the invoice when that module is built.
 */
enum InvoiceStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Void = 'void';

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
