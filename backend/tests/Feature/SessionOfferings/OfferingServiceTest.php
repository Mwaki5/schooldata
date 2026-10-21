<?php

use App\Enums\EnrolmentStatus;
use App\Enums\StageOpenReason;
use App\Enums\TermEventName;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\CourseStage;
use App\Models\SessionStageOffering;
use App\Models\StudentProfile;
use App\Models\TermEvent;
use App\Services\Offerings\OfferingService;
use Illuminate\Support\Carbon;

use function Pest\Laravel\travelTo;

function offeringStageFor(Course $course, int $moduleNo, string $code): CourseStage
{
    return $course->stages()->create([
        'module_no' => $moduleNo,
        'year_no' => intdiv($moduleNo - 1, 3) + 1,
        'session_no' => (($moduleNo - 1) % 3) + 1,
        'code' => $code,
    ]);
}

function offeringRegistrationEvent(
    AcademicSession $session,
    string $start = '2026-01-10',
    string $end = '2026-01-20',
): TermEvent {
    return TermEvent::factory()->create([
        'academic_session_id' => $session->id,
        'name' => TermEventName::SessionRegistration,
        'start_date' => $start,
        'end_date' => $end,
        'is_active' => true,
    ]);
}

/**
 * A two-year calendar: three sessions each in 2025/2026 and 2026/2027.
 *
 * @return array{0: AcademicYear, 1: array<string, AcademicSession>}
 */
