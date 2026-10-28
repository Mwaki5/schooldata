<?php

namespace App\Enums;

/**
 * The two rows a refunds table holds. A `refund` pays held credit back
 * out to the student with a positive amount and a mandatory reason; a
 * `refund_reversal` cancels one specific refund with a matching
 * negative amount and its own mandatory reason — the correction path
 * for a refund paid to the wrong student, in the wrong amount or
 * twice. Nothing else ever enters the ledger, and nothing is ever
 * edited or deleted.
 */
enum RefundEntryKind: string
{
    case Refund = 'REFUND';
    case RefundReversal = 'REFUND_REVERSAL';
}
