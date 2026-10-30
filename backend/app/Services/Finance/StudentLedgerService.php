<?php

namespace App\Services\Finance;

use App\Enums\InvoiceEntryKind;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentEntryKind;
use App\Enums\PaymentMethod;
use App\Enums\RefundEntryKind;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Collection;

/**
 * The single source of truth for every derived finance figure.
 *
 * Nothing about settlement is ever stored. From the immutable rows
 * alone this service derives, for one student:
 *
 *   - which invoices are ACTIVE: charges with no reversal row pointing
 *     at them. Reversed invoices and every reversal row are excluded
 *     from allocation and presented with the `reversed` status.
 *   - net payments received: the sum of all payment rows (reversals
 *     carry negative amounts, so they net out) minus refunds.
 *   - per-invoice paid / outstanding / status, by applying the net
 *     payments to the active invoices OLDEST FIRST (by created_at,
 *     then id).
 *   - the student balance: active invoices minus net payments.
 *     Positive is arrears, negative is credit held for the student.
 *
 * Because it is all recalculated, a reversal needs no un-apply logic
 * anywhere: reversing a payment raises the balance and re-opens the
 * invoices it had settled; reversing an invoice that was already paid
 * turns the excess into student credit, automatically.
 *
 * Every screen and every controller reads these figures through this
 * one class — the frontend never re-derives them. Monetary arithmetic
 * is rounded to two decimals at each step so the ledger always
 * reconciles to the cent.
 */
class StudentLedgerService
{
    /**
     * The full derived ledger for one student.
     *
     * @return array{
     *     invoices: list<array<string, mixed>>,
     *     reversed_invoices: list<array<string, mixed>>,
     *     total_invoiced: float,
     *     net_payments: float,
     *     balance: float,
     *     arrears: float,
     *     credit: float,
     * }
     */
    public function forStudent(int $studentId): array
    {
        return $this->compute(
            $this->invoiceRows($studentId),
            $this->paymentRows($studentId),
            $this->refundRows($studentId),
        );
    }

    /**
     * The ledger as it stands AND as it WOULD stand with hypothetical
     * rows applied — the before/after pair behind every confirmation
     * dialog: how a payment of X would settle the invoices, what
     * reversing an invoice, payment or refund would do to the balance.
     * Both sides come from ONE load of the ledger rows, and no
     * hypothetical is ever persisted.
     *
     * @param  list<array{amount: int|float|string}>  $addedInvoices
     * @param  list<array{amount: int|float|string}>  $addedPayments
     * @param  list<array{amount: int|float|string}>  $addedRefunds
     * @param  list<int>  $excludedInvoiceIds
     * @param  list<int>  $excludedPaymentIds
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    public function beforeAndAfter(
        int $studentId,
        array $addedInvoices = [],
        array $addedPayments = [],
        array $addedRefunds = [],
        array $excludedInvoiceIds = [],
        array $excludedPaymentIds = [],
    ): array {
        $invoices = $this->invoiceRows($studentId);
        $payments = $this->paymentRows($studentId);
        $refunds = $this->refundRows($studentId);

        // The real ledger first, from the untouched rows: the compute
        // pass reads them without mutating, so the same collections
        // may then carry the hypothetical rows for the "after" side.
        $before = $this->compute($invoices, $payments, $refunds);

        foreach ($addedInvoices as $added) {
            $invoices->push($this->unsavedInvoice($studentId, (float) $added['amount']));
        }

        foreach ($addedPayments as $added) {
            $payments->push($this->unsavedPayment($studentId, (float) $added['amount']));
        }

        foreach ($addedRefunds as $added) {
            $refunds->push($this->unsavedRefund($studentId, (float) $added['amount']));
        }

        $after = $this->compute(
            $invoices->reject(fn (Invoice $invoice): bool => in_array($invoice->id, $excludedInvoiceIds, true)),
            $payments->reject(fn (Payment $payment): bool => in_array($payment->id, $excludedPaymentIds, true)),
            $refunds,
        );

        return ['before' => $before, 'after' => $after];
    }

    /**
     * The balance block every money-moving response carries beside the
     * document it just wrote — the ledger's five headline figures.
     *
     * @return array{total_invoiced: float, net_payments: float, balance: float, arrears: float, credit: float}
     */
    public function summary(int $studentId): array
    {
        $ledger = $this->forStudent($studentId);

        return [
            'total_invoiced' => $ledger['total_invoiced'],
            'net_payments' => $ledger['net_payments'],
            'balance' => $ledger['balance'],
            'arrears' => $ledger['arrears'],
            'credit' => $ledger['credit'],
        ];
    }

