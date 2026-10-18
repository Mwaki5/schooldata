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

test('unit endpoints require an access token', function () {
    $this->getJson('/api/units')->assertUnauthorized();
    $this->postJson('/api/units', ['code' => 'U101', 'name' => 'Unit'])
        ->assertUnauthorized();
    $this->getJson('/api/units/duplicates')->assertUnauthorized();
});

test('the catalogue lists each master once with its code and assignment count', function () {
    $course = Course::factory()->create(['name' => 'ICT Technician Level 4']);
    $otherCourse = Course::factory()->create(['name' => 'Diploma in Business']);
    $curriculum = Curriculum::factory()->create(['name' => 'Cycle 4']);

    $shared = Unit::factory()->create(['code' => 'U101', 'name' => 'Computer Systems']);
    CourseUnit::factory()->for($shared, 'unit')->for($course, 'course')->for($curriculum, 'curriculum')
        ->create();
    CourseUnit::factory()->for($shared, 'unit')->for($otherCourse, 'course')->for($curriculum, 'curriculum')
        ->create();

    Unit::factory()->create(['code' => 'U102', 'name' => 'Networking Basics']);

    $this->withToken(unitsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/units')
        ->assertOk()
        ->assertJsonStructure([
            'units' => [[
                'id', 'code', 'name', 'description', 'is_active', 'assignments_count',
            ]],
        ])
        ->assertJsonCount(2, 'units')
        ->assertJsonPath('units.0.code', 'U101')
        ->assertJsonPath('units.0.name', 'Computer Systems')
        ->assertJsonPath('units.0.assignments_count', 2)
        ->assertJsonPath('units.1.code', 'U102')
        ->assertJsonPath('units.1.name', 'Networking Basics')
        ->assertJsonPath('units.1.assignments_count', 0);
});

test('the catalogue supports search by code and name, status filter, sorting and pagination', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();

    $active = Unit::factory()->create(['code' => 'U101', 'name' => 'Computer Systems', 'is_active' => true]);
    CourseUnit::factory()->for($active, 'unit')->for($course, 'course')->for($curriculum, 'curriculum')
        ->create();

    Unit::factory()->create(['code' => 'U900', 'name' => 'Retired Arithmetic', 'is_active' => false]);

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

    // The global unit code is searchable.
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
        ->getJson('/api/units?sort=assignments_count&order=desc')
        ->assertOk();

    expect(collect($sorted->json('units'))->pluck('code')->all())
        ->toBe(['U101', 'U900'])
        ->and($sorted->json('meta.total'))->toBe(2);

    $this->withToken($token)
        ->getJson('/api/units?per_page=1&page=2')
        ->assertOk()
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.current_page', 2);
});