function offeringCalendar(): array
{
    $previousYear = AcademicYear::factory()->create([
        'name' => '2025/2026 Academic Year',
        'start_date' => '2025-09-01',
        'end_date' => '2026-08-31',
    ]);
    $currentYear = AcademicYear::factory()->create([
        'name' => '2026/2027 Academic Year',
        'start_date' => '2026-09-01',
        'end_date' => '2027-08-31',
    ]);

    $sessions = [];

    foreach ([
        'prev_t1' => [$previousYear, 'Term 1, 2025/2026', '2025-09-01', '2025-12-05'],
        'prev_t2' => [$previousYear, 'Term 2, 2025/2026', '2026-01-05', '2026-04-10'],
        'prev_t3' => [$previousYear, 'Term 3, 2025/2026', '2026-04-27', '2026-08-28'],
        't1' => [$currentYear, 'Term 1, 2026/2027', '2026-09-01', '2026-12-18'],
        't2' => [$currentYear, 'Term 2, 2026/2027', '2027-01-04', '2027-04-09'],
        't3' => [$currentYear, 'Term 3, 2026/2027', '2027-04-26', '2027-08-31'],
    ] as $key => [$year, $name, $start, $end]) {
        $sessions[$key] = AcademicSession::factory()->create([
            'academic_year_id' => $year->id,
            'name' => $name,
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    return [$currentYear, $sessions];
}

test('a stage with no offering row is closed', function () {
    $session = AcademicSession::factory()->create();
    $stage = offeringStageFor(Course::factory()->create(), 1, 'Y1S1');

    $result = app(OfferingService::class)->isStageOpen($session->id, $stage->id);

    expect($result->open)->toBeFalse();
    expect($result->reason)->toBe(StageOpenReason::NoOffering);
});

test('an offering row that is closed is closed', function () {
    $session = AcademicSession::factory()->create();
    $stage = offeringStageFor(Course::factory()->create(), 1, 'Y1S1');

    SessionStageOffering::factory()
        ->for($session, 'academicSession')
        ->for($stage, 'courseStage')
        ->closed()
        ->create();

    $result = app(OfferingService::class)->isStageOpen($session->id, $stage->id);

    expect($result->open)->toBeFalse();
    expect($result->reason)->toBe(StageOpenReason::Closed);
});

test('an open stage is blocked when the session has no registration event', function () {
    $session = AcademicSession::factory()->create();
    $stage = offeringStageFor(Course::factory()->create(), 1, 'Y1S1');

    SessionStageOffering::factory()
        ->for($session, 'academicSession')
        ->for($stage, 'courseStage')
        ->create();

    $result = app(OfferingService::class)->isStageOpen($session->id, $stage->id);

    expect($result->open)->toBeFalse();
    expect($result->reason)->toBe(StageOpenReason::NoRegistrationWindow);
});

test('an open stage is blocked when the registration event is inactive', function () {
    $session = AcademicSession::factory()->create();
    $stage = offeringStageFor(Course::factory()->create(), 1, 'Y1S1');

    SessionStageOffering::factory()
        ->for($session, 'academicSession')
        ->for($stage, 'courseStage')
        ->create();

    TermEvent::factory()->create([
        'academic_session_id' => $session->id,
        'name' => TermEventName::SessionRegistration,
        'start_date' => '2026-01-10',
        'end_date' => '2026-01-20',
        'is_active' => false,
    ]);

    travelTo('2026-01-15 12:00:00');

    $result = app(OfferingService::class)->isStageOpen($session->id, $stage->id);

    expect($result->open)->toBeFalse();
    expect($result->reason)->toBe(StageOpenReason::NoRegistrationWindow);
});

test('the registration event date boundaries are inclusive whole days', function (string $at, bool $open, StageOpenReason $reason) {
    $session = AcademicSession::factory()->create();
    $stage = offeringStageFor(Course::factory()->create(), 1, 'Y1S1');
    offeringRegistrationEvent($session);

    SessionStageOffering::factory()
        ->for($session, 'academicSession')
        ->for($stage, 'courseStage')
        ->create();

    travelTo($at);

    $result = app(OfferingService::class)->isStageOpen($session->id, $stage->id);

    expect($result->open)->toBe($open);
    expect($result->reason)->toBe($reason);
})->with([
    'one second before opening' => ['2026-01-09 23:59:59', false, StageOpenReason::NotYetOpen],
    'exactly at opening' => ['2026-01-10 00:00:00', true, StageOpenReason::Open],
    'exactly at closing' => ['2026-01-20 23:59:59', true, StageOpenReason::Open],
    'one second after closing' => ['2026-01-21 00:00:00', false, StageOpenReason::WindowEnded],
]);

test('the registration state follows the term event dates', function () {
    $session = AcademicSession::factory()->create();
    $service = app(OfferingService::class);
    $window = $service->registrationWindow($session->id);

    expect($service->registrationState($window, Carbon::parse('2026-01-15 12:00:00')))
        ->toBe('not_configured');

    offeringRegistrationEvent($session);
    $window = $service->registrationWindow($session->id);

    expect($service->registrationState($window, Carbon::parse('2026-01-09 23:59:59')))->toBe('upcoming')
        ->and($service->registrationState($window, Carbon::parse('2026-01-10 00:00:00')))->toBe('open')
        ->and($service->registrationState($window, Carbon::parse('2026-01-20 23:59:59')))->toBe('open')
        ->and($service->registrationState($window, Carbon::parse('2026-01-21 00:00:00')))->toBe('ended');
});

test('active cohorts suggest the next stage in the target session', function () {
    [, $sessions] = offeringCalendar();
    $course = Course::factory()->create(['is_active' => true]);
    offeringStageFor($course, 1, 'Y1S1');
    offeringStageFor($course, 2, 'Y1S2');

    CourseEnrolment::factory()->create([
        'student_id' => StudentProfile::factory(),
        'course_id' => $course->id,
        'course_stage_id' => $course->stages()->where('module_no', 1)->value('id'),
        'academic_session_id' => $sessions['t1']->id,
        'status' => EnrolmentStatus::Enrolled,
    ]);

    $suggestions = app(OfferingService::class)->suggestOfferings($sessions['t2']->id);
    $stageTwo = $course->stages()->where('module_no', 2)->firstOrFail();

    expect($suggestions->firstWhere('course_stage_id', $stageTwo->id)['source'] ?? null)->toBe('cohort');
});

test('the same session slot last year is copied forward', function () {
    [, $sessions] = offeringCalendar();
    $course = Course::factory()->create(['is_active' => true]);
    $stage = offeringStageFor($course, 3, 'Y1S3');

    SessionStageOffering::factory()
        ->for($sessions['prev_t3'], 'academicSession')
        ->for($stage, 'courseStage')
        ->create();

    $suggestions = app(OfferingService::class)->suggestOfferings($sessions['t3']->id);

    expect($suggestions->firstWhere('course_stage_id', $stage->id)['source'] ?? null)->toBe('last_year');
});

test('the first session of a year suggests a new intake for active courses', function () {
    [, $sessions] = offeringCalendar();
    $course = Course::factory()->create(['is_active' => true]);
    $stage = offeringStageFor($course, 1, 'Y1S1');

    $suggestions = app(OfferingService::class)->suggestOfferings($sessions['t1']->id);

    expect($suggestions->firstWhere('course_stage_id', $stage->id)['source'] ?? null)->toBe('new_intake');
});

test('suggestion priority is cohort, then last year, then new intake', function () {
    [, $sessions] = offeringCalendar();

    $cohortCourse = Course::factory()->create(['is_active' => true]);
    $cohortStageOne = offeringStageFor($cohortCourse, 1, 'Y1S1');
    offeringStageFor($cohortCourse, 2, 'Y1S2');

    CourseEnrolment::factory()->create([
        'student_id' => StudentProfile::factory(),
        'course_id' => $cohortCourse->id,
        'course_stage_id' => $cohortStageOne->id,
        'academic_session_id' => $sessions['t1']->id,
        'status' => EnrolmentStatus::Enrolled,
    ]);

    $lastYearCourse = Course::factory()->create(['is_active' => true]);
    $lastYearStage = offeringStageFor($lastYearCourse, 2, 'Y1S2');

    SessionStageOffering::factory()
        ->for($sessions['prev_t1'], 'academicSession')
        ->for($lastYearStage, 'courseStage')
        ->create();

    $newIntakeCourse = Course::factory()->create(['is_active' => true]);
    $newIntakeStage = offeringStageFor($newIntakeCourse, 1, 'Y1S1');

    $suggestions = app(OfferingService::class)->suggestOfferings($sessions['t1']->id)->keyBy('course_stage_id');

    expect($suggestions[$cohortStageOne->id]['source'])->toBe('cohort');
    expect($suggestions[$lastYearStage->id]['source'])->toBe('last_year');
    expect($suggestions[$newIntakeStage->id]['source'])->toBe('new_intake');
});
