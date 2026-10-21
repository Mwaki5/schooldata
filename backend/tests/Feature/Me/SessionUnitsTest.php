<?php

use App\Enums\EnrolmentStatus;
use App\Enums\Role;
use App\Models\AcademicSession;
use App\Models\AcademicSessionRegistration;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\CourseUnit;
use App\Models\Curriculum;
use App\Models\StudentProfile;
use App\Models\Unit;
use App\Models\UnitRegistration;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function sessionUnitsAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

/**
 * Everything the offered-units flow needs: an active session, an enrolled
 * student who has reported for it at module 1, and their course.
 *
 * @return array{user: User, token: string, profile: StudentProfile, session: AcademicSession, course: Course, curriculum: Curriculum}
 */
function sessionUnitsReportedStudent(): array
{
    $year = AcademicYear::factory()->create([
        'name' => '2026/2027',
        'is_active' => true,
        'start_date' => '2026-09-01',
        'end_date' => '2027-08-31',
    ]);

    $session = AcademicSession::factory()->create([
        'academic_year_id' => $year->id,
        'name' => 'Term 1, 2026/2027',
        'is_active' => true,
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-18',
    ]);

    $course = Course::factory()->create(['is_active' => true]);
    $curriculum = Curriculum::factory()->create();

    $user = User::factory()->create(['role' => Role::Student]);
    $profile = StudentProfile::factory()->create(['user_id' => $user->id]);

    CourseEnrolment::factory()->create([
        'student_id' => $profile->id,
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
        'academic_session_id' => $session->id,
        'status' => EnrolmentStatus::Enrolled,
    ]);

    AcademicSessionRegistration::factory()->create([
        'academic_session_id' => $session->id,
        'student_id' => $profile->id,
        'course_stage_id' => null,
        'module_no' => 1,
        'year_no' => 1,
        'session_no' => 1,
    ]);

    return [
        'user' => $user,
        'token' => sessionUnitsAccessTokenFor($user),
        'profile' => $profile,
        'session' => $session,
        'course' => $course,
        'curriculum' => $curriculum,
    ];
}

/**
 * An offered unit: the catalogue entry plus its active course-unit link
 * at the given module. Unit fields (code, name, description) go on the
 * unit; delivery fields go on the link. The link is what comes back,
 * because that is the row a student actually registers.
 */
function sessionUnitsOfferedUnit(Course $course, Curriculum $curriculum, array $overrides = []): CourseUnit
{
    $unitFields = array_intersect_key($overrides, array_flip(['code', 'name', 'description']));
    $linkFields = array_intersect_key($overrides, array_flip(['modules_taught', 'taught_hours', 'credit_factor', 'unit_type', 'is_active']));

    $unit = Unit::factory()->create($unitFields);

    return CourseUnit::factory()->create(array_merge([
        'unit_id' => $unit->id,
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
        'modules_taught' => 1,
        'unit_type' => 'core',
        'is_active' => true,
    ], $linkFields));
}

test('the session units endpoints require an access token', function () {
    $this->getJson('/api/me/session-units')->assertUnauthorized();
    $this->postJson('/api/me/session-units', ['course_units' => [1]])->assertUnauthorized();
});

test('the session units endpoints refuse non-student tokens', function (Role $role) {
    $token = sessionUnitsAccessTokenFor(User::factory()->create(['role' => $role]));

    $this->withToken($token)->getJson('/api/me/session-units')->assertForbidden();
    $this->withToken($token)->postJson('/api/me/session-units', ['course_units' => [1]])->assertForbidden();
})->with([
    'admin' => Role::Admin,
    'trainer' => Role::Trainer,
    'finance' => Role::Finance,
]);

test('nothing is offered before the student reports for the session', function () {
    $setup = sessionUnitsReportedStudent();
    AcademicSessionRegistration::query()->delete();

    $this->withToken($setup['token'])
        ->getJson('/api/me/session-units')
        ->assertOk()
        ->assertJsonPath('reported', false)
        ->assertJsonPath('session.id', $setup['session']->id)
        ->assertJsonPath('units', []);
});

