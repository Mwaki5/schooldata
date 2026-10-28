<?php

namespace App\Services\Finance;

use RuntimeException;

/**
 * A finance ledger rule refused the write — an invoice was already
 * reversed, a payment reversal would push net payments negative, no
 * active session is configured. Controllers answer these with 409
 * Conflict: the request was well-formed, but the ledger's state says
 * no. Validation-shaped problems (bad amounts, unknown students) stay
 * plain ValidationExceptions instead.
 */
class LedgerException extends RuntimeException {}