    /**
     * The derived ledger and the chronological statement for one
     * student from a single load of the ledger rows: every row in
     * date order with a running balance, beside the same figures the
     * rest of the desks read. The closing balance reconciles exactly
     * with the ledger's balance, because both are derived from the
     * same rows.
     *
     * @return array{
     *     ledger: array{
     *         invoices: list<array<string, mixed>>,
     *         reversed_invoices: list<array<string, mixed>>,
     *         total_invoiced: float,
     *         net_payments: float,
     *         balance: float,
     *         arrears: float,
     *         credit: float,
     *     },
     *     statement: array{
     *         entries: list<array{date: string, type: string, document_number: string, description: string, debit: float, credit: float, running_balance: float}>,
     *         closing_balance: float,
     *         arrears: float,
     *         credit: float,
     *     },
     * }
     */
    public function ledgerAndStatement(int $studentId): array
    {
        $invoices = $this->invoiceRows($studentId, withReverses: true);
        $payments = $this->paymentRows($studentId, withReverses: true);
        $refunds = $this->refundRows($studentId, withReverses: true);

        return [
            'ledger' => $this->compute($invoices, $payments, $refunds),
            'statement' => $this->buildStatement($invoices, $payments, $refunds),
        ];
    }

    /**
     * The statement half of the pair above, over rows already loaded.
     *
     * @param  Collection<int, Invoice>  $invoices
     * @param  Collection<int, Payment>  $payments
     * @param  Collection<int, Refund>  $refunds
     * @return array{
     *     entries: list<array{date: string, type: string, document_number: string, description: string, debit: float, credit: float, running_balance: float}>,
     *     closing_balance: float,
     *     arrears: float,
     *     credit: float,
     * }
     */
    private function buildStatement(Collection $invoices, Collection $payments, Collection $refunds): array
    {
        $entries = [];

        foreach ($invoices as $invoice) {
            $isReversal = $invoice->entry_kind === InvoiceEntryKind::Reversal;
            $amount = round(abs((float) $invoice->amount), 2);

            $entries[] = [
                'sort_date' => $invoice->created_at?->toDateString() ?? '',
                'sort_rank' => 0,
                'id' => (int) $invoice->id,
                'date' => $invoice->created_at?->toDateString(),
                'type' => $isReversal ? 'invoice_reversal' : 'invoice',
                'document_number' => $invoice->invoice_number,
                'description' => $isReversal
                    ? 'Reversal of '.$invoice->reverses?->invoice_number.' — '.($invoice->reversal_reason ?? '')
                    : trim(($invoice->invoice_type?->value ?? '').' '.($invoice->description ?? '')),
                // A charge raises what is owed; its reversal lowers it.
                'debit' => $isReversal ? 0.0 : $amount,
                'credit' => $isReversal ? $amount : 0.0,
            ];
        }

        foreach ($payments as $payment) {
            $isReversal = $payment->entry_kind === PaymentEntryKind::PaymentReversal;
            $amount = round(abs((float) $payment->amount), 2);

            $entries[] = [
                'sort_date' => $payment->payment_date?->toDateString() ?? '',
                'sort_rank' => 1,
                'id' => (int) $payment->id,
                'date' => $payment->payment_date?->toDateString(),
                'type' => $isReversal ? 'payment_reversal' : 'payment',
                'document_number' => $payment->receipt_number,
                'description' => $isReversal
                    ? 'Reversal of '.$payment->reverses?->receipt_number.' — '.($payment->reversal_reason ?? '')
                    : trim($payment->payment_method?->label().' '.($payment->reference ?? '')),
                // Money in lowers what is owed; taking it back raises it.
                'debit' => $isReversal ? $amount : 0.0,
                'credit' => $isReversal ? 0.0 : $amount,
            ];
        }

        foreach ($refunds as $refund) {
            $isReversal = $refund->entry_kind === RefundEntryKind::RefundReversal;
            $amount = round(abs((float) $refund->amount), 2);

            $entries[] = [
                'sort_date' => $refund->created_at?->toDateString() ?? '',
                'sort_rank' => 2,
                'id' => (int) $refund->id,
                'date' => $refund->created_at?->toDateString(),
                'type' => $isReversal ? 'refund_reversal' : 'refund',
                'document_number' => $refund->refund_number,
                'description' => $isReversal
                    ? 'Reversal of '.$refund->reverses?->refund_number.' — '.($refund->reason ?? '')
                    : trim($refund->payment_method?->label().' refund '.($refund->reference ?? '')).' — '.$refund->reason,
                // Paying credit back out raises what is owed again; the
                // reversal brings that money back in and lowers it.
                'debit' => $isReversal ? 0.0 : $amount,
                'credit' => $isReversal ? $amount : 0.0,
            ];
        }

        usort($entries, fn (array $left, array $right): int => [
            $left['sort_date'], $left['sort_rank'], $left['id'],
        ] <=> [
            $right['sort_date'], $right['sort_rank'], $right['id'],
        ]);

        $running = 0.0;

        foreach ($entries as &$entry) {
            $running = round($running + $entry['debit'] - $entry['credit'], 2);
            $entry['running_balance'] = $running;
            unset($entry['sort_date'], $entry['sort_rank'], $entry['id']);
        }
        unset($entry);

        return [
            'entries' => $entries,
            'closing_balance' => $running,
            'arrears' => round(max($running, 0.0), 2),
            'credit' => round(max(-$running, 0.0), 2),
        ];
    }

