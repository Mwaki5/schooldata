<?php

namespace App\Services\Finance;

use App\Enums\EnrolmentStatus;
use App\Enums\InvoiceEntryKind;
use App\Enums\InvoiceType;
use App\Models\CourseFeeAssignment;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\StudentProfile;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every way an invoice enters the ledger: a fee invoice raised from a
 * fee structure, a free-form other invoice, and the reversal that
 * corrects either.
 *
 * All three are write-once — there is no update and no delete anywhere
 * in this service. Each write runs in one transaction, stamps the
 * active session and the acting user server-side, and carries the
 * client's idempotency key so a double click replays the original
 * document instead of billing twice.
 */
class InvoiceService
{
    public function __construct(
        private readonly ActiveSessionResolver $sessions,
        private readonly NumberSequence $numbers,
    ) {}

    /**
     * Raise a fee invoice: the amount is decided by the backend from
     * the fee structure's live item sum — a client-sent amount is
     * never read.
     *
     * @param  array{student_id: int, fee_structure_id: int, description?: string|null, confirm_duplicate?: bool, idempotency_key: string}  $data
     */
    public function createFeeInvoice(User $user, array $data): Invoice
    {
        if ($replayed = $this->replayed($data['idempotency_key'])) {
            return $replayed;
        }

        try {
            return DB::transaction(function () use ($user, $data): Invoice {
                $session = $this->sessions->resolve();
                $student = $this->activeStudent($data['student_id']);
                $feeStructure = $this->feeStructureFor($student, $data['fee_structure_id'], $session->id);

                $this->guardDuplicateFeeInvoice(
                    $student->id,
                    $feeStructure->id,
                    $session->id,
                    (bool) ($data['confirm_duplicate'] ?? false),
                );

                return Invoice::create([
                    'invoice_number' => $this->numbers->next('INV', new Invoice, 'invoice_number'),
                    'student_id' => $student->id,
                    'academic_session_id' => $session->id,
                    'invoice_type' => InvoiceType::FeeInvoice,
                    'fee_structure_id' => $feeStructure->id,
                    'description' => $data['description'] ?? null,
                    'amount' => $this->feeStructureTotal($feeStructure),
                    'entry_kind' => InvoiceEntryKind::Invoice,
                    'idempotency_key' => $data['idempotency_key'],
                    'created_by' => $user->id,
                ]);
            });
        } catch (QueryException $exception) {
            // A concurrent replay raced past the pre-check; the unique
            // index caught it, so the original document stands.
            return $this->replayed($data['idempotency_key']) ?? throw $exception;
        }
    }

    /**
     * Raise an other invoice: penalty, hostel, exam fee, transport or
     * a plain other charge, with the amount the finance desk entered.
     *
     * @param  array{student_id: int, invoice_type: string, description: string, amount: int|float|string, idempotency_key: string}  $data
     */
    public function createOtherInvoice(User $user, array $data): Invoice
    {
        if ($replayed = $this->replayed($data['idempotency_key'])) {
            return $replayed;
        }

        try {
            return DB::transaction(function () use ($user, $data): Invoice {
                $session = $this->sessions->resolve();
                $student = $this->activeStudent($data['student_id']);

                return Invoice::create([
                    'invoice_number' => $this->numbers->next('INV', new Invoice, 'invoice_number'),
                    'student_id' => $student->id,
                    'academic_session_id' => $session->id,
                    'invoice_type' => InvoiceType::from($data['invoice_type']),
                    'description' => $data['description'],
                    'amount' => round((float) $data['amount'], 2),
                    'entry_kind' => InvoiceEntryKind::Invoice,
                    'idempotency_key' => $data['idempotency_key'],
                    'created_by' => $user->id,
                ]);
            });
        } catch (QueryException $exception) {
            return $this->replayed($data['idempotency_key']) ?? throw $exception;
        }
    }

    /**
     * Reverse an invoice: insert the linked negative row, lock the
     * original while deciding, and never touch the original itself.
     * The unique constraint on the pointer makes a second reversal of
     * the same invoice impossible, even under concurrency.
     *
     * @param  array{reason: string, idempotency_key: string}  $data
     */
    public function reverseInvoice(User $user, Invoice $invoice, array $data): Invoice
    {
        if ($replayed = $this->replayed($data['idempotency_key'])) {
            return $replayed;
        }

        try {
            return DB::transaction(function () use ($user, $invoice, $data): Invoice {
                $session = $this->sessions->resolve();

                /** @var Invoice $original */
                $original = Invoice::query()
                    ->whereKey($invoice->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($original->isReversal()) {
                    throw new LedgerException(
                        'Reversal entries cannot themselves be reversed. Raise a new invoice to correct the amount instead.',
                    );
                }

                if ($original->reversedBy()->exists()) {
                    throw new LedgerException('This invoice has already been reversed.');
                }

                return Invoice::create([
                    'invoice_number' => $this->numbers->next('INV', new Invoice, 'invoice_number'),
                    'student_id' => $original->student_id,
                    'academic_session_id' => $session->id,
                    'invoice_type' => $original->invoice_type,
                    'fee_structure_id' => $original->fee_structure_id,
                    'description' => $original->description,
                    'amount' => -1 * round((float) $original->amount, 2),
                    'entry_kind' => InvoiceEntryKind::Reversal,
                    'reverses_invoice_id' => $original->id,
                    'reversal_reason' => $data['reason'],
                    'idempotency_key' => $data['idempotency_key'],
                    'created_by' => $user->id,
                ]);
            });
        } catch (QueryException $exception) {
            if ($invoice->reversedBy()->exists()) {
                throw new LedgerException('This invoice has already been reversed.');
            }

            return $this->replayed($data['idempotency_key']) ?? throw $exception;
        }
    }

    /**
     * The fee structures that can bill this student in the active
     * session: every live structure assigned — through a course fee
     * assignment — to a course and curriculum pair the student is
     * enrolled in. Each carries its current total and whether an
     * active invoice already exists for it this session, so the
     * picker can warn before the duplicate guard ever fires.
     *
     * @return Collection<int, array{id: int, name: string, total: float, already_invoiced: bool}>
     */
    public function applicableFeeStructures(StudentProfile $student, int $sessionId): Collection
    {
        $alreadyInvoiced = Invoice::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $sessionId)
            ->where('entry_kind', InvoiceEntryKind::Invoice)
            ->whereNotNull('fee_structure_id')
            ->doesntHave('reversedBy')
            ->pluck('fee_structure_id')
            ->map(fn ($id): int => (int) $id);

        return FeeStructure::query()
            ->where('is_active', true)
            ->whereExists(function ($query) use ($student, $sessionId): void {
                // Correlate to this fee structure, then apply the same
                // enrolment eligibility check a single fee invoice runs.
                $query->selectRaw('1')
                    ->from('course_fee_assignments')
                    ->whereColumn('course_fee_assignments.fee_structure_id', 'fee_structures.id')
                    ->whereExists($this->enrolmentMatch($student, $sessionId));
            })
            ->withSum('items as total_amount', 'amount')
            ->orderBy('name')
            ->get()
            ->map(fn (FeeStructure $feeStructure): array => [
                'id' => $feeStructure->id,
                'name' => $feeStructure->name,
                'total' => round((float) ($feeStructure->total_amount ?? 0), 2),
                'already_invoiced' => $alreadyInvoiced->contains($feeStructure->id),
            ]);
    }

