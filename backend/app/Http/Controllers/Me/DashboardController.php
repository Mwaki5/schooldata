<?php

namespace App\Http\Controllers\Me;

use App\Enums\EnrolmentStatus;
use App\Enums\TermEventName;
use App\Http\Controllers\Controller;
use App\Http\Resources\Enrolments\EnrolmentResource;
use App\Http\Resources\Students\StudentResource;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\CourseEnrolment;
use App\Models\TermEvent;
use App\Models\Unit;
use App\Services\Offerings\OfferingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * The relations the enrolment payloads render from, mirroring the
     * admin enrolment list.
     *
     * @var list<string>
     */
    private const LOADS = ['student.user', 'course', 'courseStage', 'curriculum', 'academicSession', 'certificationLevel'];

    public function __construct(private readonly OfferingService $offerings) {}

    /**
     * Everything the signed-in student's dashboard shows, scoped strictly
     * to the requesting account: their profile, the running academic year
     * and term, enrolment totals and the enrolment rows themselves.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        // Queried through the relation (with `user` eager loaded for the
        // resource) rather than as a magic property, so the app-wide
        // lazy-loading prevention never trips on the auth user.
        $profile = $user->studentProfile()->with('user')->first();

        $enrolments = $profile === null
            ? collect()
            : CourseEnrolment::query()
                ->where('student_id', $profile->id)
                ->with(self::LOADS)
                ->orderByDesc('enrolment_date')
                ->orderByDesc('id')
                ->get();

        $courseIds = $enrolments->pluck('course_id')->unique()->values();

        $session = AcademicSession::query()
            ->where('is_active', true)
            ->latest('start_date')
            ->first();

        return response()->json([
            'profile' => $profile === null ? null : new StudentResource($profile),
            'academic' => [
                'year' => AcademicYear::query()
                    ->where('is_active', true)
                    ->latest('start_date')
                    ->first(['id', 'name', 'start_date', 'end_date', 'is_active']),
                'session' => $session,
            ],
            'report_session' => $this->reportSessionState($profile, $session),
            'stats' => [
                'enrolments' => $enrolments->count(),
                'active' => $enrolments->where('status', EnrolmentStatus::Enrolled)->count(),
                'completed' => $enrolments->where('status', EnrolmentStatus::Completed)->count(),
                'courses' => $courseIds->count(),
                'units' => $courseIds->isEmpty()
                    ? 0
                    : Unit::query()->whereIn('course_id', $courseIds)->count(),
            ],
            'enrolments' => EnrolmentResource::collection($enrolments),
        ]);
    }

    /**
     * Whether the signed-in student may report for the running session.
     *
     * Reporting needs both: an active Session Registration term event on
     * the session, and the stage the student's course sits at being open
     * for that session. The reason is returned so the UI can explain why
     * the button is unavailable.
     *
     * @return array{can_report: bool, reason: string|null, module_no: int|null, stage_code: string|null}
     */
    private function reportSessionState($profile, ?AcademicSession $session): array
    {
        $unavailable = fn (string $reason): array => [
            'can_report' => false,
            'reason' => $reason,
            'module_no' => null,
            'stage_code' => null,
        ];

        if (! $session) {
            return $unavailable('There is no active academic session to report for.');
        }

        $event = TermEvent::query()
            ->where('academic_session_id', $session->id)
            ->where('name', TermEventName::SessionRegistration)
            ->first();

        if (! $event || ! $event->is_active) {
            return $unavailable('Session registration is not open for this term.');
        }

        if ($profile === null) {
            return $unavailable('No student profile is linked to this account yet.');
        }

        $stage = $profile->latestEnrolment()->with('courseStage')->first()?->courseStage;

        if (! $stage) {
            return $unavailable('You are not enrolled on a course stage yet.');
        }

        $result = $this->offerings->isStageOpen($session->id, $stage->id);

        if (! $result->open) {
            return $unavailable("{$stage->code} is not open for this session.");
        }

        return [
            'can_report' => true,
            'reason' => null,
            'module_no' => $stage->module_no,
            'stage_code' => $stage->code,
        ];
    }
}
