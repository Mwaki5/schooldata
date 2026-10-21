<?php

use App\Models\Course;
use App\Models\CourseUnit;
use App\Models\Curriculum;
use App\Models\Unit;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function unitsAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

/**
 * Link a catalogue unit into one course + curriculum pair.
 */
function unitsLink(Unit $unit, Course $course, Curriculum $curriculum): CourseUnit
{
    return CourseUnit::factory()->create([
        'unit_id' => $unit->id,
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
    ]);
}

test('unit endpoints require an access token', function () {
    $this->getJson('/api/units')->assertUnauthorized();
    $this->postJson('/api/units', ['code' => 'U101', 'name' => 'Unit'])
        ->assertUnauthorized();
});

test('the list returns catalogue units with their link counts', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();

    $unit = Unit::factory()->create([
        'code' => 'U101',
        'name' => 'Computer Systems',
        'description' => 'Hardware, operating systems and troubleshooting.',
    ]);
    unitsLink($unit, $course, $curriculum);

    $this->withToken(unitsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/units')
        ->assertOk()
        ->assertJsonStructure([
            'units' => [[
                'id', 'code', 'name', 'description', 'course_units_count',
                'created_by', 'updated_by',
            ]],
        ])
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('units.0.code', 'U101')
        ->assertJsonPath('units.0.name', 'Computer Systems')
        ->assertJsonPath('units.0.course_units_count', 1);
});

test('the list filters by the course and curriculum the unit is linked into', function () {
    $course = Course::factory()->create();
    $otherCourse = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $otherCurriculum = Curriculum::factory()->create();

    $first = Unit::factory()->create(['code' => 'U101']);
    $second = Unit::factory()->create(['code' => 'U102']);
    $third = Unit::factory()->create(['code' => 'U103']);

    unitsLink($first, $course, $curriculum);
    unitsLink($second, $course, $otherCurriculum);
    unitsLink($third, $otherCourse, $curriculum);

    $token = unitsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->getJson('/api/units?course_id='.$course->id)
        ->assertOk()
        ->assertJsonCount(2, 'units');

    $this->withToken($token)
        ->getJson('/api/units?curriculum_id='.$curriculum->id)
        ->assertOk()
        ->assertJsonCount(2, 'units');

    $this->withToken($token)
        ->getJson('/api/units?course_id='.$course->id.'&curriculum_id='.$curriculum->id)
        ->assertOk()
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('units.0.code', 'U101');
});

test('the list supports search by code and name, sorting and pagination', function () {
    Unit::factory()->create(['code' => 'U101', 'name' => 'Computer Systems']);
    Unit::factory()->create(['code' => 'U900', 'name' => 'Retired Arithmetic']);

    $token = unitsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->getJson('/api/units?search=networking')
        ->assertOk()
        ->assertJsonCount(0, 'units');

    $this->withToken($token)
        ->getJson('/api/units?search=arithmetic')
        ->assertOk()
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('units.0.name', 'Retired Arithmetic');

    $this->withToken($token)
        ->getJson('/api/units?search=U900')
        ->assertOk()
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('units.0.code', 'U900');

    $sorted = $this->withToken($token)
        ->getJson('/api/units?sort=code&order=desc')
        ->assertOk();

    expect(collect($sorted->json('units'))->pluck('code')->all())
        ->toBe(['U900', 'U101'])
        ->and($sorted->json('meta.total'))->toBe(2);

    $this->withToken($token)
        ->getJson('/api/units?per_page=1&page=2')
        ->assertOk()
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.current_page', 2);
});

