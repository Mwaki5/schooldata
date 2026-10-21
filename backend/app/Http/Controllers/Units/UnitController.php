<?php

namespace App\Http\Controllers\Units;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Units\StoreUnitRequest;
use App\Http\Requests\Units\UpdateUnitRequest;
use App\Http\Resources\Units\UnitResource;
use App\Models\Unit;
use App\Models\UnitRegistration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnitController extends Controller
{
    use PaginatesLists;

    /**
     * List catalogue units with the shared search / sort / pagination
     * contract, optionally narrowed down to the units linked into one
     * course or curriculum.
     *
     * Each row is one unit in the catalogue; its deliveries inside
     * courses live on `course_units` (see CourseUnitController).
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'course_id' => ['nullable', 'integer'],
            'curriculum_id' => ['nullable', 'integer'],
        ]);

        $paginator = $this->paginateList(
            $request,
            Unit::query()->withCount('courseUnits'),
            searchable: ['code', 'name', 'description'],
            sortable: [
                'code' => 'code',
                'name' => 'name',
                'created_at' => 'created_at',
            ],
            defaultOrder: [['code', 'asc']],
            applyFilters: function (Builder $query) use ($filters): void {
                $query
                    ->when(
                        ! empty($filters['course_id']),
                        fn ($query) => $query->whereHas(
                            'courseUnits',
                            fn ($link) => $link->where('course_id', (int) $filters['course_id']),
                        ),
                    )
                    ->when(
                        ! empty($filters['curriculum_id']),
                        fn ($query) => $query->whereHas(
                            'courseUnits',
                            fn ($link) => $link->where('curriculum_id', (int) $filters['curriculum_id']),
                        ),
                    );
            },
        );

        return response()->json([
            'units' => UnitResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * Create a catalogue unit — step one of the unit wizard. How the
     * unit is taught inside a course is linked in step two through
     * POST /course-units.
     */
    public function store(StoreUnitRequest $request): JsonResponse
    {
        $unit = Unit::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'unit' => new UnitResource($unit->loadCount('courseUnits')),
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a single catalogue unit with every course it is linked into.
     */
    public function show(Unit $unit): JsonResponse
    {
        return response()->json([
            'unit' => new UnitResource(
                $unit->loadCount('courseUnits')->load([
                    'courseUnits.course.certificationLevel',
                    'courseUnits.course.department',
                    'courseUnits.curriculum.examBody',
                ]),
            ),
        ]);
    }

    /**
     * Update a catalogue unit's own details, stamping the updating
     * user. Its course links are managed through /course-units.
     */
    public function update(UpdateUnitRequest $request, Unit $unit): JsonResponse
    {
        $unit->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'unit' => new UnitResource($unit->refresh()->loadCount('courseUnits')),
        ]);
    }

    /**
     * Soft delete a catalogue unit, freeing its code for reuse. Its
     * course links are removed first — they describe deliveries of a
     * unit that no longer exists — but only when no student registered
     * against them: registrations (and their marks) hold the link, and
     * the database restricts the delete as a backstop.
     */
    public function destroy(Unit $unit): Response
    {
        $isRegistered = UnitRegistration::query()
            ->whereIn('course_unit_id', $unit->courseUnits()->select('id'))
            ->exists();

        if ($isRegistered) {
            throw ValidationException::withMessages([
                'unit' => 'Students have registered this unit, so it cannot be removed. Mark its course links inactive instead.',
            ]);
        }

        DB::transaction(function () use ($unit): void {
            $unit->courseUnits()->delete();
            $unit->delete();
        });

        return response()->noContent();
    }
}
