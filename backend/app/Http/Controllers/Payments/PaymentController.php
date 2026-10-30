<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Concerns\PicksStudents;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\ReceivePaymentRequest;
use App\Http\Requests\Payments\ReversePaymentRequest;
use App\Http\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Finance\PaymentService;
use App\Services\Finance\StudentLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The cash desk: take a payment in, reverse one by inserting a linked
 * negative row, and read a student's derived balance with a live
 * preview of how a payment would settle their invoices.
 *
 * There is deliberately no update and no destroy here — the ledger is
 * write-once, and no allocation rows exist anywhere: what a payment
 * settles is always derived, never stored.
 */
class PaymentController extends Controller
{
    use PicksStudents;

    public function __construct(
        private readonly PaymentService $payments,
        private readonly StudentLedgerService $ledger,
    ) {}

    /**
     * Record one payment. The date is the server's day unless a
     * holder of `payments.backdate` sends one; the receipt number and
     * the student's post-payment balance ride the response.
     */
    public function store(ReceivePaymentRequest $request): JsonResponse
    {
        $payment = $this->payments->receivePayment($request->user(), $request->validated());

        $payment->load(['student.user', 'academicSession']);

        return response()->json([
            'payment' => new PaymentResource($payment),
            'balance' => $this->ledger->summary((int) $payment->student_id),
        ], Response::HTTP_CREATED);
    }

    /**
     * Reverse a payment by inserting the linked negative row; the
     * original is never touched, and invoice statuses re-derive on
     * their own. An already-reversed payment answers 409 through the
     * LedgerException handler.
     */
    public function reverse(ReversePaymentRequest $request, Payment $payment): JsonResponse
    {
        $reversal = $this->payments->reversePayment($request->user(), $payment, $request->validated());

        $reversal->load(['student.user', 'academicSession']);

        return response()->json([
            'payment' => new PaymentResource($reversal),
            'balance' => $this->ledger->summary((int) $reversal->student_id),
        ], Response::HTTP_CREATED);
    }

    /**
     * The student picker for the cash desk's screens. The finance
     * desk holds `payments.receive` rather than `students.view`, so
     * the picker rides the payment permission instead of the roster.
     */
    public function students(Request $request): JsonResponse
    {
        return $this->pickStudents($request);
    }

    /**
     * One student's payments, newest first, each carrying whether a
     * reversal already points at it — the reverse-payment desk reads
     * its row set from here, and `reversed_by` / a `PAYMENT_REVERSAL`
     * entry kind is what tells the client to disable its Reverse
     * action.
     */
    public function forStudent(StudentProfile $student): JsonResponse
    {
        $payments = Payment::query()
            ->where('student_id', $student->id)
            ->with(['student.user', 'academicSession', 'reversedBy', 'reverses'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'payments' => PaymentResource::collection($payments),
        ]);
    }

    /**
     * A student's derived balance with the invoices it is owed on,
     * and — when `preview_amount` rides along — how this payment
     * would settle them, oldest first, with any excess shown as the
     * credit it would become. The whole preview comes from the same
     * derivation every other screen reads; nothing is re-computed
     * client-side.
     */
    public function balance(Request $request, StudentProfile $student): JsonResponse
    {
        $data = $request->validate([
            'preview_amount' => ['nullable', 'numeric', 'min:0.01', 'decimal:0,2'],
        ]);

        $previewAmount = isset($data['preview_amount'])
            ? round((float) $data['preview_amount'], 2)
            : null;

        // With a preview amount, the "before" and "after" ledgers come
        // from ONE load of the rows; without one, only the live ledger
        // is derived.
        if ($previewAmount === null) {
            $ledger = $this->ledger->forStudent($student->id);
        } else {
            ['before' => $ledger, 'after' => $after] = $this->ledger->beforeAndAfter(
                $student->id,
                addedPayments: [['amount' => $previewAmount]],
            );
        }

        $payload = [
            'student' => $this->studentPayload($student),
            'total_invoiced' => $ledger['total_invoiced'],
            'net_payments' => $ledger['net_payments'],
            'balance' => $ledger['balance'],
            'arrears' => $ledger['arrears'],
            'credit' => $ledger['credit'],
            'outstanding_invoices' => collect($ledger['invoices'])
                ->filter(fn (array $invoice): bool => $invoice['outstanding'] > 0)
                ->values(),
        ];

        if ($previewAmount !== null) {
            $beforePaid = collect($ledger['invoices'])->pluck('paid', 'id');

            $payload['preview'] = [
                'amount' => $previewAmount,
                'applied' => collect($after['invoices'])
                    ->map(fn (array $invoice): ?array => [
                        'invoice_number' => $invoice['invoice_number'],
                        'amount' => round($invoice['paid'] - (float) $beforePaid->get($invoice['id'], 0), 2),
                    ])
                    ->filter(fn (?array $application): bool => $application['amount'] > 0)
                    ->values(),
                'credit_after' => $after['credit'],
            ];
        }

        return response()->json($payload);
    }

    /**
     * The effect of reversing this payment, computed by the derived
     * ledger: the balance before and after, and which invoices settle
     * back to outstanding. Nothing hypothetical is persisted.
     */
    public function reversalPreview(Payment $payment): JsonResponse
    {
        ['before' => $before, 'after' => $after] = $this->ledger->beforeAndAfter(
            (int) $payment->student_id,
            excludedPaymentIds: [$payment->id],
        );

        $beforeById = collect($before['invoices'])->keyBy('id');

        return response()->json([
            'receipt_number' => $payment->receipt_number,
            'amount' => (float) $payment->amount,
            'balance_before' => $before['balance'],
            'balance_after' => $after['balance'],
            'reopened_invoices' => collect($after['invoices'])
                ->filter(fn (array $invoice): bool => ($beforeById->get($invoice['id'])['status'] ?? null) === 'paid'
                    && $invoice['outstanding'] > 0)
                ->pluck('invoice_number')
                ->values(),
        ]);
    }

    /**
     * The student the balance belongs to.
     *
     * @return array{id: int, name: string, login_id: string|null}
     */
    private function studentPayload(StudentProfile $student): array
    {
        return [
            'id' => $student->id,
            'name' => User::composeName($student->first_name, $student->middle_name, $student->last_name)
                ?: ($student->user?->name ?? ''),
            'login_id' => $student->user?->login_id,
        ];
    }
}
