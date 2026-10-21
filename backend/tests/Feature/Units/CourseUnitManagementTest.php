<?php

use App\Models\Course;
use App\Models\CourseUnit;
use App\Models\Curriculum;
use App\Models\Unit;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function courseUnitsAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('course unit endpoints require an access token', function () {
    $this->getJson('/api/course-units')->assertUnauthorized();
    $this->postJson('/api/course-units', [
        'unit_id' => 1,
        'course_id' => 1,
        'curriculum_id' => 1,
    ])->assertUnauthorized();
});

test('the list returns links with their unit, course and curriculum', function () {
    $course = Course::factory()->create(['name' => 'ICT Technician Level 4']);
    $curriculum = Curriculum::factory()->create(['name' => 'Cycle 4']);

    $unit = Unit::factory()->create(['code' => 'U101', 'name' => 'Computer Systems']);
    CourseUnit::factory()->create([
        'unit_id' => $unit->id,
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
        'modules_taught' => 8,
        'taught_hours' => 45,
        'credit_factor' => 2.5,
        'unit_type' => 'core',
        'is_active' => true,
    ]);

    $this->withToken(courseUnitsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/course-units')
        ->assertOk()
        ->assertJsonStructure([
            'course_units' => [[
                'id', 'unit_id', 'course_id', 'curriculum_id', 'modules_taught',
                'taught_hours', 'credit_factor', 'unit_type', 'is_active',
                'unit' => ['id', 'code', 'name'],
                'course' => ['id', 'name'],
                'curriculum' => ['id', 'name'],
            ]],
        ])
        ->assertJsonCount(1, 'course_units')
        ->assertJsonPath('course_units.0.unit.code', 'U101')
        ->assertJsonPath('course_units.0.course.name', 'ICT Technician Level 4')
        ->assertJsonPath('course_units.0.curriculum.name', 'Cycle 4')
        ->assertJsonPath('course_units.0.modules_taught', 8)
        ->assertJsonPath('course_units.0.credit_factor', '2.50');
});

test('the list searches by the linked unit code and name', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();

    $arithmetic = Unit::factory()->create(['code' => 'U900', 'name' => 'Retired Arithmetic']);
    $systems = Unit::factory()->create(['code' => 'U101', 'name' => 'Computer Systems']);

    CourseUnit::factory()->create(['unit_id' => $arithmetic->id, 'course_id' => $course->id, 'curriculum_id' => $curriculum->id]);
    CourseUnit::factory()->create(['unit_id' => $systems->id, 'course_id' => $course->id, 'curriculum_id' => $curriculum->id]);

    $token = courseUnitsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->getJson('/api/course-units?search=arithmetic')
        ->assertOk()
        ->assertJsonCount(1, 'course_units')
        ->assertJsonPath('course_units.0.unit.code', 'U900');

    $this->withToken($token)
        ->getJson('/api/course-units?search=U101')
        ->assertOk()
        ->assertJsonCount(1, 'course_units')
        ->assertJsonPath('course_units.0.unit.name', 'Computer Systems');

    $sorted = $this->withToken($token)
        ->getJson('/api/course-units?sort=code&order=desc')
        ->assertOk();

    expect(collect($sorted->json('course_units'))->pluck('unit.code')->all())
        ->toBe(['U900', 'U101']);

    $this->withToken($token)
        ->getJson('/api/course-units?per_page=1&page=2')
        ->assertOk()
        ->assertJsonCount(1, 'course_units')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.current_page', 2);
});

