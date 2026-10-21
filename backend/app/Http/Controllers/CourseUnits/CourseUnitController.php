<?php

namespace App\Http\Controllers\CourseUnits;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\CourseUnits\StoreCourseUnitRequest;
use App\Http\Requests\CourseUnits\UpdateCourseUnitRequest;
use App\Http\Resources\Units\CourseUnitResource;
use App\Models\Course;
use App\Models\CourseUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class CourseUnitController extends Controller
{
    use PaginatesLists;

    /**
     * List unit-course links with the shared search / sort / pagination
     * contract: searchable by the linked unit's code and name (the join
     * reaches the catalogue) and filterable down to one course,
     * curriculum, unit or active flag.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'course_id' => ['nullable', 'integer'],
            'curriculum_id' => ['nullable', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $paginator = $this->paginateList(
            $request,
            CourseUnit::query()
                ->join('units', 'units.id', '=', 'course_units.unit_id')
                ->whereNull('units.deleted_at')
                ->select('course_units.*')
                ->with(['unit', 'course.certificationLevel', 'course.department', 'curriculum.examBody']),
            searchable: ['units.code', 'units.name'],
            sortable: [
                'code' => 'units.code',
                'name' => 'units.name',
                'modules_taught' => 'course_units.modules_taught',
                'is_active' => 'course_units.is_active',
                'created_at' => 'course_units.created_at',
            ],
            defaultOrder: [['course_units.modules_taught', 'asc']],
            applyFilters: function (Builder $query) use ($filters): void {
                $query
                    ->when(
                        ! empty($filters['course_id']),
                        fn ($query) => $query->where('course_units.course_id', (int) $filters['course_id']),
                    )
                    ->when(
                        ! empty($filters['curriculum_id']),
                        fn ($query) => $query->where('course_units.curriculum_id', (int) $filters['curriculum_id']),
                    )
                    ->when(
                        ! empty($filters['unit_id']),
                        fn ($query) => $query->where('course_units.unit_id', (int) $filters['unit_id']),
                    )
                    ->when(
                        array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                        fn ($query) => $query->where('course_units.is_active', (bool) $filters['is_active']),
                    );
            },
        );

        return response()->json([
            'course_units' => CourseUnitResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * Link a catalogue unit into a course + curriculum pair — the
     * wizard's second step, or the standalone Course Units page —
     * stamping the creating user.
     */
    public function store(StoreCourseUnitRequest $request): JsonResponse
    {
        $courseUnit = CourseUnit::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'course_unit' => new CourseUnitResource($courseUnit->load(['unit', 'course.certificationLevel', 'course.department', 'curriculum.examBody'])),
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a single unit-course link with its unit, course and
     * curriculum.
     */
    public function show(CourseUnit $courseUnit): JsonResponse
    {
        return response()->json([
            'course_unit' => new CourseUnitResource($courseUnit->load(['unit', 'course.certificationLevel', 'course.department', 'curriculum.examBody'])),
        ]);
    }

    /**
     * Update a unit-course link's delivery fields, stamping the
     * updating user. The linked unit / course / curriculum is the
     * link's identity and stays put.
     */
    public function update(UpdateCourseUnitRequest $request, CourseUnit $courseUnit): JsonResponse
    {
        $courseUnit->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'course_unit' => new CourseUnitResource($courseUnit->refresh()->load(['unit', 'course.certificationLevel', 'course.department', 'curriculum.examBody'])),
        ]);
    }

    /**
     * Remove the link — the catalogue unit itself stays. A link students
     * registered against is protected (the FK restricts it as well): its
     * history is retired by marking it inactive, never unlinked away.
     */
    public function destroy(CourseUnit $courseUnit): Response
    {
        if ($courseUnit->unitRegistrations()->exists()) {
            throw ValidationException::withMessages([
                'course_unit' => 'Students have registered this unit in this course. Mark the link inactive instead of removing it.',
            ]);
        }

        $courseUnit->delete();

        return response()->noContent();
    }

    /**
     * List every unit linked into one course — for reviewing what a
     * course actually teaches, grouped by curriculum by the caller.
     */
    public function byCourse(Course $course): JsonResponse
    {
        $links = $course->courseUnits()
            ->with(['unit', 'course.certificationLevel', 'course.department', 'curriculum.examBody'])
            ->orderBy('id')
            ->get()
            // Sorted by curriculum name, which a portable SQL order
            // cannot reach without joins; the collection is small.
            ->sortBy([
                fn (CourseUnit $left, CourseUnit $right) => strcasecmp($left->curriculum?->name ?? '', $right->curriculum?->name ?? ''),
                fn (CourseUnit $left, CourseUnit $right) => strcasecmp($left->unit?->name ?? '', $right->unit?->name ?? ''),
            ])
            ->values();

        return response()->json([
            'course_units' => CourseUnitResource::collection($links),
        ]);
    }
}