test('only active units of the reported module are offered', function () {
    $setup = sessionUnitsReportedStudent();

    $offered = sessionUnitsOfferedUnit($setup['course'], $setup['curriculum'], ['code' => 'U100', 'name' => 'Basics']);
    sessionUnitsOfferedUnit($setup['course'], $setup['curriculum'], ['modules_taught' => 2]);
    sessionUnitsOfferedUnit($setup['course'], $setup['curriculum'], ['is_active' => false]);
    sessionUnitsOfferedUnit(Course::factory()->create(['is_active' => true]), $setup['curriculum']);

    $this->withToken($setup['token'])
        ->getJson('/api/me/session-units')
        ->assertOk()
        ->assertJsonPath('reported', true)
        ->assertJsonPath('module_no', 1)
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('units.0.course_unit_id', $offered->id)
        ->assertJsonPath('units.0.code', 'U100')
        ->assertJsonPath('units.0.registered', false);
});

test('units offered for the session can be registered with audit stamps', function () {
    $setup = sessionUnitsReportedStudent();
    $first = sessionUnitsOfferedUnit($setup['course'], $setup['curriculum']);
    $second = sessionUnitsOfferedUnit($setup['course'], $setup['curriculum']);

    $this->withToken($setup['token'])
        ->postJson('/api/me/session-units', ['course_units' => [$first->id, $second->id]])
        ->assertCreated()
        ->assertJsonPath('registered', [$first->id, $second->id]);

    $this->assertDatabaseHas('unit_registrations', [
        'academic_session_id' => $setup['session']->id,
        'course_unit_id' => $first->id,
        'student_id' => $setup['profile']->id,
        'approved_by' => null,
        'created_by' => $setup['user']->id,
        'updated_by' => $setup['user']->id,
    ]);
});

test('registered units show as registered and are never duplicated', function () {
    $setup = sessionUnitsReportedStudent();
    $link = sessionUnitsOfferedUnit($setup['course'], $setup['curriculum']);

    $this->withToken($setup['token'])
        ->postJson('/api/me/session-units', ['course_units' => [$link->id]])
        ->assertCreated();

    $this->withToken($setup['token'])
        ->postJson('/api/me/session-units', ['course_units' => [$link->id]])
        ->assertCreated()
        ->assertJsonPath('registered', []);

    $this->withToken($setup['token'])
        ->getJson('/api/me/session-units')
        ->assertJsonPath('units.0.course_unit_id', $link->id)
        ->assertJsonPath('units.0.registered', true);

    expect(UnitRegistration::query()->where('student_id', $setup['profile']->id)->count())->toBe(1);
});

test('units that are not offered cannot be registered', function () {
    $setup = sessionUnitsReportedStudent();
    $offered = sessionUnitsOfferedUnit($setup['course'], $setup['curriculum']);
    $foreign = sessionUnitsOfferedUnit($setup['course'], $setup['curriculum'], ['modules_taught' => 3]);

    $this->withToken($setup['token'])
        ->postJson('/api/me/session-units', ['course_units' => [$offered->id, $foreign->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'course_units' => 'One or more selected units are not offered for this session.',
        ]);

    expect(UnitRegistration::query()->count())->toBe(0);
});

test('units cannot be registered before reporting for the session', function () {
    $setup = sessionUnitsReportedStudent();
    AcademicSessionRegistration::query()->delete();

    $this->withToken($setup['token'])
        ->postJson('/api/me/session-units', ['course_units' => [1]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'course_units' => 'Report for this session before registering units.',
        ]);
});

test('the units selection is required', function () {
    $setup = sessionUnitsReportedStudent();

    $this->withToken($setup['token'])
        ->postJson('/api/me/session-units', ['course_units' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['course_units']);
});

test('the offered units are empty without an active session', function () {
    $user = User::factory()->create(['role' => Role::Student]);
    StudentProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken(sessionUnitsAccessTokenFor($user))
        ->getJson('/api/me/session-units')
        ->assertOk()
        ->assertJsonPath('session', null)
        ->assertJsonPath('reported', false)
        ->assertJsonPath('units', []);
});
