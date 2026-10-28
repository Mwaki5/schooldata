<?php

namespace App\Enums;

/**
 * The two rows a payments table holds. A `payment` records money in
 * with a positive amount; a `payment_reversal` cancels one specific
 * payment with a matching negative amount and a mandatory reason — the
 * correction path for a bounced cheque, a wrong student or a duplicate
 * entry. Nothing else ever enters the ledger, and nothing is ever
 * edited or deleted.
 */
enum PaymentEntryKind: string
{
    case Payment = 'PAYMENT';
    case PaymentReversal = 'PAYMENT_REVERSAL';
}
