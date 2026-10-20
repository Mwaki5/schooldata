<?php

use App\Enums\OfferingStatus;
use App\Enums\Role;
use App\Enums\TermEventName;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Department;
use App\Models\TermEvent;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

use function Pest\Laravel\travelTo;

function sessionOfferingsAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('session offering endpoints require an access token', function () {
    $this->getJson('/api/session-offerings')->assertUnauthorized();
    $this->patchJson('/api/session-offerings/stage', [])->assertUnauthorized();
});

test('non-admin roles cannot manage session offerings', function () {
    $token = sessionOfferingsAccessTokenFor(User::factory()->create(['role' => Role::Student]));

    $this->withToken($token)->getJson('/api/session-offerings')->assertForbidden();
    $this->withToken($token)->patchJson('/api/session-offerings/stage', [])->assertForbidden();
});

test('the wizard returns the course stage grid with suggestions and server filters', function () {
    $year = AcademicYear::factory()->create([
        'name' => '2026/2027 Academic Year',
        'start_date' => '2026-09-01',
    ]);
    $session = AcademicSession::factory()->create([
        'academic_year_id' => $year->id,
        'name' => 'Term 1, 2026/2027',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-18',
    ]);
    TermEvent::factory()->create([
        'academic_session_id' => $session->id,
        'name' => TermEventName::SessionRegistration,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-05',
        'is_active' => true,
    ]);
    $department = Department::factory()->create(['name' => 'Information Technology']);
    $course = Course::factory()->create([
        'name' => 'Diploma in Information Technology',
        'department_id' => $department->id,
        'is_active' => true,
        'total_modules' => 6,
    ]);

    $course->stages()->createMany([
        ['module_no' => 1, 'year_no' => 1, 'session_no' => 1, 'code' => 'Y1S1'],
        ['module_no' => 2, 'year_no' => 1, 'session_no' => 2, 'code' => 'Y1S2'],
    ]);

    travelTo('2026-09-03 10:00:00');

    $this->withToken(sessionOfferingsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/session-offerings?academic_session_id='.$session->id)
        ->assertOk()
        ->assertJsonPath('session.name', 'Term 1, 2026/2027')
        ->assertJsonPath('session.slot', 1)
        ->assertJsonPath('registration_window.event_name', 'Session Registration')
        ->assertJsonPath('registration_window.starts_on', '2026-09-01')
        ->assertJsonPath('registration_window.ends_on', '2026-09-05')
        ->assertJsonPath('registration_window.state', 'open')
        ->assertJsonPath('courses.0.name', 'Diploma in Information Technology')
        ->assertJsonPath('courses.0.stages.0.code', 'Y1S1')
        ->assertJsonPath('courses.0.stages.0.suggested', true)
        ->assertJsonPath('courses.0.stages.0.source', 'new_intake')
        ->assertJsonPath('courses.0.stages.0.is_open', false)
        ->assertJsonPath('departments.0.name', 'Information Technology')
        ->assertJsonPath('stage_codes', ['Y1S1', 'Y1S2'])
        ->assertJsonPath('stats.open_stages', 0)
        ->assertJsonPath('stats.suggested_stages', 1)
        ->assertJsonMissingPath('totals');
});

test('the wizard applies search, department and stage filters in the backend query', function () {
    $session = AcademicSession::factory()->create();
    $ict = Department::factory()->create(['name' => 'ICT']);
    $business = Department::factory()->create(['name' => 'Business']);

    $dit = Course::factory()->create([
        'code' => 'DIT',
        'name' => 'Diploma in Information Technology',
        'department_id' => $ict->id,
    ]);
    $dit->stages()->create(['module_no' => 1, 'year_no' => 1, 'session_no' => 1, 'code' => 'Y1S1']);
    $dit->stages()->create(['module_no' => 2, 'year_no' => 1, 'session_no' => 2, 'code' => 'Y1S2']);

    $businessCourse = Course::factory()->create([
        'code' => 'DBM',
        'name' => 'Diploma in Business Management',
        'department_id' => $business->id,
    ]);
    $businessCourse->stages()->create(['module_no' => 1, 'year_no' => 1, 'session_no' => 1, 'code' => 'Y1S1']);

    $unassigned = Course::factory()->create([
        'code' => 'SC',
        'name' => 'Short Course',
        'department_id' => null,
    ]);
    $unassigned->stages()->create(['module_no' => 1, 'year_no' => 1, 'session_no' => 1, 'code' => 'Y1S1']);

    $token = sessionOfferingsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->getJson('/api/session-offerings?academic_session_id='.$session->id.'&search=Information')
        ->assertOk()
        ->assertJsonCount(1, 'courses')
        ->assertJsonPath('courses.0.code', 'DIT');

    $this->withToken($token)
        ->getJson('/api/session-offerings?academic_session_id='.$session->id.'&department_id='.$business->id)
        ->assertOk()
        ->assertJsonCount(1, 'courses')
        ->assertJsonPath('courses.0.code', 'DBM');

    $this->withToken($token)
        ->getJson('/api/session-offerings?academic_session_id='.$session->id.'&department_id=0')
        ->assertOk()
        ->assertJsonCount(1, 'courses')
        ->assertJsonPath('courses.0.code', 'SC');

    $this->withToken($token)
        ->getJson('/api/session-offerings?academic_session_id='.$session->id.'&stage_code=Y1S2')
        ->assertOk()
        ->assertJsonCount(1, 'courses')
        ->assertJsonPath('courses.0.code', 'DIT');
});

test('a single stage can be toggled open and closed immediately', function () {
    $session = AcademicSession::factory()->create();
    $course = Course::factory()->create(['is_active' => true]);
    $stage = $course->stages()->create(['module_no' => 1, 'year_no' => 1, 'session_no' => 1, 'code' => 'Y1S1']);
    $otherStage = $course->stages()->create(['module_no' => 2, 'year_no' => 1, 'session_no' => 2, 'code' => 'Y1S2']);

    $token = sessionOfferingsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->patchJson('/api/session-offerings/stage', [
            'academic_session_id' => $session->id,
            'course_stage_id' => $stage->id,
            'open' => true,
        ])
        ->assertOk()
        ->assertJsonPath('offering.course_stage_id', $stage->id)
        ->assertJsonPath('offering.is_open', true)
        ->assertJsonPath('offering.changed', true);

    $this->assertDatabaseHas('session_stage_offerings', [
        'academic_session_id' => $session->id,
        'course_stage_id' => $stage->id,
        'status' => OfferingStatus::Open->value,
    ]);

    $this->withToken($token)
        ->patchJson('/api/session-offerings/stage', [
            'academic_session_id' => $session->id,
            'course_stage_id' => $stage->id,
            'open' => true,
        ])
        ->assertOk()
        ->assertJsonPath('offering.changed', false);

    $this->withToken($token)
        ->patchJson('/api/session-offerings/stage', [
            'academic_session_id' => $session->id,
            'course_stage_id' => $stage->id,
            'open' => false,
        ])
        ->assertOk()
        ->assertJsonPath('offering.is_open', false)
        ->assertJsonPath('offering.changed', true);

    $this->assertDatabaseHas('session_stage_offerings', [
        'academic_session_id' => $session->id,
        'course_stage_id' => $stage->id,
        'status' => OfferingStatus::Closed->value,
    ]);

    $this->withToken($token)
        ->patchJson('/api/session-offerings/stage', [
            'academic_session_id' => $session->id,
            'course_stage_id' => $otherStage->id,
            'open' => false,
        ])
        ->assertOk()
        ->assertJsonPath('offering.is_open', false)
        ->assertJsonPath('offering.changed', false);

    $this->assertDatabaseMissing('session_stage_offerings', [
        'academic_session_id' => $session->id,
        'course_stage_id' => $otherStage->id,
    ]);
});
