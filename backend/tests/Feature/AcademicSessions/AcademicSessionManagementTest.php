<?php

use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function academicSessionsAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('academic session endpoints require an access token', function () {
    $this->getJson('/api/academic-sessions')->assertUnauthorized();
    $this->postJson('/api/academic-sessions', ['name' => 'Term 1'])
        ->assertUnauthorized();
});

test('academic sessions are listed newest start date first with their year name', function () {
    $year = AcademicYear::factory()->create(['name' => '2025/2026']);
    AcademicSession::factory()->create([
        'academic_year_id' => $year->id,
        'name' => 'Term 1 2025',
        'start_date' => '2025-09-01',
    ]);
    AcademicSession::factory()->create([
        'academic_year_id' => $year->id,
        'name' => 'Term 1 2026',
        'start_date' => '2026-09-01',
    ]);

    $this->withToken(academicSessionsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/academic-sessions')
        ->assertOk()
        ->assertJsonStructure([
            'academic_sessions' => [[
                'id', 'academic_year_id', 'academic_year_name', 'name',
                'start_date', 'end_date', 'description', 'is_active',
                'created_by', 'updated_by',
            ]],
        ])
        ->assertJsonPath('academic_sessions.0.name', 'Term 1 2026')
        ->assertJsonPath('academic_sessions.0.academic_year_name', '2025/2026')
        ->assertJsonPath('academic_sessions.1.name', 'Term 1 2025');
});

test('the academic session list supports year filters, sorting and pagination', function () {
    $yearA = AcademicYear::factory()->create(['name' => '2025/2026']);
    $yearB = AcademicYear::factory()->create(['name' => '2026/2027']);

    AcademicSession::factory()->create([
        'academic_year_id' => $yearA->id,
        'name' => 'Term 1 2025',
        'start_date' => '2025-09-01',
    ]);
    AcademicSession::factory()->create([
        'academic_year_id' => $yearB->id,
        'name' => 'Term 1 2026',
        'start_date' => '2026-09-01',
    ]);

    $token = academicSessionsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->getJson('/api/academic-sessions?academic_year_id='.$yearA->id)
        ->assertOk()
        ->assertJsonCount(1, 'academic_sessions')
        ->assertJsonPath('academic_sessions.0.name', 'Term 1 2025');

    $sorted = $this->withToken($token)
        ->getJson('/api/academic-sessions?sort=name&order=asc')
        ->assertOk();

    expect(collect($sorted->json('academic_sessions'))->pluck('name')->all())
        ->toBe(['Term 1 2025', 'Term 1 2026'])
        ->and($sorted->json('meta.total'))->toBe(2);
});

test('an academic session can be created with audit stamps', function () {
    $creator = User::factory()->create();
    $year = AcademicYear::factory()->create();

    $response = $this->withToken(academicSessionsAccessTokenFor($creator))
        ->postJson('/api/academic-sessions', [
            'academic_year_id' => $year->id,
            'name' => 'Term 1 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-15',
            'description' => 'First term.',
            'is_active' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('academic_session.academic_year_id', $year->id)
        ->assertJsonPath('academic_session.academic_year_name', $year->name)
        ->assertJsonPath('academic_session.name', 'Term 1 2026')
        ->assertJsonPath('academic_session.start_date', '2026-09-01')
        ->assertJsonPath('academic_session.end_date', '2026-12-15')
        ->assertJsonPath('academic_session.is_active', true)
        ->assertJsonPath('academic_session.created_by', $creator->id)
        ->assertJsonPath('academic_session.updated_by', $creator->id);

    $this->assertDatabaseHas('academic_sessions', [
        'name' => 'Term 1 2026',
        'academic_year_id' => $year->id,
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);
});

test('an academic session defaults to inactive and tolerates null dates', function () {
    $user = User::factory()->create();
    $year = AcademicYear::factory()->create();

    $this->withToken(academicSessionsAccessTokenFor($user))
        ->postJson('/api/academic-sessions', [
            'academic_year_id' => $year->id,
            'name' => 'Term 2 2026',
        ])
        ->assertCreated()
        ->assertJsonPath('academic_session.is_active', false)
        ->assertJsonPath('academic_session.start_date', null)
        ->assertJsonPath('academic_session.end_date', null)
        ->assertJsonPath('academic_session.description', null);
});

test('creating an academic session requires a name and an academic year', function () {
    $this->withToken(academicSessionsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/academic-sessions', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['academic_year_id', 'name']);
});

test('the academic year must exist', function () {
    $this->withToken(academicSessionsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/academic-sessions', [
            'academic_year_id' => 999,
            'name' => 'Term 1 2026',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['academic_year_id']);
});

test('the academic session name must be unique', function () {
    $user = User::factory()->create();
    $year = AcademicYear::factory()->create();
    AcademicSession::factory()->create([
        'academic_year_id' => $year->id,
        'name' => 'Term 1 2026',
    ]);

    $this->withToken(academicSessionsAccessTokenFor($user))
        ->postJson('/api/academic-sessions', [
            'academic_year_id' => $year->id,
            'name' => 'Term 1 2026',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('the end date cannot precede the start date', function () {
    $user = User::factory()->create();
    $year = AcademicYear::factory()->create();

    $this->withToken(academicSessionsAccessTokenFor($user))
        ->postJson('/api/academic-sessions', [
            'academic_year_id' => $year->id,
            'name' => 'Term 1 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-01-30',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date']);
});

test('dates must be valid dates', function () {
    $user = User::factory()->create();
    $year = AcademicYear::factory()->create();

    $this->withToken(academicSessionsAccessTokenFor($user))
        ->postJson('/api/academic-sessions', [
            'academic_year_id' => $year->id,
            'name' => 'Term 1 2026',
            'start_date' => 'not-a-date',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date']);
});

test('an academic session can be updated and stamps the updating user', function () {
    $creator = User::factory()->create();
    $updater = User::factory()->create();
    $oldYear = AcademicYear::factory()->create();
    $newYear = AcademicYear::factory()->create();
    $session = AcademicSession::factory()->create([
        'academic_year_id' => $oldYear->id,
        'is_active' => false,
        'created_by' => $creator->id,
    ]);

    $this->withToken(academicSessionsAccessTokenFor($updater))
        ->putJson('/api/academic-sessions/'.$session->id, [
            'academic_year_id' => $newYear->id,
            'name' => 'Term 3 2026',
            'start_date' => '2026-04-01',
            'end_date' => '2026-07-30',
            'description' => 'Revised term.',
            'is_active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('academic_session.name', 'Term 3 2026')
        ->assertJsonPath('academic_session.academic_year_id', $newYear->id)
        ->assertJsonPath('academic_session.academic_year_name', $newYear->name)
        ->assertJsonPath('academic_session.is_active', true)
        ->assertJsonPath('academic_session.created_by', $creator->id)
        ->assertJsonPath('academic_session.updated_by', $updater->id);

    $this->assertDatabaseHas('academic_sessions', [
        'id' => $session->id,
        'name' => 'Term 3 2026',
        'academic_year_id' => $newYear->id,
        'is_active' => 1,
        'created_by' => $creator->id,
        'updated_by' => $updater->id,
    ]);
});

test('updating an academic session validates its name excluding itself', function () {
    $year = AcademicYear::factory()->create();
    $session = AcademicSession::factory()->create([
        'academic_year_id' => $year->id,
        'name' => 'Term 1 2026',
    ]);
    AcademicSession::factory()->create([
        'academic_year_id' => $year->id,
        'name' => 'Term 2 2026',
    ]);

    $this->withToken(academicSessionsAccessTokenFor(User::factory()->create()))
        ->putJson('/api/academic-sessions/'.$session->id, [
            'academic_year_id' => $year->id,
            'name' => 'Term 2 2026',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    $this->withToken(academicSessionsAccessTokenFor(User::factory()->create()))
        ->putJson('/api/academic-sessions/'.$session->id, [
            'academic_year_id' => $year->id,
            'name' => 'Term 1 2026',
        ])
        ->assertOk();
});

test('an academic session is deleted and disappears from the api', function () {
    $user = User::factory()->create();
    $session = AcademicSession::factory()->create();

    $this->withToken(academicSessionsAccessTokenFor($user))
        ->deleteJson('/api/academic-sessions/'.$session->id)
        ->assertNoContent();

    $this->assertDatabaseMissing('academic_sessions', ['id' => $session->id]);

    $this->withToken(academicSessionsAccessTokenFor($user))
        ->getJson('/api/academic-sessions/'.$session->id)
        ->assertNotFound();

    $this->withToken(academicSessionsAccessTokenFor($user))
        ->getJson('/api/academic-sessions')
        ->assertOk()
        ->assertJsonMissing(['id' => $session->id]);
});

test('the academic session list can be searched by name', function () {
    $year = AcademicYear::factory()->create();
    AcademicSession::factory()->create([
        'academic_year_id' => $year->id,
        'name' => 'Term 1 2026',
    ]);
    AcademicSession::factory()->create([
        'academic_year_id' => $year->id,
        'name' => 'Term 2 2026',
    ]);

    $this->withToken(academicSessionsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/academic-sessions?search=Term 2')
        ->assertOk()
        ->assertJsonCount(1, 'academic_sessions')
        ->assertJsonPath('academic_sessions.0.name', 'Term 2 2026');
});

test('a missing academic session returns 404', function () {
    $this->withToken(academicSessionsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/academic-sessions/999')
        ->assertNotFound();
});
