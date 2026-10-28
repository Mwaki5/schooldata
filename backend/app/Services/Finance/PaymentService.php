<?php

namespace App\Services\Finance;

use App\Enums\PaymentEntryKind;
use App\Enums\PaymentMethod;
use App\Enums\RefundEntryKind;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every way money enters (and is corrected in) the ledger: receiving a
 * payment, reversing one, and refunding held credit back out.
 *
 * All are write-once — there is no update and no delete anywhere in
 * this service. Each write runs in one transaction, stamps the active
 * session and the acting user server-side, and carries the client's
 * idempotency key so a double click records the money once. Nothing
 * here allocates payments to invoices: that is derived, always, by the
 * StudentLedgerService.
 */
class PaymentService
{
    public function __construct(
        private readonly ActiveSessionResolver $sessions,
        private readonly NumberSequence $numbers,
        private readonly StudentLedgerService $ledger,
    ) {}

    /**
     * Record one payment against a student. The date is the server's
     * day unless a holder of `payments.backdate` sends one — which the
     * activity log records alongside the acting user.
     *
     * @param  array{student_id: int, payment_method: string, reference?: string|null, amount: int|float|string, notes?: string|null, date?: string|null, idempotency_key: string}  $data
     */
    public function receivePayment(User $user, array $data): Payment
    {
        if ($replayed = $this->replayed($data['idempotency_key'])) {
            return $replayed;
        }

        if (! empty($data['date']) && ! $user->can('payments.backdate')) {
            abort(403, 'Backdating a payment requires the payments.backdate permission.');
        }

        try {
            return DB::transaction(function () use ($user, $data): Payment {
                $session = $this->sessions->resolve();
                $student = $this->activeStudent($data['student_id']);
                $reference = $data['reference'] ?? null;

                $this->guardDuplicateReference(
                    Payment::query(),
                    $data['payment_method'],
                    $reference,
                    'reference',
                );

                return Payment::create([
                    'receipt_number' => $this->numbers->next('RCP', new Payment, 'receipt_number'),
                    'student_id' => $student->id,
                    'academic_session_id' => $session->id,
                    'payment_method' => PaymentMethod::from($data['payment_method']),
                    'reference' => $reference,
                    'payment_date' => $data['date'] ?? now()->toDateString(),
                    'amount' => round((float) $data['amount'], 2),
                    'entry_kind' => PaymentEntryKind::Payment,
                    'notes' => $data['notes'] ?? null,
                    'idempotency_key' => $data['idempotency_key'],
                    'created_by' => $user->id,
                ]);
            });
        } catch (QueryException $exception) {
            return $this->replayed($data['idempotency_key'])
                ?? $this->rethrowDuplicateReference($exception, $data, 'reference')
                ?? throw $exception;
        }
    }