    /**
     * Per-invoice figures (paid, outstanding, status) for a mixed set
     * of invoices across many students — the billing list reads them
     * through this one pass instead of per row.
     *
     * The ledgers of every student on the set are loaded in ONE query
     * per ledger table and derived here in memory, so the list runs a
     * fixed number of queries instead of three (times two, with the
     * relation loads) per student.
     *
     * @param  iterable<int, Invoice>  $invoices
     * @return Collection<int, array{paid: float, outstanding: float, status: string}>
     */
    public function invoiceFigures(iterable $invoices): Collection
    {
        $rows = collect($invoices)->groupBy(fn (Invoice $invoice): int => (int) $invoice->student_id);

        if ($rows->isEmpty()) {
            return new Collection;
        }

        $ledgers = $this->ledgersForStudents($rows->keys()->all());

        $figures = new Collection;

        foreach ($rows as $studentId => $studentRows) {
            $byId = collect($ledgers->get((int) $studentId)['invoices'])
                ->keyBy(fn (array $invoice): int => (int) $invoice['id']);

            foreach ($studentRows as $row) {
                $figures->put((int) $row->id, $byId->get((int) $row->id, [
                    // Anything not in the active set — a reversal row
                    // or a reversed original — settles to zero.
                    'paid' => 0.0,
                    'outstanding' => 0.0,
                    'status' => InvoiceStatus::Reversed->value,
                ]));
            }
        }

        return $figures;
    }

    /**
     * The derived ledger for a whole set of students at once: one
     * query per ledger table for the entire set, then one in-memory
     * derivation per student — the batched twin of forStudent().
     *
     * @param  list<int>  $studentIds
     * @return Collection<int, array<string, mixed>>
     */
    private function ledgersForStudents(array $studentIds): Collection
    {
        $invoices = Invoice::query()
            ->whereIn('student_id', $studentIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Invoice $invoice): int => (int) $invoice->student_id);

