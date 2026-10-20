<?php

namespace App\Services\Offerings;

use App\Enums\EnrolmentStatus;
use App\Enums\OfferingStatus;
use App\Enums\StageOpenReason;
use App\Enums\TermEventName;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\CourseStage;
use App\Models\SessionStageOffering;
use App\Models\TermEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single source of truth for session stage availability: whether a
 * stage is open right now, which stages the wizard should suggest, and
 * how a single offering is toggled.
 *
 * Registration callers must never inspect offerings directly — they go
 * through {@see self::isStageOpen()} so an override table can be layered
 * in later without changing any caller.
 */
class OfferingService
{
    /**
     * Evaluate one stage in one session at a point in time.
     */
    public function isStageOpen(
        int $academicSessionId,
        int $courseStageId,
        ?CarbonInterface $at = null,
    ): StageOpenResult {
        $offering = SessionStageOffering::query()
            ->where('academic_session_id', $academicSessionId)
            ->where('course_stage_id', $courseStageId)
            ->first();

        return $this->evaluate(
            $offering,
            $this->registrationWindow($academicSessionId),
            $at ?? now(),
        );
    }

    /**
     * The registration window assigned to a session by its Session
     * Registration term event. Dates are inclusive: the window opens at
     * the start of the first day and closes at the end of the last day.
     *
     * @return array{event: TermEvent, opens_at: Carbon, closes_at: Carbon}|null
     */
    public function registrationWindow(int $academicSessionId): ?array
    {
        $event = TermEvent::query()
            ->where('academic_session_id', $academicSessionId)
            ->where('name', TermEventName::SessionRegistration)
            ->first();

        if ($event === null) {
            return null;
        }

        return [
            'event' => $event,
            'opens_at' => $event->start_date->copy()->startOfDay(),
            'closes_at' => $event->end_date->copy()->endOfDay(),
        ];
    }

    /**
     * The display state of a registration window at a point in time.
     *
     * @param  array{event: TermEvent, opens_at: Carbon, closes_at: Carbon}|null  $window
     */
    public function registrationState(?array $window, ?CarbonInterface $at = null): string
    {
        if ($window === null) {
            return 'not_configured';
        }

        $at ??= now();

        if ($at->lt($window['opens_at'])) {
            return 'upcoming';
        }

        if ($at->gt($window['closes_at'])) {
            return 'ended';
        }

        return 'open';
    }

    /**
     * Suggest the stages to open for a session, highest priority first:
     * active cohorts, then the same slot last year, then new intake.
     *
     * @return Collection<int, array{course_stage_id: int, course_id: int, code: string, module_no: int, source: string}>
     */
    public function suggestOfferings(int $academicSessionId): Collection
    {
        $session = AcademicSession::query()->findOrFail($academicSessionId);
        $sessionOrder = $this->sessionOrder();
        $stagesByCourse = CourseStage::query()
            ->orderBy('module_no')
            ->get()
            ->groupBy('course_id');

        /** @var array<int, array{course_stage_id: int, course_id: int, code: string, module_no: int, source: string}> $suggestions */
        $suggestions = [];

        $this->suggestFromCohorts($session, $stagesByCourse, $suggestions, $sessionOrder);
        $this->suggestFromLastYear($session, $suggestions);
        $this->suggestFromNewIntake($session, $stagesByCourse, $suggestions);

        return collect($suggestions)
            ->sortBy([['course_id', 'asc'], ['module_no', 'asc']])
            ->values();
    }