test('the list filters by course, curriculum, unit and active flag', function () {
    $course = Course::factory()->create();
    $otherCourse = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $otherCurriculum = Curriculum::factory()->create();

    $first = Unit::factory()->create();
    $second = Unit::factory()->create();

    CourseUnit::factory()->create([
        'unit_id' => $first->id, 'course_id' => $course->id,
        'curriculum_id' => $curriculum->id, 'is_active' => true,
    ]);
    CourseUnit::factory()->create([
        'unit_id' => $second->id, 'course_id' => $course->id,
        'curriculum_id' => $otherCurriculum->id, 'is_active' => false,
    ]);
    CourseUnit::factory()->create([
        'unit_id' => $second->id, 'course_id' => $otherCourse->id,
        'curriculum_id' => $curriculum->id, 'is_active' => true,
    ]);

    $token = courseUnitsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->getJson('/api/course-units?course_id='.$course->id)
        ->assertOk()
        ->assertJsonCount(2, 'course_units');

    $this->withToken($token)
        ->getJson('/api/course-units?course_id='.$course->id.'&curriculum_id='.$curriculum->id)
        ->assertOk()
        ->assertJsonCount(1, 'course_units')
        ->assertJsonPath('course_units.0.unit_id', $first->id);

    $this->withToken($token)
        ->getJson('/api/course-units?unit_id='.$second->id)
        ->assertOk()
        ->assertJsonCount(2, 'course_units');

    $this->withToken($token)
        ->getJson('/api/course-units?is_active=0')
        ->assertOk()
        ->assertJsonCount(1, 'course_units')
        ->assertJsonPath('course_units.0.unit_id', $second->id)
        ->assertJsonPath('course_units.0.course_id', $course->id);
});

