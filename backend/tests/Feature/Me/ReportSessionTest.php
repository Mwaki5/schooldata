<?php

use App\Enums\EnrolmentStatus;
use App\Enums\OfferingStatus;
use App\Enums\TermEventName;
use App\Models\AcademicSession;
use App\Models\AcademicSessionRegistration;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\CourseStage;
use App\Models\SessionStageOffering;
use App\Models\StudentProfile;
use App\Models\TermEvent;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

use function Pest\Laravel\travelTo;

function reportSessionAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

/**
 * The running academic session of the demo calendar.
 */
function reportSessionRunningSession(): AcademicSession
{
    $year = AcademicYear::factory()->create([
        'name' => '2026/2027',
        'is_active' => true,
        'start_date' => '2026-09-01',
        'end_date' => '2027-08-31',
    ]);

    return AcademicSession::factory()->create([
        'academic_year_id' => $year->id,
        'name' => 'Term 1, 2026/2027',
        'is_active' => true,
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-18',
    ]);
}

function reportSessionStage(Course $course): CourseStage
{
    return $course->stages()->create([
        'module_no' => 1,
        'year_no' => 1,
        'session_no' => 1,
        'code' => 'Y1S1',
    ]);
}

/**
 * A signed-in student whose latest enrolment sits on the given stage.
 *
 * @return array{user: User, token: string, profile: StudentProfile}
 */
function reportSessionStudent(AcademicSession $session, Course $course, CourseStage $stage): array
{
    $user = User::factory()->student()->create();
    $profile = StudentProfile::factory()->create(['user_id' => $user->id]);

    CourseEnrolment::factory()->create([
        'student_id' => $profile->id,
        'course_id' => $course->id,
        'course_stage_id' => $stage->id,
        'academic_session_id' => $session->id,
        'status' => EnrolmentStatus::Enrolled,
    ]);

    return [
        'user' => $user,
        'token' => reportSessionAccessTokenFor($user),
        'profile' => $profile,
    ];
}

function reportSessionWindow(AcademicSession $session, string $start = '2026-09-01', string $end = '2026-09-10', bool $active = true): TermEvent
{
    return TermEvent::factory()->create([
        'academic_session_id' => $session->id,
        'name' => TermEventName::SessionRegistration,
        'start_date' => $start,
        'end_date' => $end,
        'is_active' => $active,
    ]);
}

function reportSessionOffering(AcademicSession $session, CourseStage $stage): SessionStageOffering
{
    return SessionStageOffering::factory()
        ->for($session, 'academicSession')
        ->for($stage, 'courseStage')
        ->create();
}

test('reporting for a session requires an access token', function () {
    $this->postJson('/api/me/report-session')->assertUnauthorized();
});

test('reporting for a session refuses non-student tokens', function (string $role) {
    $token = reportSessionAccessTokenFor(User::factory()->{$role}()->create());

    $this->withToken($token)->postJson('/api/me/report-session')->assertForbidden();
})->with([
    'admin' => 'admin',
    'trainer' => 'trainer',
    'finance' => 'finance',
]);

test('reporting fails when there is no active academic session', function () {
    $user = User::factory()->student()->create();
    StudentProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken(reportSessionAccessTokenFor($user))
        ->postJson('/api/me/report-session')
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'session' => 'There is no active academic session to report for.',
        ]);
});

test('reporting fails when the session has no registration window', function () {
    $session = reportSessionRunningSession();
    $course = Course::factory()->create(['is_active' => true]);
    $stage = reportSessionStage($course);
    ['token' => $token] = reportSessionStudent($session, $course, $stage);
    reportSessionOffering($session, $stage);

    $this->withToken($token)
        ->postJson('/api/me/report-session')
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'session' => 'Session registration is not open for this term.',
        ]);
});

test('reporting fails when the registration event is inactive', function () {
    $session = reportSessionRunningSession();
    $course = Course::factory()->create(['is_active' => true]);
    $stage = reportSessionStage($course);
    travelTo('2026-09-05 12:00:00');

    ['token' => $token] = reportSessionStudent($session, $course, $stage);
    reportSessionWindow($session, active: false);
    reportSessionOffering($session, $stage);

    $this->withToken($token)
        ->postJson('/api/me/report-session')
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'session' => 'Session registration is not open for this term.',
        ]);
});

test('reporting fails when the student is not on a course stage', function () {
    $session = reportSessionRunningSession();
    $course = Course::factory()->create(['is_active' => true]);
    $user = User::factory()->student()->create();
    $profile = StudentProfile::factory()->create(['user_id' => $user->id]);

    CourseEnrolment::factory()->create([
        'student_id' => $profile->id,
        'course_id' => $course->id,
        'academic_session_id' => $session->id,
        'status' => EnrolmentStatus::Enrolled,
    ]);

    $this->withToken(reportSessionAccessTokenFor($user))
        ->postJson('/api/me/report-session')
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'session' => 'You are not enrolled on a course stage yet.',
        ]);
});