    /**
     * Open or close exactly one stage in a session.
     *
     * Closing a stage that has never been opened leaves no row behind:
     * absence and a closed row both mean "not accepting registrations".
     *
     * @return array{course_stage_id: int, is_open: bool, changed: bool}
     */
    public function toggleOffering(
        int $academicSessionId,
        int $courseStageId,
        bool $open,
        ?int $userId = null,
    ): array {
        $session = AcademicSession::query()->findOrFail($academicSessionId);

        return DB::transaction(function () use ($academicSessionId, $session, $courseStageId, $open, $userId): array {
            $offering = SessionStageOffering::query()
                ->where('academic_session_id', $academicSessionId)
                ->where('course_stage_id', $courseStageId)
                ->lockForUpdate()
                ->first();

            $changed = false;

            if ($open) {
                if ($offering === null) {
                    SessionStageOffering::create([
                        'academic_session_id' => $academicSessionId,
                        'course_stage_id' => $courseStageId,
                        'status' => OfferingStatus::Open,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                    $changed = true;
                } elseif ($offering->status !== OfferingStatus::Open) {
                    $offering->update([
                        'status' => OfferingStatus::Open,
                        'updated_by' => $userId,
                    ]);
                    $changed = true;
                }
            } elseif ($offering !== null && $offering->status !== OfferingStatus::Closed) {
                $offering->update([
                    'status' => OfferingStatus::Closed,
                    'updated_by' => $userId,
                ]);
                $changed = true;
            }

            if ($changed) {
                activity('session-offerings')
                    ->performedOn($session)
                    ->withProperties([
                        'course_stage_id' => $courseStageId,
                        'open' => $open,
                    ])
                    ->log($open ? 'Opened session stage offering' : 'Closed session stage offering');
            }

            return [
                'course_stage_id' => $courseStageId,
                'is_open' => $open,
                'changed' => $changed,
            ];
        });
    }

    /**
     * Resolve the stage a registration targets: an explicitly requested
     * stage, the next stage after the student's last registration, or
     * module 1 for a new intake.
     */
    public function resolveTargetStage(
        Course $course,
        ?CourseEnrolment $current,
        int $academicSessionId,
        ?int $requestedStageId = null,
    ): CourseStage {
        $stages = $course->stages()->orderBy('module_no')->get();

        if ($stages->isEmpty()) {
            throw ValidationException::withMessages([
                'enrolment.course_stage_id' => 'This course has no stages configured.',
            ]);
        }

        if ($requestedStageId !== null) {
            $stage = $stages->firstWhere('id', $requestedStageId);

            if (! $stage) {
                throw ValidationException::withMessages([
                    'enrolment.course_stage_id' => 'The selected stage does not belong to this course.',
                ]);
            }

            return $stage;
        }

        if (! $current || $current->course_id !== $course->id) {
            return $stages->first();
        }

        $offset = $this->sessionOffset($current->academic_session_id, $academicSessionId);

        if ($offset <= 0) {
            return $current->courseStage ?? $stages->first();
        }

        $targetModule = $current->courseStage
            ? $current->courseStage->module_no + $offset
            : $offset + 1;

        $stage = $stages->firstWhere('module_no', $targetModule);

        if (! $stage) {
            throw ValidationException::withMessages([
                'enrolment.course_stage_id' => 'The student has completed the final stage of this course.',
            ]);
        }

        return $stage;
    }

    /**
     * The 1-based position of a session inside its academic year.
     */
    public function sessionSlot(AcademicSession $session): int
    {
        $ids = AcademicSession::query()
            ->where('academic_year_id', $session->academic_year_id)
            ->orderByRaw('start_date is null')
            ->orderBy('start_date')
            ->orderBy('id')
            ->pluck('id')
            ->values();

        $position = $ids->search($session->id);

        return $position === false ? 1 : $position + 1;
    }

    /**
     * How many sessions sit between two sessions (0 when unknown, equal
     * or reversed).
     *
     * @param  Collection<int, int>|null  $sessionOrder  the pre-computed global session order
     */
    public function sessionOffset(
        ?int $fromSessionId,
        int $toSessionId,
        ?Collection $sessionOrder = null,
    ): int {
        if ($fromSessionId === null) {
            return 0;
        }

        $ids = $sessionOrder ?? $this->sessionOrder();
        $from = $ids->search($fromSessionId);
        $to = $ids->search($toSessionId);

        if ($from === false || $to === false || $to <= $from) {
            return 0;
        }

        return $to - $from;
    }

    /**
     * Every session id in calendar order, computed once per suggestion run.
     *
     * @return Collection<int, int>
     */
    private function sessionOrder(): Collection
    {
        return AcademicSession::query()
            ->orderByRaw('start_date is null')
            ->orderBy('start_date')
            ->orderBy('id')
            ->pluck('id')
            ->values();
    }

    /**
     * Decide whether an offering admits a registration at a point in time.
     *
     * @param  array{event: TermEvent, opens_at: Carbon, closes_at: Carbon}|null  $window
     */
    private function evaluate(
        ?SessionStageOffering $offering,
        ?array $window,
        CarbonInterface $at,
    ): StageOpenResult {
        if (! $offering) {
            return StageOpenResult::denied(StageOpenReason::NoOffering);
        }

        if ($offering->status !== OfferingStatus::Open) {
            return StageOpenResult::denied(StageOpenReason::Closed, $offering);
        }

        if ($window === null) {
            return StageOpenResult::denied(StageOpenReason::NoRegistrationWindow, $offering);
        }

        if ($at->lt($window['opens_at'])) {
            return StageOpenResult::denied(StageOpenReason::NotYetOpen, $offering);
        }

        if ($at->gt($window['closes_at'])) {
            return StageOpenResult::denied(StageOpenReason::WindowEnded, $offering);
        }

        return StageOpenResult::allowed($offering);
    }

    /**
     * Suggest the next stage for every active cohort.
     *
     * A cohort is the set of enrolled students of one course. Without a
     * separate cohort table, the latest active enrolment of each student
     * carries their position; students with no recorded stage are placed
     * by how many sessions have elapsed since they enrolled.
     *
     * @param  Collection<int, Collection<int, CourseStage>>  $stagesByCourse
     * @param  array<int, array{course_stage_id: int, course_id: int, code: string, module_no: int, source: string}>  $suggestions
     * @param  Collection<int, int>  $sessionOrder
     */
    private function suggestFromCohorts(
        AcademicSession $session,
        Collection $stagesByCourse,
        array &$suggestions,
        Collection $sessionOrder,
    ): void {
        $latestEnrolments = CourseEnrolment::query()
            ->with(['courseStage', 'academicSession'])
            ->where('status', EnrolmentStatus::Enrolled)
            ->whereNotNull('course_id')
            ->get()
            ->groupBy(fn (CourseEnrolment $enrolment): string => $enrolment->student_id.'|'.$enrolment->course_id)
            ->map(fn (Collection $rows): CourseEnrolment => $rows->sortByDesc('id')->first());

        foreach ($latestEnrolments as $enrolment) {
            $stages = $stagesByCourse->get($enrolment->course_id);

            if (! $stages) {
                continue;
            }

            $offset = $this->sessionOffset(
                $enrolment->academic_session_id,
                $session->id,
                $sessionOrder,
            );

            $targetModule = $enrolment->courseStage
                ? $enrolment->courseStage->module_no + $offset
                : $offset + 1;

            $stage = $stages->firstWhere('module_no', $targetModule);

            if (! $stage) {
                continue;
            }

            $this->suggest($suggestions, $stage, 'cohort');
        }
    }

    /**
     * Copy the stages that were open in the same session slot last year.
     */
    private function suggestFromLastYear(AcademicSession $session, array &$suggestions): void
    {
        $year = AcademicYear::query()->find($session->academic_year_id);

        if (! $year) {
            return;
        }

        $previousYear = AcademicYear::query()
            ->where('start_date', '<', $year->start_date)
            ->orderByDesc('start_date')
            ->first();

        if (! $previousYear) {
            return;
        }

        $slot = $this->sessionSlot($session);

        $previousSession = AcademicSession::query()
            ->where('academic_year_id', $previousYear->id)
            ->orderByRaw('start_date is null')
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->values()
            ->get($slot - 1);

        if (! $previousSession) {
            return;
        }

        $offerings = SessionStageOffering::query()
            ->with('courseStage')
            ->where('academic_session_id', $previousSession->id)
            ->where('status', OfferingStatus::Open)
            ->get();

        foreach ($offerings as $offering) {
            if ($offering->courseStage) {
                $this->suggest($suggestions, $offering->courseStage, 'last_year');
            }
        }
    }

    /**
     * Suggest the first stage of every active course in the first session
     * of an academic year (the new-intake slot).
     *
     * @param  Collection<int, Collection<int, CourseStage>>  $stagesByCourse
     * @param  array<int, array{course_stage_id: int, course_id: int, code: string, module_no: int, source: string}>  $suggestions
     */
    private function suggestFromNewIntake(
        AcademicSession $session,
        Collection $stagesByCourse,
        array &$suggestions,
    ): void {
        if ($this->sessionSlot($session) !== 1) {
            return;
        }

        $activeCourseIds = Course::query()
            ->where('is_active', true)
            ->pluck('id');

        foreach ($activeCourseIds as $courseId) {
            $stage = $stagesByCourse->get($courseId)?->firstWhere('module_no', 1);

            if ($stage) {
                $this->suggest($suggestions, $stage, 'new_intake');
            }
        }
    }

    /**
     * Record a suggestion unless a higher-priority source already did.
     *
     * @param  array<int, array{course_stage_id: int, course_id: int, code: string, module_no: int, source: string}>  $suggestions
     */
    private function suggest(array &$suggestions, CourseStage $stage, string $source): void
    {
        $suggestions[$stage->id] ??= [
            'course_stage_id' => $stage->id,
            'course_id' => $stage->course_id,
            'code' => $stage->code,
            'module_no' => $stage->module_no,
            'source' => $source,
        ];
    }
}
