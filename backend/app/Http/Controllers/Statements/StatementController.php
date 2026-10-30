<?php

namespace App\Http\Controllers\Statements;

use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Finance\StudentLedgerService;
use Illuminate\Http\JsonResponse;

/**
 * The read-only student statement: every ledger row in chronological
 * order with a running balance, plus the closing figure. It reconciles
 * exactly with the derived balance by construction — both come from
 * the one StudentLedgerService over the same immutable rows.
 */
class StatementController extends Controller
{
    public function __construct(
        private readonly StudentLedgerService $ledger,
    ) {}

    /**
     * One student's statement of account.
     *
     * Alongside the chronological entries it carries the per-invoice
     * figures (paid, outstanding, status) from the same derivation, so
     * a statement-only reader sees each invoice's live status without
     * needing the invoices desk's permission.
     */
    public function __invoke(StudentProfile $student): JsonResponse
    {
        ['ledger' => $ledger, 'statement' => $statement] = $this->ledger->ledgerAndStatement($student->id);

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => User::composeName($student->first_name, $student->middle_name, $student->last_name)
                    ?: ($student->user?->name ?? ''),
                'login_id' => $student->user?->login_id,
            ],
            'total_invoiced' => $ledger['total_invoiced'],
            'net_payments' => $ledger['net_payments'],
            'balance' => $ledger['balance'],
            'invoices' => $ledger['invoices'],
            'reversed_invoices' => $ledger['reversed_invoices'],
            'entries' => $statement['entries'],
            'closing_balance' => $statement['closing_balance'],
            'arrears' => $statement['arrears'],
            'credit' => $statement['credit'],
        ]);
    }
}
