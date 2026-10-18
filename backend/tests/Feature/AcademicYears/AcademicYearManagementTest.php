<?php

use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function academicYearsAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('academic year endpoints require an access token', function () {
    $this->getJson('/api/academic-years')->assertUnauthorized();
    $this->postJson('/api/academic-years', ['name' => '2026/2027'])
        ->assertUnauthorized();
});

test('academic years are listed newest start date first', function () {
    AcademicYear::factory()->create([
        'name' => '2025/2026',
        'start_date' => '2025-09-01',
    ]);
    AcademicYear::factory()->create([
        'name' => '2026/2027',
        'start_date' => '2026-09-01',
    ]);

    $this->withToken(academicYearsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/academic-years')
        ->assertOk()
        ->assertJsonStructure([
            'academic_years' => [[
                'id', 'name', 'start_date', 'end_date',
                'description', 'is_active', 'created_by', 'updated_by',
            ]],
        ])
        ->assertJsonPath('academic_years.0.name', '2026/2027')
        ->assertJsonPath('academic_years.1.name', '2025/2026');
});

test('the academic year list supports status filters, sorting and pagination', function () {
    AcademicYear::factory()->create([
        'name' => '2025/2026',
        'start_date' => '2025-09-01',
        'is_active' => false,
    ]);
    AcademicYear::factory()->create([
        'name' => '2026/2027',
        'start_date' => '2026-09-01',
        'is_active' => true,
    ]);

    $token = academicYearsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->getJson('/api/academic-years?is_active=1')
        ->assertOk()
        ->assertJsonCount(1, 'academic_years')
        ->assertJsonPath('academic_years.0.name', '2026/2027');

    $sorted = $this->withToken($token)
        ->getJson('/api/academic-years?sort=name&order=asc')
        ->assertOk();

    expect(collect($sorted->json('academic_years'))->pluck('name')->all())
        ->toBe(['2025/2026', '2026/2027'])
        ->and($sorted->json('meta.total'))->toBe(2);
});

test('an academic year can be created with audit stamps', function () {
    $creator = User::factory()->create();

    $response = $this->withToken(academicYearsAccessTokenFor($creator))
        ->postJson('/api/academic-years', [
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'description' => 'Standard school year.',
            'is_active' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('academic_year.name', '2026/2027')
        ->assertJsonPath('academic_year.start_date', '2026-09-01')
        ->assertJsonPath('academic_year.end_date', '2027-06-30')
        ->assertJsonPath('academic_year.is_active', true)
        ->assertJsonPath('academic_year.created_by', $creator->id)
        ->assertJsonPath('academic_year.updated_by', $creator->id);

    $this->assertDatabaseHas('academic_years', [
        'name' => '2026/2027',
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);
});

test('an academic year defaults to inactive and tolerates null dates', function () {
    $user = User::factory()->create();

    $this->withToken(academicYearsAccessTokenFor($user))
        ->postJson('/api/academic-years', [
            'name' => '2027/2028',
        ])
        ->assertCreated()
        ->assertJsonPath('academic_year.is_active', false)
        ->assertJsonPath('academic_year.start_date', null)
        ->assertJsonPath('academic_year.end_date', null)
        ->assertJsonPath('academic_year.description', null);
});

test('creating an academic year requires a name', function () {
    $this->withToken(academicYearsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/academic-years', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('the academic year name must be unique', function () {
    $user = User::factory()->create();
    AcademicYear::factory()->create(['name' => '2026/2027']);

    $this->withToken(academicYearsAccessTokenFor($user))
        ->postJson('/api/academic-years', [
            'name' => '2026/2027',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('the end date cannot precede the start date', function () {
    $user = User::factory()->create();

    $this->withToken(academicYearsAccessTokenFor($user))
        ->postJson('/api/academic-years', [
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2025-06-30',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date']);
});

test('dates must be valid dates', function () {
    $user = User::factory()->create();

    $this->withToken(academicYearsAccessTokenFor($user))
        ->postJson('/api/academic-years', [
            'name' => '2026/2027',
            'start_date' => 'not-a-date',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date']);
});

test('an academic year can be updated and stamps the updating user', function () {
    $creator = User::factory()->create();
    $updater = User::factory()->create();
    $year = AcademicYear::factory()->create([
        'is_active' => false,
        'created_by' => $creator->id,
    ]);

    $this->withToken(academicYearsAccessTokenFor($updater))
        ->putJson('/api/academic-years/'.$year->id, [
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'description' => 'Revised year.',
            'is_active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('academic_year.name', '2026/2027')
        ->assertJsonPath('academic_year.is_active', true)
        ->assertJsonPath('academic_year.created_by', $creator->id)
        ->assertJsonPath('academic_year.updated_by', $updater->id);

    $this->assertDatabaseHas('academic_years', [
        'id' => $year->id,
        'name' => '2026/2027',
        'is_active' => 1,
        'created_by' => $creator->id,
        'updated_by' => $updater->id,
    ]);
});

test('updating an academic year validates its name excluding itself', function () {
    $year = AcademicYear::factory()->create(['name' => '2026/2027']);
    AcademicYear::factory()->create(['name' => '2025/2026']);

    $this->withToken(academicYearsAccessTokenFor(User::factory()->create()))
        ->putJson('/api/academic-years/'.$year->id, [
            'name' => '2025/2026',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    $this->withToken(academicYearsAccessTokenFor(User::factory()->create()))
        ->putJson('/api/academic-years/'.$year->id, [
            'name' => '2026/2027',
        ])
        ->assertOk();
});

test('an academic year is deleted and disappears from the api', function () {
    $user = User::factory()->create();
    $year = AcademicYear::factory()->create();

    $this->withToken(academicYearsAccessTokenFor($user))
        ->deleteJson('/api/academic-years/'.$year->id)
        ->assertNoContent();

    $this->assertDatabaseMissing('academic_years', ['id' => $year->id]);

    $this->withToken(academicYearsAccessTokenFor($user))
        ->getJson('/api/academic-years/'.$year->id)
        ->assertNotFound();

    $this->withToken(academicYearsAccessTokenFor($user))
        ->getJson('/api/academic-years')
        ->assertOk()
        ->assertJsonMissing(['id' => $year->id]);
});

test('the academic year list can be searched by name', function () {
    AcademicYear::factory()->create(['name' => '2026/2027']);
    AcademicYear::factory()->create(['name' => '2027/2028']);

    $this->withToken(academicYearsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/academic-years?search=2027/2028')
        ->assertOk()
        ->assertJsonCount(1, 'academic_years')
        ->assertJsonPath('academic_years.0.name', '2027/2028');
});

test('a missing academic year returns 404', function () {
    $this->withToken(academicYearsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/academic-years/999')
        ->assertNotFound();
});

test('activating a new academic year automatically retires the active one', function () {
    $user = User::factory()->create();
    AcademicYear::factory()->create(['name' => '2025/2026', 'is_active' => true]);

    $this->withToken(academicYearsAccessTokenFor($user))
        ->postJson('/api/academic-years', [
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('academic_year.is_active', true);

    $this->assertDatabaseHas('academic_years', ['name' => '2025/2026', 'is_active' => false])
        ->assertDatabaseHas('academic_years', ['name' => '2026/2027', 'is_active' => true]);
});

test('updating a year to active deactivates the other active year', function () {
    $user = User::factory()->create();
    AcademicYear::factory()->create(['name' => '2025/2026', 'is_active' => true]);
    $year = AcademicYear::factory()->create(['name' => '2026/2027', 'is_active' => false]);

    $this->withToken(academicYearsAccessTokenFor($user))
        ->putJson('/api/academic-years/'.$year->id, [
            'name' => '2026/2027',
            'is_active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('academic_year.is_active', true);

    $this->assertDatabaseHas('academic_years', ['name' => '2025/2026', 'is_active' => false])
        ->assertDatabaseHas('academic_years', ['name' => '2026/2027', 'is_active' => true]);
});

test('saving a year as inactive leaves the active year untouched', function () {
    $user = User::factory()->create();
    AcademicYear::factory()->create(['name' => '2025/2026', 'is_active' => true]);
    $year = AcademicYear::factory()->create(['name' => '2026/2027', 'is_active' => false]);

    $this->withToken(academicYearsAccessTokenFor($user))
        ->putJson('/api/academic-years/'.$year->id, [
            'name' => '2026/2027',
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('academic_year.is_active', false);

    $this->assertDatabaseHas('academic_years', ['name' => '2025/2026', 'is_active' => true])
        ->assertDatabaseHas('academic_years', ['name' => '2026/2027', 'is_active' => false]);
});

test('an academic year cannot start after its existing sessions', function () {
    $user = User::factory()->create();
    $year = AcademicYear::factory()->create([
        'name' => '2026/2027',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    AcademicSession::factory()->create([
        'academic_year_id' => $year->id,
        'name' => 'Term 1 2026',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-15',
    ]);

    $this->withToken(academicYearsAccessTokenFor($user))
        ->putJson('/api/academic-years/'.$year->id, [
            'name' => '2026/2027',
            'start_date' => '2026-09-02',
            'end_date' => '2026-12-31',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date']);
});

test('an academic year cannot end before its existing sessions', function () {
    $user = User::factory()->create();
    $year = AcademicYear::factory()->create([
        'name' => '2026/2027',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    AcademicSession::factory()->create([
        'academic_year_id' => $year->id,
        'name' => 'Term 3 2026',
        'start_date' => '2026-04-20',
        'end_date' => '2026-08-25',
    ]);

    $this->withToken(academicYearsAccessTokenFor($user))
        ->putJson('/api/academic-years/'.$year->id, [
            'name' => '2026/2027',
            'start_date' => '2026-01-01',
            'end_date' => '2026-08-24',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date']);
});
