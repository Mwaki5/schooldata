<?php

namespace App\Http\Controllers\Enrolments;

use App\Enums\EnrolmentStatus;
use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Resources\Enrolments\EnrolmentResource;
use App\Models\CourseEnrolment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class EnrolmentController extends Controller
{
    use PaginatesLists;

    /**
     * The relations every enrolment payload renders from.
     *
     * @var list<string>
     */
    private const LOADS = ['student.user', 'course', 'courseStage', 'curriculum', 'academicSession', 'certificationLevel'];

    /**
     * List course enrolments newest first, with the shared search / sort /
     * pagination contract and every display relation loaded.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(EnrolmentStatus::class)],
            'student_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'curriculum_id' => ['nullable', 'integer', 'exists:curricula,id'],
            'academic_session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
            'certification_level_id' => ['nullable', 'integer', 'exists:certification_levels,id'],
        ]);

        $paginator = $this->paginateList(
            $request,
            CourseEnrolment::query()->with(self::LOADS),
            // Search spans the student, course and curriculum text, so the
            // whole clause is composed in `applyFilters` below.
            searchable: [],
            sortable: [
                'enrolment_date' => 'enrolment_date',
                'status' => 'status',
                'created_at' => 'created_at',
            ],
            defaultOrder: [['enrolment_date', 'desc']],
            applyFilters: function (Builder $query) use ($filters, $request): void {
                $search = trim((string) $request->query('search', ''));

                if ($search !== '') {
                    $like = '%'.addcslashes($search, '%_\\').'%';

                    $query->where(function (Builder $query) use ($like): void {
                        $query->whereHas('student', function (Builder $query) use ($like): void {
                            $query->where('first_name', 'like', $like)
                                ->orWhere('middle_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhereHas('user', function (Builder $query) use ($like): void {
                                    $query->where('login_id', 'like', $like)
                                        ->orWhere('name', 'like', $like);
                                });
                        })
                            ->orWhereHas('course', function (Builder $query) use ($like): void {
                                $query->where('code', 'like', $like)
                                    ->orWhere('name', 'like', $like);
                            })
                            ->orWhereHas('curriculum', fn (Builder $query) => $query->where('name', 'like', $like));
                    });
                }

                $query
                    ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                    ->when($filters['student_id'] ?? null, fn ($query, $studentId) => $query->where('student_id', $studentId))
                    ->when($filters['course_id'] ?? null, fn ($query, $courseId) => $query->where('course_id', $courseId))
                    ->when($filters['curriculum_id'] ?? null, fn ($query, $curriculumId) => $query->where('curriculum_id', $curriculumId))
                    ->when($filters['academic_session_id'] ?? null, fn ($query, $sessionId) => $query->where('academic_session_id', $sessionId))
                    ->when($filters['certification_level_id'] ?? null, fn ($query, $levelId) => $query->where('certification_level_id', $levelId));
            },
        );

        return response()->json([
            'enrolments' => EnrolmentResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * Show a single enrolment.
     */
    public function show(CourseEnrolment $enrolment): JsonResponse
    {
        return response()->json([
            'enrolment' => new EnrolmentResource($enrolment->load(self::LOADS)),
        ]);
    }

    /**
     * Soft delete an enrolment.
     */
    public function destroy(CourseEnrolment $enrolment): Response
    {
        $enrolment->delete();

        return response()->noContent();
    }
}