    /**
     * Reverse a payment: insert the linked negative row, lock the
     * original while deciding, and never touch the original itself.
     * The unique constraint on the pointer makes a second reversal of
     * the same payment impossible, even under concurrency. Invoice
     * statuses re-derive on their own — nothing is un-applied here.
     *
     * @param  array{reason: string, idempotency_key: string}  $data
     */
    public function reversePayment(User $user, Payment $payment, array $data): Payment
    {
        if ($replayed = $this->replayed($data['idempotency_key'])) {
            return $replayed;
        }

        try {
            return DB::transaction(function () use ($user, $payment, $data): Payment {
                $session = $this->sessions->resolve();

                /** @var Payment $original */
                $original = Payment::query()
                    ->whereKey($payment->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($original->isReversal()) {
                    throw new LedgerException(
                        'Reversal entries cannot themselves be reversed. Reverse the original payment instead.',
                    );
                }

                if ($original->reversedBy()->exists()) {
                    throw new LedgerException('This payment has already been reversed.');
                }

                $this->guardReversalStaysPositive($original);

                return Payment::create([
                    'receipt_number' => $this->numbers->next('RCP', new Payment, 'receipt_number'),
                    'student_id' => $original->student_id,
                    'academic_session_id' => $session->id,
                    'payment_method' => $original->payment_method,
                    // The reference carries the correction marker, so a
                    // bank reconciliation can trace it back.
                    'reference' => mb_substr('REV: '.($original->reference ?? $original->receipt_number), 0, 100),
                    'payment_date' => now()->toDateString(),
                    'amount' => -1 * round((float) $original->amount, 2),
                    'entry_kind' => PaymentEntryKind::PaymentReversal,
                    'reverses_payment_id' => $original->id,
                    'reversal_reason' => $data['reason'],
                    'idempotency_key' => $data['idempotency_key'],
                    'created_by' => $user->id,
                ]);
            });
        } catch (QueryException $exception) {
            if ($payment->reversedBy()->exists()) {
                throw new LedgerException('This payment has already been reversed.');
            }

            return $this->replayed($data['idempotency_key']) ?? throw $exception;
        }
    }

    /**
     * Refund held credit back out to a student, never more than their
     * current credit. A refund is money leaving: it reduces net
     * payments and thereby raises the balance again.
     *
     * @param  array{student_id: int, payment_method: string, reference?: string|null, amount: int|float|string, reason: string, idempotency_key: string}  $data
     */
    public function refundCredit(User $user, array $data): Refund
    {
        if ($replayed = $this->replayedRefund($data['idempotency_key'])) {
            return $replayed;
        }

        try {
            return DB::transaction(function () use ($user, $data): Refund {
                $session = $this->sessions->resolve();
                $student = $this->activeStudent($data['student_id']);
                $amount = round((float) $data['amount'], 2);

                $credit = $this->ledger->forStudent($student->id)['credit'];

                if ($amount > $credit) {
                    throw ValidationException::withMessages([
                        'amount' => "Refund exceeds the student's available credit of KES ".number_format($credit, 2).'.',
                    ]);
                }

                $reference = $data['reference'] ?? null;

                $this->guardDuplicateReference(
                    Refund::query(),
                    $data['payment_method'],
                    $reference,
                    'reference',
                );

                return Refund::create([
                    'refund_number' => $this->numbers->next('RFD', new Refund, 'refund_number'),
                    'student_id' => $student->id,
                    'academic_session_id' => $session->id,
                    'payment_method' => PaymentMethod::from($data['payment_method']),
                    'reference' => $reference,
                    'amount' => $amount,
                    'entry_kind' => RefundEntryKind::Refund,
                    'reason' => $data['reason'],
                    'idempotency_key' => $data['idempotency_key'],
                    'created_by' => $user->id,
                ]);
            });
        } catch (QueryException $exception) {
            return $this->replayedRefund($data['idempotency_key']) ?? throw $exception;
        }
    }

    /**
     * Reverse a refund: insert the linked negative row, lock the
     * original while deciding, and never touch the original itself.
     * The unique constraint on the pointer makes a second reversal of
     * the same refund impossible, even under concurrency. The refunded
     * money is treated as returned to the student — the credit it had
     * cancelled is restored, and net payments rise again on their own
     * because the ledger is fully re-derived.
     *
     * @param  array{reason: string, idempotency_key: string}  $data
     */
    public function reverseRefund(User $user, Refund $refund, array $data): Refund
    {
        if ($replayed = $this->replayedRefund($data['idempotency_key'])) {
            return $replayed;
        }

        try {
            return DB::transaction(function () use ($user, $refund, $data): Refund {
                $session = $this->sessions->resolve();

                /** @var Refund $original */
                $original = Refund::query()
                    ->whereKey($refund->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($original->isReversal()) {
                    throw new LedgerException(
                        'Reversal entries cannot themselves be reversed. Reverse the original refund instead.',
                    );
                }

                if ($original->reversedBy()->exists()) {
                    throw new LedgerException('This refund has already been reversed.');
                }

                return Refund::create([
                    'refund_number' => $this->numbers->next('RFD', new Refund, 'refund_number'),
                    'student_id' => $original->student_id,
                    'academic_session_id' => $session->id,
                    'payment_method' => $original->payment_method,
                    // The reference carries the correction marker, so a
                    // bank reconciliation can trace it back.
                    'reference' => mb_substr('REV: '.($original->reference ?? $original->refund_number), 0, 100),
                    // Money that left is coming back: the matching
                    // negative amount nets the original out of the
                    // refund sum the ledger derives from.
                    'amount' => -1 * round((float) $original->amount, 2),
                    'entry_kind' => RefundEntryKind::RefundReversal,
                    'reverses_refund_id' => $original->id,
                    'reason' => $data['reason'],
                    'idempotency_key' => $data['idempotency_key'],
                    'created_by' => $user->id,
                ]);
            });
        } catch (QueryException $exception) {
            if ($refund->reversedBy()->exists()) {
                throw new LedgerException('This refund has already been reversed.');
            }

            return $this->replayedRefund($data['idempotency_key']) ?? throw $exception;
        }
    }

    /**
     * The original row behind a replayed idempotency key, if the key
     * was already used on the payments table.
     */
    private function replayed(string $idempotencyKey): ?Payment
    {
        return Payment::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * The original row behind a replayed idempotency key on refunds.
     */
    private function replayedRefund(string $idempotencyKey): ?Refund
    {
        return Refund::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * The paying student, who must be on record and active.
     */
    private function activeStudent(int $studentId): StudentProfile
    {
        /** @var StudentProfile $student */
        $student = StudentProfile::query()->findOrFail($studentId);

        if ($student->status !== 'active') {
            throw ValidationException::withMessages([
                'student_id' => 'This student is not active.',
            ]);
        }

        return $student;
    }

    /**
     * Reversing this payment must leave net payments (payments minus
     * refunds) non-negative: a refunded payment cannot be reversed out
     * from under the refund.
     */
    private function guardReversalStaysPositive(Payment $original): void
    {
        $netAfter = round(
            (float) Payment::query()->where('student_id', $original->student_id)->sum('amount')
            - (float) $original->amount
            - (float) Refund::query()->where('student_id', $original->student_id)->sum('amount'),
            2,
        );

        if ($netAfter < 0) {
            throw new LedgerException(
                'This payment has been refunded; reversing it would make net payments negative. Reverse the refund first, or contact an administrator.',
            );
        }
    }

    /**
     * A transaction reference is unique within its method — the same
     * bank slip cannot be entered twice.
     *
     * @param  Builder<Payment|Refund>  $query
     */
    private function guardDuplicateReference($query, string $method, ?string $reference, string $field): void
    {
        if ($reference === null || $reference === '') {
            return;
        }

        $duplicate = $query
            ->where('payment_method', $method)
            ->where('reference', $reference)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                $field => 'A record with this reference already exists for this payment method.',
            ]);
        }
    }

    /**
     * Map a raced reference collision onto the same field error the
     * pre-check raises.
     */
    private function rethrowDuplicateReference(QueryException $exception, array $data, string $field): ?ValidationException
    {
        $reference = $data[$field] ?? null;

        if ($reference === null || $reference === '') {
            return null;
        }

        $collided = Payment::query()
            ->where('payment_method', $data['payment_method'] ?? '')
            ->where('reference', $reference)
            ->exists();

        if (! $collided) {
            return null;
        }

        return ValidationException::withMessages([
            $field => 'A record with this reference already exists for this payment method.',
        ]);
    }
}
