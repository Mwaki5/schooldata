<?php

namespace App\Http\Controllers\CourseUnits;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourseUnits\StoreCourseUnitRequest;
use App\Http\Requests\CourseUnits\UpdateCourseUnitRequest;
use App\Http\Resources\CourseUnits\CourseUnitResource;
use App\Models\Course;
use App\Models\CourseUnit;
use App\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseUnitController extends Controller
{
    /**
     * List every assignment of one unit.
     *
     * No pagination: a unit is delivered at most once per course +
     * curriculum, so the set is inherently small and the details page
     * renders it in full.
     */
    public function index(Unit $unit): JsonResponse
    {
        $assignments = $unit->assignments()
            ->with(['unit:id,name', 'course:id,code,name', 'curriculum:id,name'])
            ->orderBy('id')
            ->get()
            // Sorted by related names, which a portable SQL order cannot
            // reach without joins; the collection is small by design.
            ->sortBy([
                fn (CourseUnit $left, CourseUnit $right) => strcasecmp($left->course?->name ?? '', $right->course?->name ?? ''),
                fn (CourseUnit $left, CourseUnit $right) => strcasecmp($left->curriculum?->name ?? '', $right->curriculum?->name ?? ''),
            ])
            ->values();

        return response()->json([
            'assignments' => CourseUnitResource::collection($assignments),
        ]);
    }

    /**
     * List every assignment under one course — the course-side mirror of
     * a unit's own details page, for reviewing what a course actually
     * teaches. The caller groups the rows per curriculum, which is where
     * a unit is really delivered.
     */
    public function byCourse(Course $course): JsonResponse
    {
        $assignments = $course->assignments()
            ->with(['unit:id,code,name,is_active', 'curriculum:id,name', 'course:id,code,name'])
            ->orderBy('id')
            ->get()
            // Sorted by related names, which a portable SQL order cannot
            // reach without joins; the collection is small by design.
            ->sortBy([
                fn (CourseUnit $left, CourseUnit $right) => strcasecmp($left->curriculum?->name ?? '', $right->curriculum?->name ?? ''),
                fn (CourseUnit $left, CourseUnit $right) => strcasecmp($left->unit?->name ?? '', $right->unit?->name ?? ''),
            ])
            ->values();

        return response()->json([
            'assignments' => CourseUnitResource::collection($assignments),
        ]);
    }

    /**
     * Assign the unit to several course + curriculum combinations in
     * one transactional batch: either every row is created or none.
     */
    public function store(StoreCourseUnitRequest $request, Unit $unit): JsonResponse
    {
        $user = $request->user();

        try {
            $assignments = DB::transaction(
                fn () => collect($request->validated('assignments'))
                    ->map(fn (array $row) => CourseUnit::create([
                        ...$row,
                        'unit_id' => $unit->id,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]))
                    ->each(fn (CourseUnit $assignment) => $assignment->load(['unit', 'course', 'curriculum']))
                    ->all(),
            );
        } catch (QueryException $exception) {
            // The unique index is the final guard against a concurrent
            // request slipping the same assignment in between validation
            // and insert; a duplicate key becomes a clear validation
            // error, anything else surfaces as a generic server error.
            if (str_starts_with((string) $exception->errorInfo[0], '23')) {
                throw ValidationException::withMessages([
                    'assignments' => 'One of the selected courses already has this unit assigned. Refresh the page and try again.',
                ]);
            }

            throw $exception;
        }

        return response()->json([
            'assignments' => CourseUnitResource::collection($assignments),
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a single assignment with its unit, course and curriculum.
     */
    public function show(CourseUnit $courseUnit): JsonResponse
    {
        return response()->json([
            'course_unit' => new CourseUnitResource(
                $courseUnit->load(['unit', 'course', 'curriculum']),
            ),
        ]);
    }

    /**
     * Update an assignment's own details — its teaching load is
     * independent from every other delivery of the same unit.
     */
    public function update(UpdateCourseUnitRequest $request, CourseUnit $courseUnit): JsonResponse
    {
        $courseUnit->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'course_unit' => new CourseUnitResource(
                $courseUnit->refresh()->load(['unit', 'course', 'curriculum']),
            ),
        ]);
    }

    /**
     * Remove one delivery of the unit, keeping the master untouched.
     */
    public function destroy(CourseUnit $courseUnit): Response
    {
        $courseUnit->delete();

        return response()->noContent();
    }
}
