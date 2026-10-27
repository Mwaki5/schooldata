<?php

namespace App\Http\Controllers\Invoices;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Http\Requests\Invoices\UpdateInvoiceRequest;
use App\Http\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    use PaginatesLists;

    /**
     * List invoices newest first with the shared search / sort /
     * pagination contract. Search spans the invoice number and the
     * billed student's name / admission number, and the list narrows to
     * one lifecycle state with the `status` filter. The total rides
     * along as a sum of the invoice's live lines.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(InvoiceStatus::values())],
        ]);

        $paginator = $this->paginateList(
            $request,
            Invoice::query()
                ->with(['student.user', 'academicSession'])
                ->withSum('items as total_amount', 'amount'),
            searchable: [],
            sortable: [
                'invoice_number' => 'invoice_number',
                'status' => 'status',
                'created_at' => 'created_at',
            ],
            defaultOrder: [['created_at', 'desc'], ['id', 'desc']],
            applyFilters: function (Builder $query) use ($filters, $request): void {
                $search = trim((string) $request->query('search', ''));

                if ($search !== '') {
                    $like = '%'.addcslashes($search, '%_\\').'%';

                    $query->where(function (Builder $query) use ($like): void {
                        $query->where('invoice_number', 'like', $like)
                            ->orWhereHas('student', function (Builder $query) use ($like): void {
                                $query->where('first_name', 'like', $like)
                                    ->orWhere('last_name', 'like', $like)
                                    ->orWhereHas('user', fn (Builder $inner) => $inner->where('login_id', 'like', $like));
                            });
                    });
                }

                $query
                    ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));
            },
        );

        return response()->json([
            'invoices' => InvoiceResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * The student picker for the invoice form: names and admission
     * numbers, searchable and capped. The finance desk holds
     * `invoices.create` rather than `students.view`, so the picker
     * rides the invoice permission instead of the student roster.
     */
    public function students(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim($data['search'] ?? '');

        $students = StudentProfile::query()
            ->with('user:id,login_id,name')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('first_name', 'like', $like)
                        ->orWhere('middle_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhereHas('user', fn (Builder $inner) => $inner->where('login_id', 'like', $like));
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(50)
            ->get()
            ->map(fn (StudentProfile $student): array => [
                'id' => $student->id,
                'name' => User::composeName($student->first_name, $student->middle_name, $student->last_name)
                    ?: ($student->user?->name ?? ''),
                'login_id' => $student->user?->login_id,
            ]);

        return response()->json(['students' => $students]);
    }

    /**
     * Raise an invoice against a student: the number, header row and
     * its lines are written in one transaction, so a half-built
     * invoice can never be read.
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = DB::transaction(function () use ($request): Invoice {
            $validated = $request->validated();

            $invoice = Invoice::create([
                'invoice_number' => $this->nextInvoiceNumber(),
                'student_id' => $validated['student_id'],
                'academic_session_id' => $validated['academic_session_id'],
                'description' => $validated['description'] ?? null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $this->replaceItems($invoice, $validated['items'], $request->user()->id);

            return $invoice;
        });

        $invoice->load(['items', 'student.user', 'academicSession']);
        $invoice->loadSum('items as total_amount', 'amount');

        return response()->json([
            'invoice' => new InvoiceResource($invoice),
        ], Response::HTTP_CREATED);
    }

    /**
     * Show one invoice with its lines and summed total.
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['items', 'student.user', 'academicSession']);
        $invoice->loadSum('items as total_amount', 'amount');

        return response()->json([
            'invoice' => new InvoiceResource($invoice),
        ]);
    }

    /**
     * Update an invoice's status and note and replace its lines,
     * stamping the updating user. The billed student and session are
     * the invoice's anchor and never change here.
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $invoice, $validated): void {
            $invoice->update([
                'status' => $validated['status'],
                'description' => $validated['description'] ?? null,
                'updated_by' => $request->user()->id,
            ]);

            $this->replaceItems($invoice, $validated['items'], $request->user()->id);
        });

        $invoice->load(['items', 'student.user', 'academicSession']);
        $invoice->loadSum('items as total_amount', 'amount');

        return response()->json([
            'invoice' => new InvoiceResource($invoice),
        ]);
    }

    /**
     * Soft delete an invoice. Its number and lines stay on record, so
     * the sequence never reissues the number and the billed history
     * remains auditable.
     */
    public function destroy(Invoice $invoice): Response
    {
        $invoice->delete();

        return response()->noContent();
    }

    /**
     * The next sequential number for the current year — INV-2026-0007 —
     * read with a row lock inside the store transaction so two
     * concurrent invoices can never take the same number. Trashed
     * invoices keep theirs, so their numbers are never reissued.
     */
    private function nextInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('Y').'-';

        $latest = Invoice::withTrashed()
            ->where('invoice_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $sequence = $latest === null ? 1 : ((int) substr($latest, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Swap an invoice's lines for the payload's: the previous lines
     * are soft-deleted — their history stays on record — and the new
     * ones are inserted in payload order, stamped with the acting
     * user.
     *
     * @param  list<array{type: string, description: string, amount: int|float|string}>  $items
     */
    private function replaceItems(Invoice $invoice, array $items, int $userId): void
    {
        $invoice->items()->delete();

        foreach ($items as $item) {
            $invoice->items()->create([
                'type' => $item['type'],
                'description' => $item['description'],
                'amount' => $item['amount'],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }
}
