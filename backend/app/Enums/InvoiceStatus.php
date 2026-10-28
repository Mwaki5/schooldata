<?php

namespace App\Enums;

/**
 * The states an invoice can present with. None of these are stored:
 * every one is derived from the immutable ledger rows by the
 * StudentLedgerService, so a status always reflects the live set of
 * invoices, payments and refunds.
 *
 * - `unpaid`          nothing settled yet
 * - `partially_paid`  some, but not all, of the amount is settled
 * - `paid`            fully settled by the oldest-first allocation
 * - `reversed`        the invoice (or reversal row) is excluded from
 *                     allocation entirely
 */
enum InvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Reversed = 'reversed';

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
