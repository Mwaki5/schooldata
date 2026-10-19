<?php

use App\Enums\Role;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\CertificationLevel;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\Curriculum;
use App\Models\StudentProfile;
use App\Models\Unit;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function studentDashboardAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('the student dashboard requires an access token', function () {
    $this->getJson('/api/me/dashboard')->assertUnauthorized();
});

test('the student dashboard refuses admin tokens', function () {
    $token = studentDashboardAccessTokenFor(User::factory()->create(['role' => Role::Admin]));

    $this->withToken($token)->getJson('/api/me/dashboard')->assertForbidden();
});

test('the student dashboard refuses trainer tokens', function () {
    $token = studentDashboardAccessTokenFor(User::factory()->create(['role' => Role::Trainer]));

    $this->withToken($token)->getJson('/api/me/dashboard')->assertForbidden();
});

test('the dashboard returns the student profile, academic calendar and own enrolments', function () {
    AcademicYear::factory()->create([
        'name' => '2025/2026',
        'is_active' => false,
        'start_date' => '2025-09-01',
    ]);
    $year = AcademicYear::factory()->create([
        'name' => '2026/2027',
        'is_active' => true,
        'start_date' => '2026-09-01',
    ]);
    $session = AcademicSession::factory()->create([
        'academic_year_id' => $year->id,
        'name' => 'Term 1, 2026/2027',
        'is_active' => true,
    ]);
    AcademicSession::factory()->create([
        'academic_year_id' => $year->id,
        'name' => 'Term 2, 2026/2027',
        'is_active' => false,
    ]);

    $course = Course::factory()->create(['code' => 'DIT', 'name' => 'Diploma in ICT']);
    $otherCourse = Course::factory()->create(['code' => 'CIT', 'name' => 'Certificate in ICT']);
    $curriculum = Curriculum::factory()->create(['name' => 'TVET Diploma Curriculum']);
    $level = CertificationLevel::factory()->create(['name' => 'Diploma']);
    Unit::factory()->count(2)->create(['course_id' => $course->id]);

    $user = User::factory()->create(['role' => Role::Student, 'login_id' => 'ADM-2026-0001']);
    $student = StudentProfile::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Hassan',
        'status' => 'active',
    ]);

    $shared = [
        'student_id' => $student->id,
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
        'academic_session_id' => $session->id,
        'certification_level_id' => $level->id,
    ];

    CourseEnrolment::factory()->create([
        ...$shared,
        'enrolment_date' => '2026-04-27',
        'status' => 'COMPLETED',
    ]);
    CourseEnrolment::factory()->create([
        ...$shared,
        'enrolment_date' => '2026-09-01',
        'status' => 'ENROLLED',
    ]);

    // Another student's enrolment must never leak into this dashboard.
    $otherStudent = StudentProfile::factory()->create();
    CourseEnrolment::factory()->create([
        'student_id' => $otherStudent->id,
        'course_id' => $otherCourse->id,
        'curriculum_id' => $curriculum->id,
        'academic_session_id' => $session->id,
        'certification_level_id' => $level->id,
    ]);

    $this->withToken(studentDashboardAccessTokenFor($user))
        ->getJson('/api/me/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'profile' => [
                'id', 'user_id', 'login_id', 'email', 'name',
                'first_name', 'last_name', 'gender', 'status',
                'phone', 'date_of_birth', 'county', 'sponsored', 'index_no',
            ],
            'academic' => [
                'year' => ['id', 'name', 'start_date', 'end_date', 'is_active'],
                'session' => ['id', 'name', 'start_date', 'end_date', 'is_active'],
            ],
            'stats' => ['enrolments', 'active', 'completed', 'courses', 'units'],
            'enrolments' => [[
                'id', 'course_code', 'course_name', 'curriculum_name',
                'academic_session_name', 'certification_level_name',
                'enrolment_date', 'status',
            ]],
        ])
        ->assertJsonPath('profile.login_id', 'ADM-2026-0001')
        ->assertJsonPath('profile.name', 'Amina Hassan')
        ->assertJsonPath('profile.status', 'active')
        ->assertJsonPath('academic.year.name', '2026/2027')
        ->assertJsonPath('academic.session.name', 'Term 1, 2026/2027')
        ->assertJsonPath('academic.year.is_active', true)
        ->assertJsonCount(2, 'enrolments')
        ->assertJsonPath('stats.enrolments', 2)
        ->assertJsonPath('stats.active', 1)
        ->assertJsonPath('stats.completed', 1)
        ->assertJsonPath('stats.courses', 1)
        ->assertJsonPath('stats.units', 2)
        ->assertJsonPath('enrolments.0.status', 'ENROLLED')
        ->assertJsonPath('enrolments.0.course_code', 'DIT')
        ->assertJsonPath('enrolments.1.status', 'COMPLETED');
});

test('a student without a profile row gets an empty dashboard', function () {
    $user = User::factory()->create(['role' => Role::Student, 'login_id' => 'ADM-2026-9999']);

    $this->withToken(studentDashboardAccessTokenFor($user))
        ->getJson('/api/me/dashboard')
        ->assertOk()
        ->assertJsonPath('profile', null)
        ->assertJsonPath('stats.enrolments', 0)
        ->assertJsonPath('stats.active', 0)
        ->assertJsonPath('stats.courses', 0)
        ->assertJsonPath('stats.units', 0)
        ->assertJsonCount(0, 'enrolments');
});