test('reporting fails while the stage is not offered or is closed', function (bool $createRow) {
    $session = reportSessionRunningSession();
    $course = Course::factory()->create(['is_active' => true]);
    $stage = reportSessionStage($course);
    ['token' => $token] = reportSessionStudent($session, $course, $stage);
    reportSessionWindow($session);

    if ($createRow) {
        reportSessionOffering($session, $stage)->update(['status' => OfferingStatus::Closed]);
    }

    $this->withToken($token)
        ->postJson('/api/me/report-session')
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'session' => 'Y1S1 is not open for this session.',
        ]);
})->with([
    'no offering row' => false,
    'closed offering row' => true,
]);

test('reporting fails outside the registration window', function (string $start, string $end, string $message) {
    $session = reportSessionRunningSession();
    $course = Course::factory()->create(['is_active' => true]);
    $stage = reportSessionStage($course);
    travelTo('2026-09-05 12:00:00');

    ['token' => $token] = reportSessionStudent($session, $course, $stage);
    reportSessionWindow($session, $start, $end);
    reportSessionOffering($session, $stage);

    $this->withToken($token)
        ->postJson('/api/me/report-session')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['session' => $message]);
})->with([
    'before the window opens' => ['2026-09-20', '2026-09-25', 'Registration for this session has not opened yet.'],
    'after the window closes' => ['2026-08-01', '2026-08-10', 'Registration for this session has closed.'],
]);

test('a student reports for the open stage of the running session', function () {
    $session = reportSessionRunningSession();
    $course = Course::factory()->create(['is_active' => true]);
    $stage = reportSessionStage($course);
    travelTo('2026-09-05 12:00:00');

    ['user' => $user, 'token' => $token, 'profile' => $profile] = reportSessionStudent($session, $course, $stage);
    reportSessionWindow($session);
    reportSessionOffering($session, $stage);

    $this->withToken($token)
        ->postJson('/api/me/report-session')
        ->assertCreated()
        ->assertJsonStructure([
            'registration' => ['id', 'academic_session_id', 'module_no', 'year_no', 'session_no', 'course_stage_code'],
        ])
        ->assertJsonPath('registration.academic_session_id', $session->id)
        ->assertJsonPath('registration.module_no', 1)
        ->assertJsonPath('registration.year_no', 1)
        ->assertJsonPath('registration.session_no', 1)
        ->assertJsonPath('registration.course_stage_code', 'Y1S1');

    $this->assertDatabaseHas('academic_session_registrations', [
        'academic_session_id' => $session->id,
        'student_id' => $profile->id,
        'course_stage_id' => $stage->id,
        'module_no' => 1,
        'year_no' => 1,
        'session_no' => 1,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);
});

test('reporting twice keeps a single registration', function () {
    $session = reportSessionRunningSession();
    $course = Course::factory()->create(['is_active' => true]);
    $stage = reportSessionStage($course);
    travelTo('2026-09-05 12:00:00');

    ['token' => $token, 'profile' => $profile] = reportSessionStudent($session, $course, $stage);
    reportSessionWindow($session);
    reportSessionOffering($session, $stage);

    $first = $this->withToken($token)->postJson('/api/me/report-session')->assertCreated();

    $this->withToken($token)
        ->postJson('/api/me/report-session')
        ->assertCreated()
        ->assertJsonPath('registration.id', $first->json('registration.id'));

    expect(AcademicSessionRegistration::query()->where('student_id', $profile->id)->count())->toBe(1);
});

test('the dashboard offers reporting until the student has reported', function () {
    $session = reportSessionRunningSession();
    $course = Course::factory()->create(['is_active' => true]);
    $stage = reportSessionStage($course);
    travelTo('2026-09-05 12:00:00');

    ['token' => $token] = reportSessionStudent($session, $course, $stage);
    reportSessionWindow($session);
    reportSessionOffering($session, $stage);

    $this->withToken($token)
        ->getJson('/api/me/dashboard')
        ->assertOk()
        ->assertJsonPath('report_session.can_report', true)
        ->assertJsonPath('report_session.module_no', 1)
        ->assertJsonPath('report_session.year_no', 1)
        ->assertJsonPath('report_session.session_no', 1)
        ->assertJsonPath('report_session.stage_code', 'Y1S1');

    $this->withToken($token)->postJson('/api/me/report-session')->assertCreated();

    $this->withToken($token)
        ->getJson('/api/me/dashboard')
        ->assertOk()
        ->assertJsonPath('report_session.can_report', false)
        ->assertJsonPath('report_session.reason', 'You have already reported for this session.')
        ->assertJsonPath('report_session.year_no', 1)
        ->assertJsonPath('report_session.session_no', 1);
});

test('the dashboard explains why reporting is unavailable', function () {
    $session = reportSessionRunningSession();
    $course = Course::factory()->create(['is_active' => true]);
    $stage = reportSessionStage($course);
    travelTo('2026-09-05 12:00:00');

    ['token' => $token] = reportSessionStudent($session, $course, $stage);
    reportSessionWindow($session, active: false);
    reportSessionOffering($session, $stage);

    $this->withToken($token)
        ->getJson('/api/me/dashboard')
        ->assertOk()
        ->assertJsonPath('report_session.can_report', false)
        ->assertJsonPath('report_session.reason', 'Session registration is not open for this term.')
        ->assertJsonPath('report_session.year_no', 1)
        ->assertJsonPath('report_session.session_no', 1);
});
