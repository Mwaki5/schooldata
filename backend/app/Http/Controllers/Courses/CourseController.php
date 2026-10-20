<?php

namespace App\Http\Controllers\Courses;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courses\StoreCourseRequest;
use App\Http\Requests\Courses\UpdateCourseRequest;
use App\Http\Resources\Courses\CourseResource;
use App\Models\Course;
use App\Services\Offerings\CourseStageGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    use PaginatesLists;

    public function __construct(private readonly CourseStageGenerator $stageGenerator) {}

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
                'total_modules' => 'total_modules',
                'entry_grade' => 'entry_grade',
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
        $course = DB::transaction(function () use ($request): Course {
            $course = Course::create([
                ...$request->validated(),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            // Every course gets its full progression ladder immediately;
            // the backfill command covers courses created before this.
            $this->stageGenerator->generate($course);

            return $course;
        });

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
        $originalModules = $course->total_modules;

        DB::transaction(function () use ($request, $course, $originalModules): void {
            $course->update([
                ...$request->validated(),
                'updated_by' => $request->user()->id,
            ]);

            // Extending a course tops up its ladder. Shrinking it never
            // deletes stages that already carry offerings or registrations.
            if ($originalModules !== $course->total_modules) {
                $this->stageGenerator->generate($course);
            }
        });

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
