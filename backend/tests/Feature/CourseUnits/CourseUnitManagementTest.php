<?php

use App\Models\Course;
use App\Models\CourseUnit;
use App\Models\Curriculum;
use App\Models\Unit;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function courseUnitAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('assignment endpoints require an access token', function () {
    $unit = Unit::factory()->create();

    $this->getJson('/api/units/'.$unit->id.'/assignments')->assertUnauthorized();
    $this->postJson('/api/units/'.$unit->id.'/assignments', ['assignments' => []])
        ->assertUnauthorized();
    $this->getJson('/api/courses/1/units')->assertUnauthorized();
    $this->getJson('/api/course-units/1')->assertUnauthorized();
});

test('one unit can be assigned to several courses in a single request', function () {
    $unit = Unit::factory()->create(['code' => 'BCS', 'name' => 'Basic Computer Skills']);
    $course = Course::factory()->create(['name' => 'ICT Technician Level 4']);
    $otherCourse = Course::factory()->create(['name' => 'Diploma in Business']);
    $curriculum = Curriculum::factory()->create(['name' => 'Cycle 4']);
    $creator = User::factory()->create();

    $this->withToken(courseUnitAccessTokenFor($creator))
        ->postJson('/api/units/'.$unit->id.'/assignments', [
            'assignments' => [
                [
                    'course_id' => $course->id,
                    'curriculum_id' => $curriculum->id,
                    'core' => true,
                    'time_offered' => 'Year 1 Term 1',
                    'taught_hours' => 60,
                    'credit_factor' => 2.5,
                ],
                [
                    // Every delivery carries the master's code; only the
                    // teaching load differs per course.
                    'course_id' => $otherCourse->id,
                    'curriculum_id' => $curriculum->id,
                    'core' => false,
                    'time_offered' => 'Year 1 Term 2',
                    'taught_hours' => 45,
                    'credit_factor' => 1.5,
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonCount(2, 'assignments')
        ->assertJsonStructure([
            'assignments' => [[
                'id', 'unit_id', 'unit_name', 'course_id', 'course_name',
                'curriculum_id', 'curriculum_name', 'core', 'time_offered',
                'taught_hours', 'credit_factor', 'is_active',
            ]],
        ])
        ->assertJsonPath('assignments.0.unit_name', 'Basic Computer Skills')
        ->assertJsonPath('assignments.0.course_name', 'ICT Technician Level 4')
        ->assertJsonPath('assignments.0.core', true)
        ->assertJsonPath('assignments.0.time_offered', 'Year 1 Term 1')
        ->assertJsonPath('assignments.0.credit_factor', '2.50')
        ->assertJsonPath('assignments.1.course_name', 'Diploma in Business')
        ->assertJsonPath('assignments.1.core', false)
        ->assertJsonPath('assignments.1.time_offered', 'Year 1 Term 2')
        ->assertJsonPath('assignments.1.credit_factor', '1.50')
        ->assertJsonPath('assignments.1.created_by', $creator->id);

    $this->assertDatabaseCount('course_units', 2);
    // The master catalogue row is not duplicated or altered by assigning.
    $this->assertDatabaseCount('units', 1)
        ->assertDatabaseHas('units', ['id' => $unit->id, 'code' => 'BCS', 'name' => 'Basic Computer Skills']);
});

test('the same unit carries independent values per curriculum', function () {
    $unit = Unit::factory()->create();
    $course = Course::factory()->create();
    $firstCurriculum = Curriculum::factory()->create(['name' => 'Cycle 4']);
    $secondCurriculum = Curriculum::factory()->create(['name' => 'Cycle 5']);

    $this->withToken(courseUnitAccessTokenFor(User::factory()->create()))
        ->postJson('/api/units/'.$unit->id.'/assignments', [
            'assignments' => [
                [
                    'course_id' => $course->id,
                    'curriculum_id' => $firstCurriculum->id,
                    'time_offered' => 'Year 1 Term 1',
                    'taught_hours' => 40,
                    'credit_factor' => 1.5,
                ],
                [
                    'course_id' => $course->id,
                    'curriculum_id' => $secondCurriculum->id,
                    'time_offered' => 'Year 2 Term 1',
                    'taught_hours' => 60,
                    'credit_factor' => 2.5,
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonCount(2, 'assignments');

    $this->assertDatabaseCount('course_units', 2);
});

test('the same unit cannot be assigned twice to one course and curriculum', function () {
    $unit = Unit::factory()->create();
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();

    CourseUnit::factory()->for($unit, 'unit')->for($course, 'course')->for($curriculum, 'curriculum')
        ->create();

    $this->withToken(courseUnitAccessTokenFor(User::factory()->create()))
        ->postJson('/api/units/'.$unit->id.'/assignments', [
            'assignments' => [[
                'course_id' => $course->id,
                'curriculum_id' => $curriculum->id,
            ]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['assignments.0.curriculum_id']);

    $this->assertDatabaseCount('course_units', 1);
});

test('a batch cannot assign the same course and curriculum twice', function () {
    $unit = Unit::factory()->create();
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();

    $this->withToken(courseUnitAccessTokenFor(User::factory()->create()))
        ->postJson('/api/units/'.$unit->id.'/assignments', [
            'assignments' => [
                ['course_id' => $course->id, 'curriculum_id' => $curriculum->id],
                ['course_id' => $course->id, 'curriculum_id' => $curriculum->id],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['assignments']);

    $this->assertDatabaseCount('course_units', 0);
});

test('a batch with one invalid row creates nothing', function () {
    $unit = Unit::factory()->create();
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();

    $this->withToken(courseUnitAccessTokenFor(User::factory()->create()))
        ->postJson('/api/units/'.$unit->id.'/assignments', [
            'assignments' => [
                ['course_id' => $course->id, 'curriculum_id' => $curriculum->id],
                ['course_id' => 999999, 'curriculum_id' => 999998],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['assignments.1.course_id', 'assignments.1.curriculum_id']);

    $this->assertDatabaseCount('course_units', 0);
});

test('an assignment requires an existing live course and curriculum', function () {
    $unit = Unit::factory()->create();
    $deletedCourse = Course::factory()->create();
    $deletedCourse->delete();

    $this->withToken(courseUnitAccessTokenFor(User::factory()->create()))
        ->postJson('/api/units/'.$unit->id.'/assignments', [
            'assignments' => [[
                'course_id' => $deletedCourse->id,
                'curriculum_id' => 999998,
            ]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['assignments.0.course_id', 'assignments.0.curriculum_id']);
});

test('the teaching load fields must be within bounds', function () {
    $unit = Unit::factory()->create();
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();

    $this->withToken(courseUnitAccessTokenFor(User::factory()->create()))
        ->postJson('/api/units/'.$unit->id.'/assignments', [
            'assignments' => [[
                'course_id' => $course->id,
                'curriculum_id' => $curriculum->id,
                'core' => 'yes',
                'time_offered' => str_repeat('x', 101),
                'taught_hours' => -5,
                'credit_factor' => -2,
            ]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'assignments.0.core',
            'assignments.0.time_offered',
            'assignments.0.taught_hours',
            'assignments.0.credit_factor',
        ]);
});

test('editing one assignment does not change another of the same unit', function () {
    $unit = Unit::factory()->create();
    $course = Course::factory()->create();
    $otherCourse = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();

    $first = CourseUnit::factory()->for($unit, 'unit')->for($course, 'course')->for($curriculum, 'curriculum')
        ->create(['credit_factor' => 1.5, 'taught_hours' => 40, 'time_offered' => 'Year 1 Term 1']);
    $second = CourseUnit::factory()->for($unit, 'unit')->for($otherCourse, 'course')->for($curriculum, 'curriculum')
        ->create(['credit_factor' => 2.5, 'taught_hours' => 60, 'core' => true, 'time_offered' => 'Year 1 Term 1']);
    $updater = User::factory()->create();

    $this->withToken(courseUnitAccessTokenFor($updater))
        ->putJson('/api/course-units/'.$first->id, [
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
            'core' => false,
            'time_offered' => 'Year 2 Term 2',
            'taught_hours' => 75,
            'credit_factor' => 3,
            'is_active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('course_unit.core', false)
        ->assertJsonPath('course_unit.time_offered', 'Year 2 Term 2')
        ->assertJsonPath('course_unit.credit_factor', '3.00')
        ->assertJsonPath('course_unit.updated_by', $updater->id);

    $second->refresh();

    expect((float) $second->credit_factor)->toBe(2.5)
        ->and((int) $second->taught_hours)->toBe(60)
        ->and($second->core)->toBeTrue()
        ->and($second->time_offered)->toBe('Year 1 Term 1');
});

test('an assignment cannot be moved onto a course and curriculum combo that is taken', function () {
    $unit = Unit::factory()->create();
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $otherCurriculum = Curriculum::factory()->create();

    $occupied = CourseUnit::factory()->for($unit, 'unit')->for($course, 'course')->for($curriculum, 'curriculum')
        ->create();
    $moving = CourseUnit::factory()->for($unit, 'unit')->for($course, 'course')->for($otherCurriculum, 'curriculum')
        ->create();

    $token = courseUnitAccessTokenFor(User::factory()->create());

    // Same course + curriculum combo already carries this unit.
    $this->withToken($token)
        ->putJson('/api/course-units/'.$moving->id, [
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['curriculum_id']);

    // Keeping its own combo (ignore-self) is fine.
    $this->withToken($token)
        ->putJson('/api/course-units/'.$moving->id, [
            'course_id' => $course->id,
            'curriculum_id' => $otherCurriculum->id,
            'time_offered' => 'Year 2 Term 1',
        ])
        ->assertOk()
        ->assertJsonPath('course_unit.id', $moving->id);

    expect(CourseUnit::find($occupied->id))->not->toBeNull();
});

test('soft deleting an assignment frees its combo for a fresh assignment', function () {
    $unit = Unit::factory()->create();
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();

    $assignment = CourseUnit::factory()->for($unit, 'unit')->for($course, 'course')->for($curriculum, 'curriculum')
        ->create();
    $token = courseUnitAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->deleteJson('/api/course-units/'.$assignment->id)
        ->assertNoContent();

    $this->assertSoftDeleted('course_units', ['id' => $assignment->id]);

    // The details payload only carries live assignments.
    $this->withToken($token)
        ->getJson('/api/units/'.$unit->id.'/assignments')
        ->assertOk()
        ->assertJsonCount(0, 'assignments');

    // …and the combo can be assigned afresh.
    $this->withToken($token)
        ->postJson('/api/units/'.$unit->id.'/assignments', [
            'assignments' => [[
                'course_id' => $course->id,
                'curriculum_id' => $curriculum->id,
            ]],
        ])
        ->assertCreated()
        ->assertJsonCount(1, 'assignments');
});

test('deactivating the master keeps its assignments intact and listed', function () {
    $unit = Unit::factory()->create(['code' => 'BCS', 'name' => 'Basic Computer Skills']);
    $assignment = CourseUnit::factory()->for($unit, 'unit')
        ->create(['is_active' => true]);

    $this->withToken(courseUnitAccessTokenFor(User::factory()->create()))
        ->putJson('/api/units/'.$unit->id, [
            'code' => 'BCS',
            'name' => 'Basic Computer Skills',
            'description' => null,
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('unit.is_active', false);

    $this->withToken(courseUnitAccessTokenFor(User::factory()->create()))
        ->getJson('/api/units/'.$unit->id.'/assignments')
        ->assertOk()
        ->assertJsonCount(1, 'assignments')
        ->assertJsonPath('assignments.0.id', $assignment->id)
        ->assertJsonPath('assignments.0.is_active', true);
});

test('the assignment list is ordered by course then curriculum name', function () {
    $unit = Unit::factory()->create();
    $zulu = Course::factory()->create(['name' => 'Zulu Course']);
    $alpha = Course::factory()->create(['name' => 'Alpha Course']);
    $curriculum = Curriculum::factory()->create();

    CourseUnit::factory()->for($unit, 'unit')->for($zulu, 'course')->for($curriculum, 'curriculum')
        ->create(['time_offered' => 'Year 2 Term 1']);
    CourseUnit::factory()->for($unit, 'unit')->for($alpha, 'course')->for($curriculum, 'curriculum')
        ->create(['time_offered' => 'Year 1 Term 1']);

    $this->withToken(courseUnitAccessTokenFor(User::factory()->create()))
        ->getJson('/api/units/'.$unit->id.'/assignments')
        ->assertOk()
        ->assertJsonCount(2, 'assignments')
        ->assertJsonPath('assignments.0.time_offered', 'Year 1 Term 1')
        ->assertJsonPath('assignments.0.course_name', 'Alpha Course')
        ->assertJsonPath('assignments.1.time_offered', 'Year 2 Term 1')
        ->assertJsonPath('assignments.1.course_name', 'Zulu Course');
});

test('a single assignment can be shown with its unit, course and curriculum', function () {
    $unit = Unit::factory()->create(['code' => 'BCS', 'name' => 'Basic Computer Skills']);
    $course = Course::factory()->create(['name' => 'ICT Technician Level 4']);
    $curriculum = Curriculum::factory()->create(['name' => 'Cycle 4']);
    $assignment = CourseUnit::factory()->for($unit, 'unit')->for($course, 'course')->for($curriculum, 'curriculum')
        ->create();

    $this->withToken(courseUnitAccessTokenFor(User::factory()->create()))
        ->getJson('/api/course-units/'.$assignment->id)
        ->assertOk()
        ->assertJsonPath('course_unit.id', $assignment->id)
        ->assertJsonPath('course_unit.unit_name', 'Basic Computer Skills')
        ->assertJsonPath('course_unit.course_name', 'ICT Technician Level 4')
        ->assertJsonPath('course_unit.curriculum_name', 'Cycle 4');
});

test("a course's units are listed per curriculum with the master's code", function () {
    $course = Course::factory()->create(['name' => 'ICT Technician Level 4']);
    $otherCourse = Course::factory()->create();
    $cycle4 = Curriculum::factory()->create(['name' => 'Cycle 4']);
    $cycle5 = Curriculum::factory()->create(['name' => 'Cycle 5']);
    $unit = Unit::factory()->create(['code' => 'BCS', 'name' => 'Basic Computer Skills']);
    $otherUnit = Unit::factory()->create(['code' => 'NET', 'name' => 'Networking Basics']);

    CourseUnit::factory()->for($unit, 'unit')->for($course, 'course')->for($cycle5, 'curriculum')
        ->create(['credit_factor' => 2.5, 'core' => false, 'time_offered' => 'Year 2 Term 1']);
    CourseUnit::factory()->for($unit, 'unit')->for($course, 'course')->for($cycle4, 'curriculum')
        ->create(['credit_factor' => 1.5, 'core' => true, 'time_offered' => 'Year 1 Term 1']);
    CourseUnit::factory()->for($otherUnit, 'unit')->for($course, 'course')->for($cycle4, 'curriculum')
        ->create();
    // The same unit under another course must not leak into this view.
    CourseUnit::factory()->for($unit, 'unit')->for($otherCourse, 'course')->for($cycle4, 'curriculum')
        ->create();

    $this->withToken(courseUnitAccessTokenFor(User::factory()->create()))
        ->getJson('/api/courses/'.$course->id.'/units')
        ->assertOk()
        ->assertJsonCount(3, 'assignments')
        ->assertJsonStructure([
            'assignments' => [[
                'id', 'unit_id', 'unit_code', 'unit_name', 'course_id',
                'course_name', 'curriculum_id', 'curriculum_name',
                'core', 'time_offered', 'taught_hours', 'credit_factor', 'is_active',
            ]],
        ])
        // Ordered by curriculum name, then unit name.
        ->assertJsonPath('assignments.0.curriculum_name', 'Cycle 4')
        ->assertJsonPath('assignments.0.unit_code', 'BCS')
        ->assertJsonPath('assignments.0.core', true)
        ->assertJsonPath('assignments.0.time_offered', 'Year 1 Term 1')
        ->assertJsonPath('assignments.0.credit_factor', '1.50')
        ->assertJsonPath('assignments.1.unit_code', 'NET')
        ->assertJsonPath('assignments.2.curriculum_name', 'Cycle 5')
        ->assertJsonPath('assignments.2.unit_code', 'BCS')
        ->assertJsonPath('assignments.2.core', false)
        ->assertJsonPath('assignments.2.time_offered', 'Year 2 Term 1');
});