    /**
     * The original row behind a replayed idempotency key, if the key
     * was already used on this table.
     */
    private function replayed(string $idempotencyKey): ?Invoice
    {
        return Invoice::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * The billed student, who must be on record and active.
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
     * The fee structure a fee invoice is raised from: it must be live,
     * and assigned — through a course fee assignment — to a course and
     * curriculum the student is actually enrolled in for the active
     * session. That pairing is what "matches the student's class"
     * means here.
     */
    private function feeStructureFor(StudentProfile $student, int $feeStructureId, int $sessionId): FeeStructure
    {
        /** @var FeeStructure|null $feeStructure */
        $feeStructure = FeeStructure::query()->find($feeStructureId);

        if (! $feeStructure instanceof FeeStructure) {
            throw ValidationException::withMessages([
                'fee_structure_id' => 'The selected fee structure does not exist.',
            ]);
        }

        if (! $feeStructure->is_active) {
            throw ValidationException::withMessages([
                'fee_structure_id' => 'This fee structure is inactive.',
            ]);
        }

        $applies = CourseFeeAssignment::query()
            ->where('fee_structure_id', $feeStructure->id)
            ->whereExists($this->enrolmentMatch($student, $sessionId))
            ->exists();

        if (! $applies) {
            throw ValidationException::withMessages([
                'fee_structure_id' => "This fee structure is not assigned to the student's enrolled course in the active session.",
            ]);
        }

        return $feeStructure;
    }

    /**
     * The correlated existence check behind fee-invoice eligibility:
     * some course fee assignment of the structure must pair a course
     * and curriculum the student is ENROLLED in for the active
     * session. That pairing is what "matches the student's class"
     * means here.
     *
     * @return Closure(Builder<mixed>): void
     */
    private function enrolmentMatch(StudentProfile $student, int $sessionId): Closure
    {
        return function ($query) use ($student, $sessionId): void {
            $query->selectRaw('1')
                ->from('course_enrolments')
                ->whereColumn('course_enrolments.course_id', 'course_fee_assignments.course_id')
                ->whereColumn('course_enrolments.curriculum_id', 'course_fee_assignments.curriculum_id')
                ->where('course_enrolments.student_id', $student->id)
                ->where('course_enrolments.academic_session_id', $sessionId)
                ->where('course_enrolments.status', EnrolmentStatus::Enrolled->value)
                ->whereNull('course_enrolments.deleted_at');
        };
    }

    /**
     * The charge a fee invoice carries: the structure's live item sum,
     * decided here and nowhere else.
     */
    private function feeStructureTotal(FeeStructure $feeStructure): float
    {
        $total = round((float) $feeStructure->items()->sum('amount'), 2);

        if ($total <= 0) {
            throw ValidationException::withMessages([
                'fee_structure_id' => 'This fee structure has no charges to invoice.',
            ]);
        }

        return $total;
    }

    /**
     * The duplicate-fee-invoice guard, driven by the
     * `finance.duplicate_fee_invoice` config flag:
     *
     * - `allow`  no guard at all
     * - `warn`   the client must send `confirm_duplicate: true`
     * - `block`  the second invoice is refused outright
     */
    private function guardDuplicateFeeInvoice(int $studentId, int $feeStructureId, int $sessionId, bool $confirmed): void
    {
        $mode = config('finance.duplicate_fee_invoice', 'warn');

        if ($mode === 'allow') {
            return;
        }

        $duplicate = Invoice::query()
            ->where('student_id', $studentId)
            ->where('fee_structure_id', $feeStructureId)
            ->where('academic_session_id', $sessionId)
            ->where('entry_kind', InvoiceEntryKind::Invoice)
            ->doesntHave('reversedBy')
            ->exists();

        if (! $duplicate) {
            return;
        }

        if ($mode === 'block' || ! $confirmed) {
            throw ValidationException::withMessages([
                'fee_structure_id' => 'This student already has an active invoice for this fee structure in the current session.'
                    .($mode === 'warn' ? ' Confirm to raise it again anyway.' : ''),
            ]);
        }
    }
}
