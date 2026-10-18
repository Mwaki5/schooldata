<?php

namespace App\Http\Controllers\Units;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Units\StoreUnitRequest;
use App\Http\Requests\Units\UpdateUnitRequest;
use App\Http\Resources\Units\UnitResource;
use App\Models\Course;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UnitController extends Controller
{
    use PaginatesLists;

    /**
     * List unit offerings with the shared search / sort / pagination
     * contract, filterable down to one course and curriculum.
     *
     * Each row is one delivery of a unit inside a course + curriculum
     * pair; the same code may appear once per pair.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'course_id' => ['nullable', 'integer'],
            'curriculum_id' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $paginator = $this->paginateList(
            $request,
            Unit::query()->with(['course.certificationLevel', 'course.department', 'curriculum.examBody']),
            searchable: ['code', 'name', 'description'],
            sortable: [
                'code' => 'code',
                'name' => 'name',
                'course_id' => 'course_id',
                'is_active' => 'is_active',
                'created_at' => 'created_at',
            ],
            defaultOrder: [['name', 'asc']],
            applyFilters: function (Builder $query) use ($filters): void {
                $query
                    ->when(
                        ! empty($filters['course_id']),
                        fn ($query) => $query->where('course_id', (int) $filters['course_id']),
                    )
                    ->when(
                        ! empty($filters['curriculum_id']),
                        fn ($query) => $query->where('curriculum_id', (int) $filters['curriculum_id']),
                    )
                    ->when(
                        array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                        fn ($query) => $query->where('is_active', (bool) $filters['is_active']),
                    );
            },
        );

        return response()->json([
            'units' => UnitResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * Create a unit offering for one course + curriculum, stamping the
     * creating user. The same code may already exist under a different
     * course or curriculum; within this pair it must be unique.
     */
    public function store(StoreUnitRequest $request): JsonResponse
    {
        $unit = Unit::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'unit' => new UnitResource($unit->load(['course.certificationLevel', 'course.department', 'curriculum.examBody'])),
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a single unit offering with its course and curriculum.
     */
    public function show(Unit $unit): JsonResponse
    {
        return response()->json([
            'unit' => new UnitResource($unit->load(['course.certificationLevel', 'course.department', 'curriculum.examBody'])),
        ]);
    }

    /**
     * List every unit offered under one course — for reviewing what a
     * course actually teaches, grouped by curriculum by the caller.
     */
    public function byCourse(Course $course): JsonResponse
    {
        $units = $course->units()
            ->with('curriculum.examBody')
            ->orderBy('id')
            ->get()
            // Sorted by curriculum name, which a portable SQL order
            // cannot reach without joins; the collection is small.
            ->sortBy([
                fn (Unit $left, Unit $right) => strcasecmp($left->curriculum?->name ?? '', $right->curriculum?->name ?? ''),
                fn (Unit $left, Unit $right) => strcasecmp($left->name, $right->name),
            ])
            ->values();

        return response()->json([
            'units' => UnitResource::collection($units),
        ]);
    }

    /**
     * Update a unit offering, stamping the updating user.
     */
    public function update(UpdateUnitRequest $request, Unit $unit): JsonResponse
    {
        $unit->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'unit' => new UnitResource($unit->refresh()->load(['course.certificationLevel', 'course.department', 'curriculum.examBody'])),
        ]);
    }

    /**
     * Soft delete a unit offering, freeing its code for reuse within
     * the course + curriculum pair.
     */
    public function destroy(Unit $unit): Response
    {
        $unit->delete();

        return response()->noContent();
    }
}
