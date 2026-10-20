<?php

namespace App\Http\Controllers\TermEvents;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\TermEvents\StoreTermEventRequest;
use App\Http\Requests\TermEvents\UpdateTermEventRequest;
use App\Http\Resources\TermEvents\TermEventResource;
use App\Models\TermEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TermEventController extends Controller
{
    use PaginatesLists;

    /**
     * List term events newest start date first, with the shared search /
     * sort / pagination contract and the owning session eagerly loaded.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'academic_session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $paginator = $this->paginateList(
            $request,
            TermEvent::query()->with('academicSession:id,name'),
            searchable: ['name'],
            sortable: [
                'name' => 'name',
                'start_date' => 'start_date',
                'end_date' => 'end_date',
                'is_active' => 'is_active',
                'created_at' => 'created_at',
            ],
            defaultOrder: [['start_date', 'desc'], ['id', 'desc']],
            applyFilters: function (Builder $query) use ($filters): void {
                $query
                    ->when($filters['academic_session_id'] ?? null, fn ($query, $sessionId) => $query->where('academic_session_id', $sessionId))
                    ->when(
                        array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                        fn ($query) => $query->where('is_active', (bool) $filters['is_active']),
                    );
            },
        );

        return response()->json([
            'term_events' => TermEventResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * Create a new term event, stamping the creating user.
     */
    public function store(StoreTermEventRequest $request): JsonResponse
    {
        $termEvent = TermEvent::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'term_event' => new TermEventResource(
                $termEvent->load('academicSession:id,name')
            ),
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a single term event.
     */
    public function show(TermEvent $termEvent): JsonResponse
    {
        return response()->json([
            'term_event' => new TermEventResource(
                $termEvent->loadMissing('academicSession:id,name')
            ),
        ]);
    }

    /**
     * Update an existing term event, stamping the updating user.
     */
    public function update(UpdateTermEventRequest $request, TermEvent $termEvent): JsonResponse
    {
        $termEvent->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'term_event' => new TermEventResource(
                $termEvent->refresh()->load('academicSession:id,name')
            ),
        ]);
    }

    /**
     * Delete a term event.
     */
    public function destroy(TermEvent $termEvent): Response
    {
        $termEvent->delete();

        return response()->noContent();
    }
}
