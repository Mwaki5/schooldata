<?php

namespace App\Http\Controllers\Invoices;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Concerns\PicksStudents;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\ReverseInvoiceRequest;
use App\Http\Requests\Invoices\StoreFeeInvoiceRequest;
use App\Http\Requests\Invoices\StoreOtherInvoiceRequest;
use App\Http\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Models\StudentProfile;
use App\Services\Finance\ActiveSessionResolver;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\StudentLedgerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

/**
 * The billing desk: list the ledger's invoices, raise a fee invoice
 * from a fee structure or a free-form other invoice, and reverse one
 * by inserting a linked negative row.
 *
 * There is deliberately no update and no destroy here — the ledger is
 * write-once, and every figure a response carries (paid, outstanding,
 * status) is derived from the immutable rows by the
 * StudentLedgerService, never read from storage.
 */
class InvoiceController extends Controller
{
    use PaginatesLists, PicksStudents;

    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly StudentLedgerService $ledger,
        private readonly ActiveSessionResolver $sessions,
    ) {}

    /**
     * List invoices newest first with the shared search / sort /
     * pagination contract. Search spans the invoice number and the
     * billed student's name / admission number; the `status` filter
     * and each row's paid / outstanding / status figures come from the
     * derived ledger, so the list always reflects the live state of
     * every payment and reversal.
     */
    public function index(Request $request): JsonResponse
    {
        $sortable = [
            'invoice_number' => 'invoice_number',
            'invoice_type' => 'invoice_type',
            'amount' => 'amount',
            'created_at' => 'created_at',
        ];

        $validated = $this->validateListQuery($request, $sortable);

        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(InvoiceStatus::values())],
        ]);

        $rows = Invoice::query()
            ->when($validated['search'] !== '', function (Builder $query) use ($validated): void {
                $like = '%'.addcslashes($validated['search'], '%_\\').'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('invoice_number', 'like', $like)
                        ->orWhereHas('student', function (Builder $query) use ($like): void {
                            $query->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhereHas('user', fn (Builder $inner) => $inner->where('login_id', 'like', $like));
                        });
                });
            })
            ->get(['invoices.id', 'invoices.student_id', 'invoices.amount', 'invoices.entry_kind', 'invoices.created_at']);

        $figures = $this->ledger->invoiceFigures($rows);

        $status = $filters['status'] ?? null;

        if ($status !== null) {
            $rows = $rows
                ->filter(fn (Invoice $invoice): bool => $figures->get((int) $invoice->id)['status'] === $status)
                ->values();
        }

        $orders = $validated['sort'] !== null
            ? [[$sortable[$validated['sort']], $validated['order']]]
            : [['created_at', 'desc'], ['id', 'desc']];

        foreach ($orders as [$column, $direction]) {
            $rows = $rows->sort(function (Invoice $left, Invoice $right) use ($column, $direction): int {
                $leftValue = $left->getAttribute($column);
                $rightValue = $right->getAttribute($column);

                $comparison = is_numeric($leftValue) && is_numeric($rightValue)
                    ? ((float) $leftValue <=> (float) $rightValue)
                    : strnatcasecmp((string) $leftValue, (string) $rightValue);

                return $direction === 'desc' ? -$comparison : $comparison;
            })->values();
        }

        $pageIds = $rows
            ->forPage($validated['page'], $validated['per_page'])
            ->pluck('id')
            ->all();

        $models = Invoice::query()
            ->whereIn('id', $pageIds)
            ->with(['student.user', 'academicSession', 'feeStructure'])
            ->get()
            ->keyBy('id');

        $items = collect($pageIds)
            ->map(fn (int $id): Invoice => $models[$id])
            ->each(function (Invoice $invoice) use ($figures): void {
                $this->attachFigures($invoice, $figures->get((int) $invoice->id));
            })
            ->all();

        $paginator = new LengthAwarePaginator(
            $items,
            $rows->count(),
            $validated['per_page'],
            $validated['page'],
            ['path' => $request->url(), 'pageName' => 'page'],
        );

        return response()->json([
            'invoices' => InvoiceResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * The student picker for the billing screens. The finance desk
     * holds `invoices.create` rather than `students.view`, so the
     * picker rides the invoice permission instead of the roster.
     */
    public function students(Request $request): JsonResponse
    {
        return $this->pickStudents($request);
    }

    /**
     * The fee structures that can bill the chosen student in the
     * active session, each with its current total and whether an
     * active invoice already exists for it — the fee-invoice tab's
     * picker and its duplicate hint.
     */
    public function feeStructures(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:student_profiles,id'],
        ]);

        $session = $this->sessions->resolve();

        /** @var StudentProfile $student */
        $student = StudentProfile::query()->findOrFail($data['student_id']);

        return response()->json([
            'academic_session' => [
                'id' => $session->id,
                'name' => $session->name,
            ],
            // The configured duplicate-fee-invoice mode, so the tab can
            // offer the confirm checkbox under `warn`, refuse the
            // submit under `block`, and stay silent under `allow`.
            'duplicate_fee_invoice' => config('finance.duplicate_fee_invoice', 'warn'),
            'fee_structures' => $this->invoices->applicableFeeStructures($student, $session->id),
        ]);
    }

    /**
     * One student's invoices, newest first, each carrying the derived
     * paid / outstanding / status figures and its reversal links —
     * the reverse-invoice desk reads the row set from here, and a
     * status of `reversed` (on a reversed original or a reversal row
     * alike) is what tells the client to disable its Reverse action.
     */
    public function forStudent(StudentProfile $student): JsonResponse
    {
        $rows = Invoice::query()
            ->where('student_id', $student->id)
            ->with(['student.user', 'academicSession', 'feeStructure', 'reversedBy', 'reverses'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $figures = $this->ledger->invoiceFigures($rows);

        $rows->each(fn (Invoice $invoice): mixed => $this->attachFigures(
            $invoice,
            $figures->get((int) $invoice->id),
        ));

        return response()->json([
            'invoices' => InvoiceResource::collection($rows),
        ]);
    }

    /**
     * Raise a fee invoice. The amount is decided by the backend from
     * the fee structure's live item sum — anything the client sent as
     * an amount is ignored entirely.
     */
    public function storeFee(StoreFeeInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoices->createFeeInvoice($request->user(), $request->validated());

        $invoice->load(['student.user', 'academicSession', 'feeStructure']);
        $this->attachFigures($invoice, $this->ledger->invoiceFigures([$invoice])->get((int) $invoice->id));

        return response()->json([
            'invoice' => new InvoiceResource($invoice),
        ], Response::HTTP_CREATED);
    }

    /**
     * Raise an other invoice: penalty, hostel, exam fee, transport or
     * a plain other charge, under the finance desk's own amount.
     */
    public function storeOther(StoreOtherInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoices->createOtherInvoice($request->user(), $request->validated());

        $invoice->load(['student.user', 'academicSession', 'feeStructure']);
        $this->attachFigures($invoice, $this->ledger->invoiceFigures([$invoice])->get((int) $invoice->id));

        return response()->json([
            'invoice' => new InvoiceResource($invoice),
        ], Response::HTTP_CREATED);
    }

    /**
     * Reverse an invoice by inserting the linked negative row; the
     * original is never touched. An already-reversed invoice answers
     * 409 through the LedgerException handler.
     */
    public function reverse(ReverseInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $reversal = $this->invoices->reverseInvoice($request->user(), $invoice, $request->validated());

        $reversal->load(['student.user', 'academicSession', 'feeStructure']);
        $this->attachFigures($reversal, $this->ledger->invoiceFigures([$reversal])->get((int) $reversal->id));

        return response()->json([
            'invoice' => new InvoiceResource($reversal),
        ], Response::HTTP_CREATED);
    }

    /**
     * Show one invoice with its derived figures and, when relevant,
     * the reversal it carries or the original it reverses.
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['student.user', 'academicSession', 'feeStructure', 'reversedBy', 'reverses']);
        $this->attachFigures($invoice, $this->ledger->invoiceFigures([$invoice])->get((int) $invoice->id));

        return response()->json([
            'invoice' => new InvoiceResource($invoice),
        ]);
    }

    /**
     * The effect of reversing this invoice, computed by the derived
     * ledger: the balance before and after, and how much credit the
     * reversal would create. Nothing hypothetical is persisted.
     */
    public function reversalPreview(Invoice $invoice): JsonResponse
    {
        ['before' => $before, 'after' => $after] = $this->ledger->beforeAndAfter(
            (int) $invoice->student_id,
            excludedInvoiceIds: [$invoice->id],
        );

        return response()->json([
            'invoice_number' => $invoice->invoice_number,
            'amount' => (float) $invoice->amount,
            'balance_before' => $before['balance'],
            'balance_after' => $after['balance'],
            'credit_after' => $after['credit'],
            'reopened_invoices' => collect($after['invoices'])
                ->filter(fn (array $figure): bool => $figure['outstanding'] > 0)
                ->pluck('invoice_number')
                ->values(),
        ]);
    }

    /**
     * Stamp the ledger-derived figures onto the model so the resource
     * can render them.
     */
    private function attachFigures(Invoice $invoice, ?array $figure): void
    {
        $invoice->paid = $figure['paid'] ?? 0.0;
        $invoice->outstanding = $figure['outstanding'] ?? 0.0;
        $invoice->status = $figure['status'] ?? InvoiceStatus::Reversed->value;
    }
}
