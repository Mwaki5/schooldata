<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\RegisterSessionUnitsRequest;
use App\Models\AcademicSession;
use App\Models\AcademicSessionRegistration;
use App\Models\CourseUnit;
use App\Models\StudentProfile;
use App\Models\UnitRegistration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SessionUnitsController extends Controller
{
    /**
     * The units offered to the signed-in student for the running session:
     * the active units of their enrolled course, taught at the module they
     * reported for. Nothing is offered until they have reported.
     */
    public function index(Request $request): JsonResponse
    {
        // Queried through the relation rather than as a magic property so
        // lazy-loading prevention never trips on the auth user.
        $profile = $request->user()->studentProfile()->first();

        $session = $this->runningSession();

        $payload = [
            'session' => $session === null ? null : $this->sessionPayload($session),
            'reported' => false,
            'module_no' => null,
            'units' => [],
        ];

        if ($profile === null || $session === null) {
            return response()->json($payload);
        }

        $registration = $this->sessionRegistration($session->id, $profile->id);

        if ($registration === null) {
            return response()->json($payload);
        }

        $registeredIds = UnitRegistration::query()
            ->where('academic_session_id', $session->id)
            ->where('student_id', $profile->id)
            ->pluck('course_unit_id');

        $units = $this->offeredUnits($profile, $registration)
            ->with('unit')
            ->get()
            ->map(fn (CourseUnit $link): array => [
                'course_unit_id' => $link->id,
                'code' => $link->unit->code,
                'name' => $link->unit->name,
                'unit_type' => $link->unit_type->value,
                'credit_factor' => $link->credit_factor,
                'registered' => $registeredIds->contains($link->id),
            ])
            ->values();

        return response()->json([
            'session' => $this->sessionPayload($session),
            'reported' => true,
            'module_no' => $registration->module_no,
            'units' => $units,
        ]);
    }

    /**
     * Register the selected course units for the running session.
     *
     * The ids are course-unit links, not bare catalogue units: only
     * links actually offered to the student are accepted, and links
     * already registered are skipped, so repeat submissions stay
     * harmless. Approval is a later, staff-side act.
     */
    public function store(RegisterSessionUnitsRequest $request): JsonResponse
    {
        $user = $request->user();

        $profile = $user->studentProfile()->firstOrFail();

        $session = $this->runningSession();

        if ($session === null) {
            throw ValidationException::withMessages([
                'course_units' => 'There is no active academic session to register units for.',
            ]);
        }

        $registration = $this->sessionRegistration($session->id, $profile->id);

        if ($registration === null) {
            throw ValidationException::withMessages([
                'course_units' => 'Report for this session before registering units.',
            ]);
        }

        $offeredIds = $this->offeredUnits($profile, $registration)->pluck('course_units.id');

        $requestedIds = collect($request->validated('course_units'));

        if ($requestedIds->diff($offeredIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'course_units' => 'One or more selected units are not offered for this session.',
            ]);
        }

        $alreadyRegisteredIds = UnitRegistration::query()
            ->where('academic_session_id', $session->id)
            ->where('student_id', $profile->id)
            ->whereIn('course_unit_id', $requestedIds)
            ->pluck('course_unit_id');

        $newIds = $requestedIds->diff($alreadyRegisteredIds)->values();

        DB::transaction(function () use ($newIds, $session, $profile, $user): void {
            foreach ($newIds as $courseUnitId) {
                UnitRegistration::query()->firstOrCreate(
                    [
                        'academic_session_id' => $session->id,
                        'course_unit_id' => $courseUnitId,
                        'student_id' => $profile->id,
                    ],
                    [
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ],
                );
            }
        });

        return response()->json([
            'registered' => $newIds->all(),
        ], 201);
    }

    /**
     * The academic session currently running, when there is one.
     */
    private function runningSession(): ?AcademicSession
    {
        return AcademicSession::query()
            ->where('is_active', true)
            ->latest('start_date')
            ->first();
    }

    /**
     * The student's "report session" registration for the session, when
     * they have reported.
     */
    private function sessionRegistration(int $sessionId, int $profileId): ?AcademicSessionRegistration
    {
        return AcademicSessionRegistration::query()
            ->where('academic_session_id', $sessionId)
            ->where('student_id', $profileId)
            ->first();
    }

    /**
     * The units offered to the student: active course-unit links of
     * their enrolled course, taught at the module they reported for.
     *
     * @return Builder<CourseUnit>
     */
    private function offeredUnits(StudentProfile $profile, AcademicSessionRegistration $registration): Builder
    {
        $enrolment = $profile->latestEnrolment()->first();

        return CourseUnit::query()
            ->join('units', 'units.id', '=', 'course_units.unit_id')
            ->whereNull('units.deleted_at')
            ->where('course_units.is_active', true)
            ->where('course_units.course_id', $enrolment?->course_id)
            ->where('course_units.curriculum_id', $enrolment?->curriculum_id)
            ->where('course_units.modules_taught', $registration->module_no)
            ->select('course_units.*')
            ->orderBy('units.code')
            ->orderBy('units.name');
    }

    /**
     * The session segment the units payload renders with.
     *
     * @return array{id: int, name: string, start_date: string|null, end_date: string|null}
     */
    private function sessionPayload(AcademicSession $session): array
    {
        return [
            'id' => $session->id,
            'name' => $session->name,
            'start_date' => $session->start_date?->toDateString(),
            'end_date' => $session->end_date?->toDateString(),
        ];
    }
}
