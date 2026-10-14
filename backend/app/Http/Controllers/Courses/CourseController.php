<?php

namespace App\Http\Controllers\Courses;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courses\StoreCourseRequest;
use App\Http\Requests\Courses\UpdateCourseRequest;
use App\Http\Resources\Courses\CourseResource;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CourseController extends Controller
{
    use PaginatesLists;

    /**
     * List courses ordered by name, with the shared search / sort /
     * pagination contract and both referencing columns eagerly loaded.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'certification_level_id' => ['nullable', 'integer', 'exists:certification_levels,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $paginator = $this->paginateList(
            $request,
            Course::query()->with(['certificationLevel', 'department']),
            searchable: ['name', 'code', 'initials'],
            sortable: [
                'name' => 'name',
                'code' => 'code',
                'duration_months' => 'duration_months',
                'is_active' => 'is_active',
                'created_at' => 'created_at',
            ],
            defaultOrder: [['name', 'asc']],
            applyFilters: function (Builder $query) use ($filters): void {
                $query
                    ->when($filters['certification_level_id'] ?? null, fn ($query, $levelId) => $query->where('certification_level_id', $levelId))
                    ->when($filters['department_id'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId))
                    ->when(
                        array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                        fn ($query) => $query->where('is_active', (bool) $filters['is_active']),
                    );
            },
        );

        return response()->json([
            'courses' => CourseResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * Create a new course, stamping the creating user.
     */
    public function store(StoreCourseRequest $request): JsonResponse
    {
        $course = Course::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'course' => new CourseResource(
                $course->load(['certificationLevel', 'department']),
            ),
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a single course.
     */
    public function show(Course $course): JsonResponse
    {
        return response()->json([
            'course' => new CourseResource(
                $course->load(['certificationLevel', 'department']),
            ),
        ]);
    }

    /**
     * Update an existing course, stamping the updating user.
     */
    public function update(UpdateCourseRequest $request, Course $course): JsonResponse
    {
        $course->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'course' => new CourseResource(
                $course->refresh()->load(['certificationLevel', 'department']),
            ),
        ]);
    }

    /**
     * Soft delete a course.
     */
    public function destroy(Course $course): Response
    {
        $course->delete();

        return response()->noContent();
    }
}
