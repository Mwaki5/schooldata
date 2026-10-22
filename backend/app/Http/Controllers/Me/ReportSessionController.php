<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\AcademicSessionRegistration;
use App\Services\Offerings\OfferingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportSessionController extends Controller
{
    public function __construct(private readonly OfferingService $offerings) {}

    /**
     * Record a "report session" registration for the signed-in student.
     *
     * Reporting is allowed only when the stage the student's course sits
     * at is open for the running session; the offering service applies
     * the whole rule chain — the offering row and the active Session
     * Registration window. The module (and its derived year / session)
     * is taken from that stage.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('student')) {
            abort(403, 'Only students can report for a session.');
        }

        $profile = $user->studentProfile()->firstOrFail();

        $session = AcademicSession::query()
            ->where('is_active', true)
            ->latest('start_date')
            ->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'session' => 'There is no active academic session to report for.',
            ]);
        }

        $enrolment = $profile->latestEnrolment()->with('courseStage')->first();

        if (! $enrolment || ! $enrolment->course_stage_id || ! $enrolment->courseStage) {
            throw ValidationException::withMessages([
                'session' => 'You are not enrolled on a course stage yet.',
            ]);
        }

        $stage = $enrolment->courseStage;
        $result = $this->offerings->isStageOpen($session->id, $stage->id);

        if (! $result->open) {
            throw ValidationException::withMessages([
                'session' => $result->reason->message($stage->code),
            ]);
        }

        $registration = DB::transaction(function () use ($profile, $session, $stage, $request): AcademicSessionRegistration {
            $attributes = [
                'academic_session_id' => $session->id,
                'student_id' => $profile->id,
                'course_stage_id' => $stage->id,
                'module_no' => $stage->module_no,
                'year_no' => $stage->year_no,
                'session_no' => $stage->session_no,
            ];

            $registration = AcademicSessionRegistration::query()
                ->where('academic_session_id', $session->id)
                ->where('student_id', $profile->id)
                ->where('module_no', $stage->module_no)
                ->first();

            if ($registration) {
                $registration->update([
                    ...$attributes,
                    'updated_by' => $request->user()->id,
                ]);

                return $registration;
            }

            return AcademicSessionRegistration::create([
                ...$attributes,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });

        return response()->json([
            'registration' => [
                'id' => $registration->id,
                'academic_session_id' => $registration->academic_session_id,
                'module_no' => $registration->module_no,
                'year_no' => $registration->year_no,
                'session_no' => $registration->session_no,
                'course_stage_code' => $stage->code,
            ],
        ], 201);
    }
}