test('a unit details payload carries its assignments with course and curriculum names', function () {
    $course = Course::factory()->create(['name' => 'ICT Technician Level 4']);
    $curriculum = Curriculum::factory()->create(['name' => 'Cycle 4']);
    $unit = Unit::factory()->create(['code' => 'U101', 'name' => 'Computer Systems']);

    CourseUnit::factory()->for($unit, 'unit')->for($course, 'course')->for($curriculum, 'curriculum')
        ->create(['credit_factor' => 2.5]);

    $this->withToken(unitsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/units/'.$unit->id)
        ->assertOk()
        ->assertJsonPath('unit.id', $unit->id)
        ->assertJsonPath('unit.code', 'U101')
        ->assertJsonPath('unit.name', 'Computer Systems')
        ->assertJsonPath('unit.assignments_count', 1)
        ->assertJsonCount(1, 'unit.assignments')
        ->assertJsonFragment([
            'course_name' => 'ICT Technician Level 4',
            'curriculum_name' => 'Cycle 4',
            'credit_factor' => '2.50',
        ]);
});

test('a master unit can be created with its code and audit stamps', function () {
    $creator = User::factory()->create();

    $this->withToken(unitsAccessTokenFor($creator))
        ->postJson('/api/units', [
            'code' => 'U101',
            'name' => 'Computer Systems',
            'description' => 'Hardware, operating systems and troubleshooting.',
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonStructure([
            'unit' => ['id', 'code', 'name', 'description', 'is_active', 'assignments_count'],
        ])
        ->assertJsonPath('unit.code', 'U101')
        ->assertJsonPath('unit.name', 'Computer Systems')
        ->assertJsonPath('unit.assignments_count', 0)
        ->assertJsonPath('unit.created_by', $creator->id)
        ->assertJsonPath('unit.updated_by', $creator->id);

    $this->assertDatabaseHas('units', [
        'code' => 'U101',
        'name' => 'Computer Systems',
        'created_by' => $creator->id,
    ]);
});

test('a unit code is required and must be unique among live units', function () {
    Unit::factory()->create(['code' => 'U101']);
    $token = unitsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->postJson('/api/units', ['code' => '', 'name' => 'Missing code'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);

    // Two catalogue entries may share a name (deliberately distinct
    // units), but never a code.
    $this->withToken($token)
        ->postJson('/api/units', ['code' => 'U101', 'name' => 'A different unit with a taken code'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);

    $this->withToken($token)
        ->postJson('/api/units', ['code' => 'U102', 'name' => 'Computer Systems'])
        ->assertCreated();
});

test('a soft-deleted unit frees its code for reuse', function () {
    $unit = Unit::factory()->create(['code' => 'U101']);
    $token = unitsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->deleteJson('/api/units/'.$unit->id)
        ->assertNoContent();

    $this->assertSoftDeleted('units', ['id' => $unit->id]);

    $this->withToken($token)
        ->postJson('/api/units', ['code' => 'U101', 'name' => 'Re-entered unit'])
        ->assertCreated();
});

test('a unit cannot be updated onto another unit\'s code', function () {
    Unit::factory()->create(['code' => 'U101', 'name' => 'Computer Systems']);
    $unit = Unit::factory()->create(['code' => 'U102', 'name' => 'Networking Basics']);
    $token = unitsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->putJson('/api/units/'.$unit->id, [
            'code' => 'U101',
            'name' => 'Networking Basics',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('renaming and deactivating a master leaves its assignments untouched', function () {
    $unit = Unit::factory()->create(['code' => 'U101', 'name' => 'Computer Systems']);
    $assignment = CourseUnit::factory()->for($unit, 'unit')
        ->create(['credit_factor' => 2.5, 'is_active' => true]);
    $updater = User::factory()->create();

    $this->withToken(unitsAccessTokenFor($updater))
        ->putJson('/api/units/'.$unit->id, [
            'code' => 'U101',
            'name' => 'Computer Systems & Peripherals',
            'description' => null,
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('unit.name', 'Computer Systems & Peripherals')
        ->assertJsonPath('unit.is_active', false)
        ->assertJsonPath('unit.updated_by', $updater->id);

    $assignment->refresh();

    expect((float) $assignment->credit_factor)->toBe(2.5)
        ->and($assignment->is_active)->toBeTrue();
});

test('a unit with assignments cannot be deleted until they are removed', function () {
    $unit = Unit::factory()->create();
    $assignment = CourseUnit::factory()->for($unit, 'unit')->create();

    $this->withToken(unitsAccessTokenFor(User::factory()->create()))
        ->deleteJson('/api/units/'.$unit->id)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This unit is still assigned to courses. Remove its assignments before deleting it.');

    expect(Unit::find($unit->id))->not->toBeNull();

    $this->deleteJson('/api/course-units/'.$assignment->id)->assertNoContent();

    $this->deleteJson('/api/units/'.$unit->id)->assertNoContent();

    $this->assertSoftDeleted('units', ['id' => $unit->id]);
});

test('the duplicates report groups case-insensitive name matches, including retired units', function () {
    Unit::factory()->create(['name' => 'Computer Systems']);
    Unit::factory()->create(['name' => 'computer systems']);
    Unit::factory()->create(['name' => '  Networking Basics '])->delete();
    Unit::factory()->create(['name' => 'Networking Basics']);

    $response = $this->withToken(unitsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/units/duplicates')
        ->assertOk()
        ->assertJsonCount(2, 'duplicates');

    $groups = collect($response->json('duplicates'))->keyBy('normalized_name');

    expect($groups['computer systems']['total'])->toBe(2)
        ->and($groups['networking basics']['total'])->toBe(2)
        ->and($groups['computer systems']['units'])->toHaveCount(2);
});

test('distinct units that merely share no name are not reported as duplicates', function () {
    Unit::factory()->create(['name' => 'Computer Systems']);
    Unit::factory()->create(['name' => 'Networking Basics']);

    $this->withToken(unitsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/units/duplicates')
        ->assertOk()
        ->assertJsonCount(0, 'duplicates');
});