        $payments = Payment::query()
            ->whereIn('student_id', $studentIds)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Payment $payment): int => (int) $payment->student_id);

        $refunds = Refund::query()
            ->whereIn('student_id', $studentIds)
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Refund $refund): int => (int) $refund->student_id);

        return (new Collection($studentIds))->mapWithKeys(fn (int $studentId): array => [
            $studentId => $this->compute(
                $invoices->get($studentId, new Collection),
                $payments->get($studentId, new Collection),
                $refunds->get($studentId, new Collection),
            ),
        ]);
    }

    /**
     * The derivation itself, over plain ledger rows.
     *
     * @param  Collection<int, Invoice>  $invoices
     * @param  Collection<int, Payment>  $payments
     * @param  Collection<int, Refund>  $refunds
     * @return array{
     *     invoices: list<array<string, mixed>>,
     *     reversed_invoices: list<array<string, mixed>>,
     *     total_invoiced: float,
     *     net_payments: float,
     *     balance: float,
     *     arrears: float,
     *     credit: float,
     * }
     */
    private function compute(Collection $invoices, Collection $payments, Collection $refunds): array
    {
        // The originals a reversal row points at are no longer active.
        $reversalRows = $invoices
            ->filter(fn (Invoice $invoice): bool => $invoice->entry_kind === InvoiceEntryKind::Reversal);

        $reversedIds = $reversalRows
            ->map(fn (Invoice $invoice): int => (int) $invoice->reverses_invoice_id);

        $reversalReasons = $reversalRows->mapWithKeys(
            fn (Invoice $invoice): array => [(int) $invoice->reverses_invoice_id => $invoice->reversal_reason],
        );

        $active = $invoices
            ->reject(fn (Invoice $invoice): bool => $invoice->entry_kind === InvoiceEntryKind::Reversal)
            ->reject(fn (Invoice $invoice): bool => $reversedIds->contains((int) $invoice->id))
            ->sort(fn (Invoice $left, Invoice $right): int => [
                $left->created_at?->toDateTimeString() ?? '', (int) $left->id,
            ] <=> [
                $right->created_at?->toDateTimeString() ?? '', (int) $right->id,
            ])
            ->values();

        $reversed = $invoices
            ->reject(fn (Invoice $invoice): bool => $invoice->entry_kind === InvoiceEntryKind::Reversal)
            ->filter(fn (Invoice $invoice): bool => $reversedIds->contains((int) $invoice->id))
            ->values();

        $totalInvoiced = round((float) $active->sum(fn (Invoice $invoice): float => (float) $invoice->amount), 2);

        // Reversal rows carry negative amounts, so summing every
        // payment row nets originals against their corrections.
        $netPayments = round(
            (float) $payments->sum(fn (Payment $payment): float => (float) $payment->amount)
            - (float) $refunds->sum(fn (Refund $refund): float => (float) $refund->amount),
            2,
        );

        // Oldest first: each invoice takes what is still unapplied.
        $remaining = max($netPayments, 0.0);

        $figured = $active->map(function (Invoice $invoice) use (&$remaining): array {
            $amount = round((float) $invoice->amount, 2);
            $paid = round(min($remaining, $amount), 2);
            $outstanding = round($amount - $paid, 2);
            $remaining = round($remaining - $paid, 2);

            return [
                'id' => (int) $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_type' => $invoice->invoice_type?->value,
                'fee_structure_id' => $invoice->fee_structure_id,
                'student_id' => (int) $invoice->student_id,
                'academic_session_id' => (int) $invoice->academic_session_id,
                'description' => $invoice->description,
                'amount' => $amount,
                'paid' => $paid,
                'outstanding' => $outstanding,
                'status' => match (true) {
                    $paid <= 0.0 => InvoiceStatus::Unpaid->value,
                    $outstanding > 0.0 => InvoiceStatus::PartiallyPaid->value,
                    default => InvoiceStatus::Paid->value,
                },
                'entry_kind' => $invoice->entry_kind?->value,
                'reverses_invoice_id' => $invoice->reverses_invoice_id,
                'reversal_reason' => $invoice->reversal_reason,
                'created_by' => $invoice->created_by,
                'created_at' => $invoice->created_at?->toDateTimeString(),
            ];
        })->values()->all();

        $reversedPlain = $reversed->map(fn (Invoice $invoice): array => [
            'id' => (int) $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_type' => $invoice->invoice_type?->value,
            'fee_structure_id' => $invoice->fee_structure_id,
            'student_id' => (int) $invoice->student_id,
            'academic_session_id' => (int) $invoice->academic_session_id,
            'description' => $invoice->description,
            'amount' => round((float) $invoice->amount, 2),
            'status' => InvoiceStatus::Reversed->value,
            'entry_kind' => $invoice->entry_kind?->value,
            'reversal_reason' => $reversalReasons->get((int) $invoice->id),
            'created_at' => $invoice->created_at?->toDateTimeString(),
        ])->values()->all();

        $balance = round($totalInvoiced - $netPayments, 2);

        return [
            'invoices' => $figured,
            'reversed_invoices' => $reversedPlain,
            'total_invoiced' => $totalInvoiced,
            'net_payments' => $netPayments,
            'balance' => $balance,
            'arrears' => round(max($balance, 0.0), 2),
            'credit' => round(max(-$balance, 0.0), 2),
        ];
    }

    /**
     * Every invoice row for one student, oldest first. The reverses
     * relation is opt-in: only the statement's descriptions read it.
     *
     * @return Collection<int, Invoice>
     */
    private function invoiceRows(int $studentId, bool $withReverses = false): Collection
    {
        $query = Invoice::query();

        if ($withReverses) {
            $query->with('reverses:id,invoice_number');
        }

        return $query
            ->where('student_id', $studentId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Every payment row for one student, oldest first. The reverses
     * relation is opt-in: only the statement's descriptions read it.
     *
     * @return Collection<int, Payment>
     */
    private function paymentRows(int $studentId, bool $withReverses = false): Collection
    {
        $query = Payment::query();

        if ($withReverses) {
            $query->with('reverses:id,receipt_number');
        }

        return $query
            ->where('student_id', $studentId)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Every refund row for one student, oldest first. The reverses
     * relation is opt-in: only the statement's descriptions read it.
     *
     * @return Collection<int, Refund>
     */
    private function refundRows(int $studentId, bool $withReverses = false): Collection
    {
        $query = Refund::query();

        if ($withReverses) {
            $query->with('reverses:id,refund_number');
        }

        return $query
            ->where('student_id', $studentId)
            ->orderBy('id')
            ->get();
    }

    /**
     * An in-memory stand-in charge, for previews only.
     */
    private function unsavedInvoice(int $studentId, float $amount): Invoice
    {
        $invoice = new Invoice([
            'student_id' => $studentId,
            'invoice_type' => InvoiceType::Other,
            'amount' => $amount,
            'entry_kind' => InvoiceEntryKind::Invoice,
        ]);
        $invoice->created_at = now();

        return $invoice;
    }

    /**
     * An in-memory stand-in payment, for previews only.
     */
    private function unsavedPayment(int $studentId, float $amount): Payment
    {
        $payment = new Payment([
            'student_id' => $studentId,
            'payment_method' => PaymentMethod::Cash,
            'payment_date' => now()->toDateString(),
            'amount' => $amount,
            'entry_kind' => PaymentEntryKind::Payment,
        ]);

        return $payment;
    }

    /**
     * An in-memory stand-in refund, for previews only.
     */
    private function unsavedRefund(int $studentId, float $amount): Refund
    {
        return new Refund([
            'student_id' => $studentId,
            'payment_method' => PaymentMethod::Cash,
            'amount' => $amount,
        ]);
    }
}
