<?php

namespace App\Http\Controllers\Refunds;

use App\Http\Controllers\Controller;
use App\Http\Requests\Refunds\ReverseRefundRequest;
use App\Http\Requests\Refunds\StoreRefundRequest;
use App\Http\Resources\Refunds\RefundResource;
use App\Models\Refund;
use App\Models\StudentProfile;
use App\Services\Finance\PaymentService;
use App\Services\Finance\StudentLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Refunding held credit back out to a student, and reversing a refund
 * that was paid in error by inserting the linked negative row. Allowed
 * only up to the student's current credit — the service derives that
 * credit from the ledger and refuses anything beyond it — and
 * deliberately restricted to the roles granted `refunds.create`, which
 * the finance bundle does not carry by default. Reversing a refund is
 * the same restricted verb: it puts money back into the student's held
 * credit, so it belongs on the same desk.
 */
class RefundController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly StudentLedgerService $ledger,
    ) {}

    /**
     * Pay credit back out; the refund number and the student's
     * post-refund balance ride the response.
     */
    public function store(StoreRefundRequest $request): JsonResponse
    {
        $refund = $this->payments->refundCredit($request->user(), $request->validated());

        $refund->load(['student.user', 'academicSession']);

        $ledger = $this->ledger->forStudent((int) $refund->student_id);

        return response()->json([
            'refund' => new RefundResource($refund),
            'balance' => [
                'total_invoiced' => $ledger['total_invoiced'],
                'net_payments' => $ledger['net_payments'],
                'balance' => $ledger['balance'],
                'arrears' => $ledger['arrears'],
                'credit' => $ledger['credit'],
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Reverse a refund by inserting the linked negative row; the
     * original is never touched, and the student's credit is restored
     * because the ledger re-derives from every row. An already-reversed
     * refund answers 409 through the LedgerException handler.
     */
    public function reverse(ReverseRefundRequest $request, Refund $refund): JsonResponse
    {
        $reversal = $this->payments->reverseRefund($request->user(), $refund, $request->validated());

        $reversal->load(['student.user', 'academicSession', 'reverses']);

        $ledger = $this->ledger->forStudent((int) $reversal->student_id);

        return response()->json([
            'refund' => new RefundResource($reversal),
            'balance' => [
                'total_invoiced' => $ledger['total_invoiced'],
                'net_payments' => $ledger['net_payments'],
                'balance' => $ledger['balance'],
                'arrears' => $ledger['arrears'],
                'credit' => $ledger['credit'],
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * The effect of reversing this refund, computed by the derived
     * ledger: the balance and held credit before and after. Reversing
     * money that left restores the credit it had cancelled. Nothing
     * hypothetical is persisted.
     */
    public function reversalPreview(Refund $refund): JsonResponse
    {
        $before = $this->ledger->forStudent((int) $refund->student_id);
        $after = $this->ledger->hypothetical(
            (int) $refund->student_id,
            addedRefunds: [['amount' => -1 * round((float) $refund->amount, 2)]],
        );

        return response()->json([
            'refund_number' => $refund->refund_number,
            'amount' => (float) $refund->amount,
            'balance_before' => $before['balance'],
            'balance_after' => $after['balance'],
            'credit_before' => $before['credit'],
            'credit_after' => $after['credit'],
        ]);
    }

    /**
     * One student's refunds, newest first, each carrying whether a
     * reversal already points at it — the reverse-refund desk reads its
     * row set from here, and `reversed_by` / a `REFUND_REVERSAL` entry
     * kind is what tells the client to disable its Reverse action.
     */
    public function forStudent(StudentProfile $student): JsonResponse
    {
        $refunds = Refund::query()
            ->where('student_id', $student->id)
            ->with(['student.user', 'academicSession', 'reversedBy', 'reverses'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'refunds' => RefundResource::collection($refunds),
        ]);
    }
}
