<?php

use App\Enums\Role;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\Curriculum;
use App\Models\StudentProfile;
use App\Models\Unit;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function myUnitsAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('my units requires an access token', function () {
    $this->getJson('/api/me/units')->assertUnauthorized();
});

test('my units refuses non-student tokens', function () {
    $this->withToken(myUnitsAccessTokenFor(User::factory()->create(['role' => Role::Admin])))
        ->getJson('/api/me/units')
        ->assertForbidden();

    $this->withToken(myUnitsAccessTokenFor(User::factory()->create(['role' => Role::Trainer])))
        ->getJson('/api/me/units')
        ->assertForbidden();
});

test('my units groups the units of every enrolment by course and curriculum', function () {
    $diploma = Course::factory()->create(['code' => 'DIT', 'name' => 'Diploma in ICT']);
    $certificate = Course::factory()->create(['code' => 'CIT', 'name' => 'Certificate in ICT']);
    $diplomaCurriculum = Curriculum::factory()->create(['name' => 'TVET Diploma Curriculum']);
    $certificateCurriculum = Curriculum::factory()->create(['name' => 'TVET Certificate Curriculum']);

    $user = User::factory()->create(['role' => Role::Student, 'login_id' => 'ADM-2026-0001']);
    $student = StudentProfile::factory()->create(['user_id' => $user->id]);

    CourseEnrolment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $diploma->id,
        'curriculum_id' => $diplomaCurriculum->id,
        'status' => 'ENROLLED',
    ]);
    CourseEnrolment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $certificate->id,
        'curriculum_id' => $certificateCurriculum->id,
        'status' => 'ENROLLED',
    ]);

    Unit::factory()->create([
        'course_id' => $diploma->id,
        'curriculum_id' => $diplomaCurriculum->id,
        'code' => 'DIT 1102',
        'name' => 'Programming Fundamentals',
        'modules_taught' => 3,
        'core' => true,
        'is_active' => true,
    ]);
    Unit::factory()->create([
        'course_id' => $diploma->id,
        'curriculum_id' => $diplomaCurriculum->id,
        'code' => 'DIT 1101',
        'name' => 'ICT Fundamentals',
        'modules_taught' => 3,
        'core' => true,
        'is_active' => true,
    ]);
    Unit::factory()->create([
        'course_id' => $diploma->id,
        'curriculum_id' => $diplomaCurriculum->id,
        'code' => 'DIT 1201',
        'name' => 'Entrepreneurship for ICT',
        'modules_taught' => 2,
        'core' => false,
        'is_active' => true,
    ]);
    Unit::factory()->create([
        'course_id' => $diploma->id,
        'curriculum_id' => $diplomaCurriculum->id,
        'code' => 'DIT 1301',
        'name' => 'Industrial Attachment',
        'modules_taught' => null,
        'core' => true,
        'is_active' => true,
    ]);
    // Retired units are not offered to students.
    Unit::factory()->create([
        'course_id' => $diploma->id,
        'curriculum_id' => $diplomaCurriculum->id,
        'code' => 'DIT 9999',
        'name' => 'Retired Unit',
        'modules_taught' => 3,
        'core' => true,
        'is_active' => false,
    ]);
    Unit::factory()->create([
        'course_id' => $certificate->id,
        'curriculum_id' => $certificateCurriculum->id,
        'code' => 'CIT 1101',
        'name' => 'ICT Applications',
        'modules_taught' => 3,
        'core' => true,
        'is_active' => true,
    ]);

    $this->withToken(myUnitsAccessTokenFor($user))
        ->getJson('/api/me/units')
        ->assertOk()
        ->assertJsonStructure([
            'groups' => [[
                'course' => ['id', 'initials', 'name'],
                'curriculum' => ['id', 'name'],
                'stats' => ['total', 'core', 'elective'],
                'modules' => [[
                    'modules_taught',
                    'total',
                    'units' => [[
                        'id', 'code', 'name', 'modules_taught', 'taught_hours',
                        'credit_factor', 'core', 'is_active',
                    ]],
                ]],
            ]],
        ])
        // Groups are ordered by course name.
        ->assertJsonPath('groups.0.course.name', 'Certificate in ICT')
        ->assertJsonPath('groups.1.course.name', 'Diploma in ICT')
        ->assertJsonPath('groups.1.curriculum.name', 'TVET Diploma Curriculum')
        ->assertJsonPath('groups.1.stats.total', 4)
        ->assertJsonPath('groups.1.stats.core', 3)
        ->assertJsonPath('groups.1.stats.elective', 1)
        ->assertJsonCount(3, 'groups.1.modules')
        // Modules ascend by count; units without a count come last.
        ->assertJsonPath('groups.1.modules.0.modules_taught', 2)
        ->assertJsonPath('groups.1.modules.0.total', 1)
        ->assertJsonPath('groups.1.modules.0.units.0.code', 'DIT 1201')
        ->assertJsonPath('groups.1.modules.1.modules_taught', 3)
        ->assertJsonPath('groups.1.modules.1.total', 2)
        // Units within a module stay ordered by code, retired removed.
        ->assertJsonPath('groups.1.modules.1.units.0.code', 'DIT 1101')
        ->assertJsonPath('groups.1.modules.1.units.1.code', 'DIT 1102')
        ->assertJsonPath('groups.1.modules.2.modules_taught', null)
        ->assertJsonPath('groups.1.modules.2.units.0.code', 'DIT 1301')
        ->assertJsonPath('groups.0.stats.total', 1)
        ->assertJsonPath('groups.0.modules.0.modules_taught', 3);
});

test('my units never leaks another student\'s course units', function () {
    $course = Course::factory()->create(['name' => 'Diploma in ICT']);
    $curriculum = Curriculum::factory()->create();
    Unit::factory()->create([
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
        'code' => 'DIT 1101',
    ]);

    $user = User::factory()->create(['role' => Role::Student]);
    StudentProfile::factory()->create(['user_id' => $user->id]);

    // A different student is enrolled in that course; our student is not.
    CourseEnrolment::factory()->create([
        'student_id' => StudentProfile::factory()->create()->id,
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
    ]);

    $this->withToken(myUnitsAccessTokenFor($user))
        ->getJson('/api/me/units')
        ->assertOk()
        ->assertJsonCount(0, 'groups');
});

test('a student without a profile row gets no groups', function () {
    $user = User::factory()->create(['role' => Role::Student]);

    $this->withToken(myUnitsAccessTokenFor($user))
        ->getJson('/api/me/units')
        ->assertOk()
        ->assertJsonCount(0, 'groups');
});

test('an enrolment whose course has no units still renders an empty group', function () {
    $course = Course::factory()->create(['name' => 'Diploma in ICT']);
    $curriculum = Curriculum::factory()->create();

    $user = User::factory()->create(['role' => Role::Student]);
    $student = StudentProfile::factory()->create(['user_id' => $user->id]);

    CourseEnrolment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
    ]);

    $this->withToken(myUnitsAccessTokenFor($user))
        ->getJson('/api/me/units')
        ->assertOk()
        ->assertJsonCount(1, 'groups')
        ->assertJsonPath('groups.0.stats.total', 0)
        ->assertJsonCount(0, 'groups.0.modules');
});
