<?php

namespace App\Http\Controllers\Me;

use App\Enums\EnrolmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Enrolments\EnrolmentResource;
use App\Http\Resources\Students\StudentResource;
use App\Models\AcademicSession;
use App\Models\AcademicSessionRegistration;
use App\Models\AcademicYear;
use App\Models\CourseEnrolment;
use App\Models\CourseUnit;
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
                    : CourseUnit::query()->whereIn('course_id', $courseIds)->count(),
            ],
            'enrolments' => EnrolmentResource::collection($enrolments),
        ]);
    }

    /**
     * Whether the signed-in student may report for the running session.
     *
     * Reporting needs an open stage on the student's latest enrolment —
     * the offering service applies the whole rule chain, including the
     * active Session Registration window — and no registration recorded
     * yet for this session. The reason is returned so the UI can explain
     * why the button is unavailable, and the student's level (year and
     * session of their current stage) travels with every state.
     *
     * @return array{can_report: bool, reason: string|null, module_no: int|null, year_no: int|null, session_no: int|null, stage_code: string|null}
     */
    private function reportSessionState($profile, ?AcademicSession $session): array
    {
        $stage = $profile?->latestEnrolment()->with('courseStage')->first()?->courseStage;

        $state = fn (bool $canReport, ?string $reason = null, ?int $moduleNo = null): array => [
            'can_report' => $canReport,
            'reason' => $reason,
            'module_no' => $moduleNo,
            'year_no' => $stage?->year_no,
            'session_no' => $stage?->session_no,
            'stage_code' => $stage?->code,
        ];

        if (! $session) {
            return $state(false, 'There is no active academic session to report for.');
        }

        if ($profile === null) {
            return $state(false, 'No student profile is linked to this account yet.');
        }

        $alreadyReported = AcademicSessionRegistration::query()
            ->where('academic_session_id', $session->id)
            ->where('student_id', $profile->id)
            ->exists();

        if ($alreadyReported) {
            return $state(false, 'You have already reported for this session.');
        }

        if (! $stage) {
            return $state(false, 'You are not enrolled on a course stage yet.');
        }

        $result = $this->offerings->isStageOpen($session->id, $stage->id);

        if (! $result->open) {
            return $state(false, $result->reason->message($stage->code));
        }

        return $state(true, null, $stage->module_no);
    }
}
