<?php

use App\Enums\Role;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\CourseUnit;
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

/**
 * A unit offered by one course + curriculum: the catalogue entry plus
 * its active course-unit link. Unit fields (code, name) go on the unit;
 * delivery fields go on the link.
 */
function myUnitsUnit(Course $course, Curriculum $curriculum, array $overrides = []): Unit
{
    $unitFields = array_intersect_key($overrides, array_flip(['code', 'name', 'description']));
    $linkFields = array_intersect_key($overrides, array_flip(['modules_taught', 'taught_hours', 'credit_factor', 'unit_type', 'is_active']));

    $unit = Unit::factory()->create($unitFields);

    CourseUnit::factory()->create(array_merge([
        'unit_id' => $unit->id,
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
        'modules_taught' => 1,
        'unit_type' => 'core',
        'is_active' => true,
    ], $linkFields));

    return $unit;
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

    myUnitsUnit($diploma, $diplomaCurriculum, [
        'code' => 'DIT 1102',
        'name' => 'Programming Fundamentals',
        'modules_taught' => 3,
        'unit_type' => 'core',
    ]);
    myUnitsUnit($diploma, $diplomaCurriculum, [
        'code' => 'DIT 1101',
        'name' => 'ICT Fundamentals',
        'modules_taught' => 3,
        'unit_type' => 'core',
    ]);
    myUnitsUnit($diploma, $diplomaCurriculum, [
        'code' => 'DIT 1201',
        'name' => 'Entrepreneurship for ICT',
        'modules_taught' => 2,
        'unit_type' => 'common',
    ]);
    myUnitsUnit($diploma, $diplomaCurriculum, [
        'code' => 'DIT 1301',
        'name' => 'Industrial Attachment',
        'modules_taught' => null,
        'unit_type' => 'core',
    ]);
    // Retired deliveries are not offered to students.
    myUnitsUnit($diploma, $diplomaCurriculum, [
        'code' => 'DIT 9999',
        'name' => 'Retired Unit',
        'modules_taught' => 3,
        'unit_type' => 'core',
        'is_active' => false,
    ]);
    myUnitsUnit($certificate, $certificateCurriculum, [
        'code' => 'CIT 1101',
        'name' => 'ICT Applications',
        'modules_taught' => 3,
        'unit_type' => 'core',
    ]);

    $this->withToken(myUnitsAccessTokenFor($user))
        ->getJson('/api/me/units')
        ->assertOk()
        ->assertJsonStructure([
            'groups' => [[
                'course' => ['id', 'initials', 'name'],
                'curriculum' => ['id', 'name'],
                'stats' => ['total', 'basic', 'core', 'common'],
                'modules' => [[
                    'modules_taught',
                    'total',
                    'units' => [[
                        'id', 'code', 'name', 'description', 'taught_hours',
                        'credit_factor', 'unit_type',
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
        ->assertJsonPath('groups.1.stats.common', 1)
        ->assertJsonPath('groups.1.stats.basic', 0)
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
    myUnitsUnit($course, $curriculum, [
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
