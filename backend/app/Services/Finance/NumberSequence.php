<?php

namespace App\Services\Finance;

use Illuminate\Database\Eloquent\Model;

/**
 * Sequential document numbering for the finance ledger: INV-2026-0007,
 * RCP-2026-0042, RFD-2026-0003.
 *
 * The next number is read with a row lock on the latest issued number,
 * inside the caller's write transaction, so two concurrent documents
 * can never take the same number. Ledger rows are never deleted, so a
 * number is never reissued.
 */
class NumberSequence
{
    /**
     * The next sequential number for the current year under the given
     * prefix, padded to four digits.
     */
    public function next(string $prefix, Model $model, string $column): string
    {
        $yearPrefix = $prefix.'-'.now()->format('Y').'-';

        $latest = $model->newQuery()
            ->where($column, 'like', $yearPrefix.'%')
            ->lockForUpdate()
            ->orderByDesc($column)
            ->value($column);

        $sequence = $latest === null
            ? 1
            : ((int) substr($latest, strlen($yearPrefix))) + 1;

        return $yearPrefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
