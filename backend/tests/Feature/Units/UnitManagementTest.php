<?php

use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Unit;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function unitsAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('unit endpoints require an access token', function () {
    $this->getJson('/api/units')->assertUnauthorized();
    $this->postJson('/api/units', ['code' => 'U101', 'name' => 'Unit'])
        ->assertUnauthorized();
});

test('the list returns each offering with its course and curriculum', function () {
    $course = Course::factory()->create(['name' => 'ICT Technician Level 4']);
    $curriculum = Curriculum::factory()->create(['name' => 'Cycle 4']);

    Unit::factory()->for($course, 'course')->for($curriculum, 'curriculum')->create([
        'code' => 'U101',
        'name' => 'Computer Systems',
        'credit_factor' => 2.5,
    ]);

    $this->withToken(unitsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/units')
        ->assertOk()
        ->assertJsonStructure([
            'units' => [[
                'id', 'course_id', 'curriculum_id', 'code', 'name', 'description',
                'modules_taught', 'taught_hours', 'credit_factor', 'core',
                'is_active',
            ]],
        ])
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('units.0.code', 'U101')
        ->assertJsonPath('units.0.course.name', 'ICT Technician Level 4')
        ->assertJsonPath('units.0.curriculum.name', 'Cycle 4')
        ->assertJsonPath('units.0.credit_factor', '2.50');
});

test('the list filters by course and curriculum', function () {
    $course = Course::factory()->create();
    $otherCourse = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $otherCurriculum = Curriculum::factory()->create();

    Unit::factory()->for($course, 'course')->for($curriculum, 'curriculum')->create(['code' => 'U101']);
    Unit::factory()->for($course, 'course')->for($otherCurriculum, 'curriculum')->create(['code' => 'U102']);
    Unit::factory()->for($otherCourse, 'course')->for($curriculum, 'curriculum')->create(['code' => 'U103']);

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

test('the list supports search by code and name, status filter, sorting and pagination', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();

    Unit::factory()->for($course, 'course')->for($curriculum, 'curriculum')->create([
        'code' => 'U101',
        'name' => 'Computer Systems',
        'is_active' => true,
    ]);
    Unit::factory()->for($course, 'course')->for($curriculum, 'curriculum')->create([
        'code' => 'U900',
        'name' => 'Retired Arithmetic',
        'is_active' => false,
    ]);

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

    $this->withToken($token)
        ->getJson('/api/units?is_active=0')
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

test('a course units listing groups its offerings by curriculum names', function () {
    $course = Course::factory()->create(['name' => 'ICT Technician Level 4']);
    $otherCourse = Course::factory()->create();
    $curriculum = Curriculum::factory()->create(['name' => 'Cycle 4']);

    Unit::factory()->for($course, 'course')->for($curriculum, 'curriculum')->create(['code' => 'U101', 'name' => 'Computer Systems']);
    Unit::factory()->for($otherCourse, 'course')->for($curriculum, 'curriculum')->create(['code' => 'U101', 'name' => 'A different unit']);

    $this->withToken(unitsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/courses/'.$course->id.'/units')
        ->assertOk()
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('units.0.code', 'U101')
        ->assertJsonPath('units.0.curriculum.name', 'Cycle 4');
});

test('a unit can be created with its course, curriculum, teaching load and audit stamps', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $creator = User::factory()->create();

    $this->withToken(unitsAccessTokenFor($creator))
        ->postJson('/api/units', [
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
            'code' => 'U101',
            'name' => 'Computer Systems',
            'description' => 'Hardware, operating systems and troubleshooting.',
            'modules_taught' => 8,
            'taught_hours' => 45,
            'credit_factor' => 2.5,
            'core' => true,
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonStructure([
            'unit' => ['id', 'course_id', 'curriculum_id', 'code', 'name', 'description'],
        ])
        ->assertJsonPath('unit.course_id', $course->id)
        ->assertJsonPath('unit.curriculum_id', $curriculum->id)
        ->assertJsonPath('unit.code', 'U101')
        ->assertJsonPath('unit.name', 'Computer Systems')
        ->assertJsonPath('unit.modules_taught', 8)
        ->assertJsonPath('unit.taught_hours', 45)
        ->assertJsonPath('unit.credit_factor', '2.50')
        ->assertJsonPath('unit.core', true)
        ->assertJsonPath('unit.created_by', $creator->id)
        ->assertJsonPath('unit.updated_by', $creator->id);

    $this->assertDatabaseHas('units', [
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
        'code' => 'U101',
        'name' => 'Computer Systems',
        'created_by' => $creator->id,
    ]);
});

test('a unit code is required and must be unique within its course and curriculum', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();

    Unit::factory()->for($course, 'course')->for($curriculum, 'curriculum')->create(['code' => 'U101']);

    $token = unitsAccessTokenFor(User::factory()->create());
    $payload = ['course_id' => $course->id, 'curriculum_id' => $curriculum->id, 'name' => 'Some unit'];

    $this->withToken($token)
        ->postJson('/api/units', [...$payload, 'code' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);

    // Same code under the same course + curriculum is taken.
    $this->withToken($token)
        ->postJson('/api/units', [...$payload, 'code' => 'U101'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);

    // The course and curriculum themselves are required.
    $this->withToken($token)
        ->postJson('/api/units', ['code' => 'U102', 'name' => 'Missing pair'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['course_id', 'curriculum_id']);
});

test('the same code may identify different units in another course or curriculum', function () {
    $course = Course::factory()->create();
    $otherCourse = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $otherCurriculum = Curriculum::factory()->create();

    Unit::factory()->for($course, 'course')->for($curriculum, 'curriculum')->create(['code' => '101']);

    $token = unitsAccessTokenFor(User::factory()->create());

    // A different course may reuse the code for a different unit.
    $this->withToken($token)
        ->postJson('/api/units', [
            'course_id' => $otherCourse->id,
            'curriculum_id' => $curriculum->id,
            'code' => '101',
            'name' => 'A different 101',
        ])
        ->assertCreated();

    // A different curriculum of the same course may too.
    $this->withToken($token)
        ->postJson('/api/units', [
            'course_id' => $course->id,
            'curriculum_id' => $otherCurriculum->id,
            'code' => '101',
            'name' => '101 under the new curriculum',
        ])
        ->assertCreated();

    $this->assertDatabaseCount('units', 3);
});

test('a soft-deleted unit frees its code for reuse within the pair', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $unit = Unit::factory()->for($course, 'course')->for($curriculum, 'curriculum')->create(['code' => 'U101']);

    $token = unitsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->deleteJson('/api/units/'.$unit->id)
        ->assertNoContent();

    $this->assertSoftDeleted('units', ['id' => $unit->id]);

    $this->withToken($token)
        ->postJson('/api/units', [
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
            'code' => 'U101',
            'name' => 'Re-entered unit',
        ])
        ->assertCreated();
});

test('a unit cannot be updated onto another unit\'s code within the pair', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();

    Unit::factory()->for($course, 'course')->for($curriculum, 'curriculum')->create(['code' => 'U101', 'name' => 'Computer Systems']);
    $unit = Unit::factory()->for($course, 'course')->for($curriculum, 'curriculum')->create(['code' => 'U102', 'name' => 'Networking Basics']);

    $this->withToken(unitsAccessTokenFor(User::factory()->create()))
        ->putJson('/api/units/'.$unit->id, [
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
            'code' => 'U101',
            'name' => 'Networking Basics',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('a unit teaching load can be updated', function () {
    $unit = Unit::factory()->create(['code' => 'U101', 'name' => 'Computer Systems', 'credit_factor' => 1]);
    $updater = User::factory()->create();

    $this->withToken(unitsAccessTokenFor($updater))
        ->putJson('/api/units/'.$unit->id, [
            'course_id' => $unit->course_id,
            'curriculum_id' => $unit->curriculum_id,
            'code' => 'U101',
            'name' => 'Computer Systems & Peripherals',
            'description' => null,
            'modules_taught' => 10,
            'taught_hours' => 60,
            'credit_factor' => 3.5,
            'core' => false,
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('unit.name', 'Computer Systems & Peripherals')
        ->assertJsonPath('unit.modules_taught', 10)
        ->assertJsonPath('unit.taught_hours', 60)
        ->assertJsonPath('unit.credit_factor', '3.50')
        ->assertJsonPath('unit.core', false)
        ->assertJsonPath('unit.is_active', false)
        ->assertJsonPath('unit.updated_by', $updater->id);

    $this->assertDatabaseHas('units', ['id' => $unit->id, 'credit_factor' => 3.5]);
});
