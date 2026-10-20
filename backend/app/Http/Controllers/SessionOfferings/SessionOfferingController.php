<?php

namespace App\Http\Controllers\SessionOfferings;

use App\Enums\OfferingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SessionOfferings\ToggleOfferingRequest;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseStage;
use App\Models\Department;
use App\Models\SessionStageOffering;
use App\Services\Offerings\OfferingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The "Open Session" wizard endpoints: one aggregate read that returns
 * matching courses with their stages, current offerings and suggestions,
 * plus the single-stage toggle used by each checkbox.
 */
class SessionOfferingController extends Controller
{
    public function __construct(private readonly OfferingService $offerings) {}

    /**
     * Return the course × stage grid for a session, pre-annotated with
     * suggestions and existing offerings. Search, department and stage
     * filters are applied in the database rather than in the browser.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', 'min:0'],
            'stage_code' => ['nullable', 'string', 'max:10'],
        ]);

        $session = AcademicSession::query()
            ->with('academicYear:id,name')
            ->findOrFail($validated['academic_session_id']);

        $suggestions = $this->offerings->suggestOfferings($session->id)->keyBy('course_stage_id');
        $window = $this->offerings->registrationWindow($session->id);
        $search = trim((string) ($validated['search'] ?? ''));
        $departmentId = $validated['department_id'] ?? null;
        $stageCode = $validated['stage_code'] ?? null;

        $courses = Course::query()
            ->with([
                'department:id,name',
                'stages' => fn ($query) => $query->orderBy('module_no'),
                'stages.offerings' => fn ($query) => $query->where('academic_session_id', $session->id),
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('code', 'like', $like)
                        ->orWhere('initials', 'like', $like)
                        ->orWhere('name', 'like', $like);
                });
            })
            ->when($departmentId !== null, function (Builder $query) use ($departmentId): void {
                if ((int) $departmentId === 0) {
                    $query->whereNull('department_id');

                    return;
                }

                $query->where('department_id', (int) $departmentId);
            })
            ->when(
                $stageCode !== null && $stageCode !== '',
                fn (Builder $query) => $query->whereHas(
                    'stages',
                    fn (Builder $stages) => $stages->where('code', $stageCode),
                ),
            )
            ->orderBy('name')
            ->get();

        $coursePayload = [];

        foreach ($courses as $course) {
            $stages = [];

            foreach ($course->stages as $stage) {
                $offering = $stage->offerings->first();
                $suggestion = $suggestions->get($stage->id);

                $stages[] = [
                    'id' => $stage->id,
                    'module_no' => $stage->module_no,
                    'year_no' => $stage->year_no,
                    'session_no' => $stage->session_no,
                    'code' => $stage->code,
                    'suggested' => $suggestion !== null,
                    'source' => $suggestion['source'] ?? null,
                    'is_open' => $offering?->status === OfferingStatus::Open,
                ];
            }

            $coursePayload[] = [
                'id' => $course->id,
                'code' => $course->code,
                'initials' => $course->initials,
                'name' => $course->name,
                'total_modules' => $course->total_modules,
                'entry_grade' => $course->entry_grade,
                'is_active' => $course->is_active,
                'department_id' => $course->department_id,
                'department_name' => $course->department?->name,
                'stages' => $stages,
            ];
        }

        return response()->json([
            'session' => [
                'id' => $session->id,
                'name' => $session->name,
                'start_date' => $session->start_date?->toDateString(),
                'end_date' => $session->end_date?->toDateString(),
                'academic_year_id' => $session->academic_year_id,
                'academic_year_name' => $session->academicYear?->name,
                'slot' => $this->offerings->sessionSlot($session),
            ],
            'registration_window' => $window === null ? null : [
                'event_id' => $window['event']->id,
                'event_name' => $window['event']->name->value,
                'starts_on' => $window['event']->start_date->toDateString(),
                'ends_on' => $window['event']->end_date->toDateString(),
                'opens_at' => $window['opens_at']->toIso8601String(),
                'closes_at' => $window['closes_at']->toIso8601String(),
                'is_active' => $window['event']->is_active,
                'state' => $this->offerings->registrationState($window),
            ],
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'stage_codes' => CourseStage::query()->distinct()->orderBy('code')->pluck('code'),
            'stats' => [
                'open_stages' => SessionStageOffering::query()
                    ->where('academic_session_id', $session->id)
                    ->where('status', OfferingStatus::Open)
                    ->count(),
                'suggested_stages' => $suggestions->count(),
            ],
            'courses' => $coursePayload,
        ]);
    }

    /**
     * Open or close a single stage as soon as its checkbox is toggled.
     */
    public function toggle(ToggleOfferingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'offering' => $this->offerings->toggleOffering(
                (int) $validated['academic_session_id'],
                (int) $validated['course_stage_id'],
                (bool) $validated['open'],
                $request->user()->id,
            ),
        ]);
    }
}