test('a unit shows with every course it is linked into', function () {
    $course = Course::factory()->create(['name' => 'ICT Technician Level 4']);
    $curriculum = Curriculum::factory()->create(['name' => 'Cycle 4']);

    $unit = Unit::factory()->create(['code' => 'U101', 'name' => 'Computer Systems']);
    unitsLink($unit, $course, $curriculum);

    $this->withToken(unitsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/units/'.$unit->id)
        ->assertOk()
        ->assertJsonPath('unit.code', 'U101')
        ->assertJsonPath('unit.course_units_count', 1)
        ->assertJsonPath('unit.course_units.0.course.name', 'ICT Technician Level 4')
        ->assertJsonPath('unit.course_units.0.curriculum.name', 'Cycle 4');
});

test('a unit can be created with its details and audit stamps', function () {
    $creator = User::factory()->create();

    $this->withToken(unitsAccessTokenFor($creator))
        ->postJson('/api/units', [
            'code' => 'U101',
            'name' => 'Computer Systems',
            'description' => 'Hardware, operating systems and troubleshooting.',
        ])
        ->assertCreated()
        ->assertJsonStructure([
            'unit' => ['id', 'code', 'name', 'description', 'course_units_count'],
        ])
        ->assertJsonPath('unit.code', 'U101')
        ->assertJsonPath('unit.name', 'Computer Systems')
        ->assertJsonPath('unit.course_units_count', 0)
        ->assertJsonPath('unit.created_by', $creator->id)
        ->assertJsonPath('unit.updated_by', $creator->id);

    $this->assertDatabaseHas('units', [
        'code' => 'U101',
        'name' => 'Computer Systems',
        'created_by' => $creator->id,
    ]);
});

test('a unit code is required and unique across the catalogue', function () {
    Unit::factory()->create(['code' => 'U101']);

    $token = unitsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->postJson('/api/units', ['code' => '', 'name' => 'Some unit'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);

    // The code identifies the unit catalogue-wide, whatever course
    // later links it.
    $this->withToken($token)
        ->postJson('/api/units', ['code' => 'U101', 'name' => 'Some unit'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('the same code may not identify units in different courses', function () {
    $course = Course::factory()->create();
    $otherCourse = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();

    Unit::factory()->create(['code' => '101']);

    $token = unitsAccessTokenFor(User::factory()->create());

    // One catalogue entry per code: another course links the existing
    // unit instead of creating its own.
    $this->withToken($token)
        ->postJson('/api/units', [
            'course_id' => $otherCourse->id,
            'curriculum_id' => $curriculum->id,
            'code' => '101',
            'name' => 'A different 101',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);

    $this->assertDatabaseCount('units', 1);
});

test('a soft-deleted unit frees its code for reuse and removes its links', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();

    $unit = Unit::factory()->create(['code' => 'U101']);
    unitsLink($unit, $course, $curriculum);

    $token = unitsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->deleteJson('/api/units/'.$unit->id)
        ->assertNoContent();

    $this->assertSoftDeleted('units', ['id' => $unit->id]);

    // The deliveries went with the unit: nothing dangles behind it.
    expect(CourseUnit::query()->where('unit_id', $unit->id)->count())->toBe(0);

    $this->withToken($token)
        ->postJson('/api/units', [
            'code' => 'U101',
            'name' => 'Re-entered unit',
        ])
        ->assertCreated();
});

test('a unit cannot be updated onto another unit\'s code', function () {
    Unit::factory()->create(['code' => 'U101', 'name' => 'Computer Systems']);
    $unit = Unit::factory()->create(['code' => 'U102', 'name' => 'Networking Basics']);

    $this->withToken(unitsAccessTokenFor(User::factory()->create()))
        ->putJson('/api/units/'.$unit->id, [
            'code' => 'U101',
            'name' => 'Networking Basics',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('a unit\'s own details can be updated', function () {
    $unit = Unit::factory()->create(['code' => 'U101', 'name' => 'Computer Systems']);
    $updater = User::factory()->create();

    $this->withToken(unitsAccessTokenFor($updater))
        ->putJson('/api/units/'.$unit->id, [
            'code' => 'U101',
            'name' => 'Computer Systems & Peripherals',
            'description' => null,
        ])
        ->assertOk()
        ->assertJsonPath('unit.name', 'Computer Systems & Peripherals')
        ->assertJsonPath('unit.description', null)
        ->assertJsonPath('unit.updated_by', $updater->id);

    $this->assertDatabaseHas('units', ['id' => $unit->id, 'name' => 'Computer Systems & Peripherals']);
});