test('a unit can be linked into a course with its teaching load and audit stamps', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $unit = Unit::factory()->create(['code' => 'U101']);
    $creator = User::factory()->create();

    $this->withToken(courseUnitsAccessTokenFor($creator))
        ->postJson('/api/course-units', [
            'unit_id' => $unit->id,
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
            'modules_taught' => 8,
            'taught_hours' => 45,
            'credit_factor' => 2.5,
            'unit_type' => 'core',
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonStructure([
            'course_unit' => ['id', 'unit_id', 'course_id', 'curriculum_id', 'modules_taught', 'taught_hours', 'credit_factor', 'unit_type', 'is_active'],
        ])
        ->assertJsonPath('course_unit.unit_id', $unit->id)
        ->assertJsonPath('course_unit.modules_taught', 8)
        ->assertJsonPath('course_unit.taught_hours', 45)
        ->assertJsonPath('course_unit.credit_factor', '2.50')
        ->assertJsonPath('course_unit.unit_type', 'core')
        ->assertJsonPath('course_unit.created_by', $creator->id)
        ->assertJsonPath('course_unit.updated_by', $creator->id);

    $this->assertDatabaseHas('course_units', [
        'unit_id' => $unit->id,
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
        'created_by' => $creator->id,
    ]);
});

test('the unit type must be one of basic, core or common', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $unit = Unit::factory()->create();

    $token = courseUnitsAccessTokenFor(User::factory()->create());
    $payload = ['unit_id' => $unit->id, 'course_id' => $course->id, 'curriculum_id' => $curriculum->id];

    $this->withToken($token)
        ->postJson('/api/course-units', [...$payload, 'unit_type' => 'elective'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_type']);

    // The link falls back to the default type when the field is omitted.
    $this->withToken($token)
        ->postJson('/api/course-units', $payload)
        ->assertCreated()
        ->assertJsonPath('course_unit.unit_type', 'core');
});

test('the same unit cannot be linked into the same course and curriculum twice', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $unit = Unit::factory()->create();

    CourseUnit::factory()->create([
        'unit_id' => $unit->id,
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
    ]);

    $token = courseUnitsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->postJson('/api/course-units', [
            'unit_id' => $unit->id,
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_id']);

    $this->assertDatabaseCount('course_units', 1);
});

test('a link requires live unit, course and curriculum rows', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $unit = Unit::factory()->create();
    $deletedUnit = Unit::factory()->create();
    $deletedUnit->delete();

    $token = courseUnitsAccessTokenFor(User::factory()->create());
    $payload = ['unit_id' => $unit->id, 'course_id' => $course->id, 'curriculum_id' => $curriculum->id];

    $this->withToken($token)
        ->postJson('/api/course-units', [...$payload, 'unit_id' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_id']);

    $this->withToken($token)
        ->postJson('/api/course-units', [...$payload, 'course_id' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['course_id']);

    $this->withToken($token)
        ->postJson('/api/course-units', [...$payload, 'curriculum_id' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['curriculum_id']);

    // A soft-deleted unit is no longer linkable.
    $this->withToken($token)
        ->postJson('/api/course-units', [...$payload, 'unit_id' => $deletedUnit->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_id']);
});

test('a link\'s delivery fields can be updated but its identity stays put', function () {
    $course = Course::factory()->create();
    $otherCourse = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $unit = Unit::factory()->create();

    $link = CourseUnit::factory()->create([
        'unit_id' => $unit->id,
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
        'modules_taught' => 1,
        'taught_hours' => 30,
        'credit_factor' => 1,
        'unit_type' => 'core',
        'is_active' => true,
    ]);
    $updater = User::factory()->create();

    $this->withToken(courseUnitsAccessTokenFor($updater))
        ->putJson('/api/course-units/'.$link->id, [
            // Attempted identity moves are ignored: a link is created
            // and removed, never re-pointed.
            'unit_id' => 0,
            'course_id' => $otherCourse->id,
            'curriculum_id' => 0,
            'modules_taught' => 10,
            'taught_hours' => 60,
            'credit_factor' => 3.5,
            'unit_type' => 'common',
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('course_unit.unit_id', $unit->id)
        ->assertJsonPath('course_unit.course_id', $course->id)
        ->assertJsonPath('course_unit.curriculum_id', $curriculum->id)
        ->assertJsonPath('course_unit.modules_taught', 10)
        ->assertJsonPath('course_unit.taught_hours', 60)
        ->assertJsonPath('course_unit.credit_factor', '3.50')
        ->assertJsonPath('course_unit.unit_type', 'common')
        ->assertJsonPath('course_unit.is_active', false)
        ->assertJsonPath('course_unit.updated_by', $updater->id);

    $this->assertDatabaseHas('course_units', [
        'id' => $link->id,
        'course_id' => $course->id,
        'modules_taught' => 10,
        'credit_factor' => 3.5,
    ]);
});

test('removing a link keeps the unit in the catalogue', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $unit = Unit::factory()->create(['code' => 'U101']);

    $link = CourseUnit::factory()->create([
        'unit_id' => $unit->id,
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
    ]);

    $this->withToken(courseUnitsAccessTokenFor(User::factory()->create()))
        ->deleteJson('/api/course-units/'.$link->id)
        ->assertNoContent();

    $this->assertDatabaseMissing('course_units', ['id' => $link->id]);
    $this->assertDatabaseHas('units', ['id' => $unit->id, 'code' => 'U101']);
});

test('a course units listing returns only that course\'s links sorted by curriculum', function () {
    $course = Course::factory()->create();
    $otherCourse = Course::factory()->create();
    $laterCurriculum = Curriculum::factory()->create(['name' => 'Cycle 5']);
    $firstCurriculum = Curriculum::factory()->create(['name' => 'Cycle 4']);

    $systems = Unit::factory()->create(['code' => 'U101', 'name' => 'Computer Systems']);
    $networking = Unit::factory()->create(['code' => 'U102', 'name' => 'Networking Basics']);

    CourseUnit::factory()->create([
        'unit_id' => $networking->id,
        'course_id' => $course->id,
        'curriculum_id' => $laterCurriculum->id,
    ]);
    CourseUnit::factory()->create([
        'unit_id' => $systems->id,
        'course_id' => $course->id,
        'curriculum_id' => $firstCurriculum->id,
    ]);
    CourseUnit::factory()->create([
        'unit_id' => $systems->id,
        'course_id' => $otherCourse->id,
        'curriculum_id' => $firstCurriculum->id,
    ]);

    $this->withToken(courseUnitsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/courses/'.$course->id.'/units')
        ->assertOk()
        ->assertJsonCount(2, 'course_units')
        // Sorted by curriculum name, then unit name.
        ->assertJsonPath('course_units.0.curriculum.name', 'Cycle 4')
        ->assertJsonPath('course_units.0.unit.code', 'U101')
        ->assertJsonPath('course_units.1.curriculum.name', 'Cycle 5');
});
