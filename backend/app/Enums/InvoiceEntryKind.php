<?php

namespace App\Enums;

/**
 * The two rows an invoice table holds. An `invoice` charges a positive
 * amount; a `reversal` cancels one specific invoice with a matching
 * negative amount and a mandatory reason. Nothing else ever enters the
 * ledger, and nothing is ever edited or deleted.
 */
enum InvoiceEntryKind: string
{
    case Invoice = 'INVOICE';
    case Reversal = 'REVERSAL';
}
