<?php

use App\Models\Curriculum;
use App\Models\ExamBody;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function curriculaAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('curriculum endpoints require an access token', function () {
    $this->getJson('/api/curricula')->assertUnauthorized();
    $this->postJson('/api/curricula', ['name' => '2024 Curriculum'])
        ->assertUnauthorized();
});

test('curricula are listed grouped by exam body then name', function () {
    $bodyA = ExamBody::factory()->create(['name' => 'A Council']);
    $bodyB = ExamBody::factory()->create(['name' => 'B Council']);

    Curriculum::factory()->create([
        'exam_body_id' => $bodyA->id,
        'name' => '2024 Curriculum',
    ]);
    Curriculum::factory()->create([
        'exam_body_id' => $bodyA->id,
        'name' => '2023 Curriculum',
    ]);
    Curriculum::factory()->create([
        'exam_body_id' => $bodyB->id,
        'name' => 'Pilot Curriculum',
    ]);

    $this->withToken(curriculaAccessTokenFor(User::factory()->create()))
        ->getJson('/api/curricula')
        ->assertOk()
        ->assertJsonStructure([
            'curricula' => [[
                'id', 'exam_body_id', 'exam_body_name', 'name', 'is_active',
                'created_by', 'updated_by',
            ]],
        ])
        ->assertJsonPath('curricula.0.name', '2023 Curriculum')
        ->assertJsonPath('curricula.1.name', '2024 Curriculum')
        ->assertJsonPath('curricula.2.exam_body_name', 'B Council');
});

test('the curriculum list supports search, filters, sorting and pagination', function () {
    $bodyA = ExamBody::factory()->create(['name' => 'A Council']);
    $bodyB = ExamBody::factory()->create(['name' => 'B Council']);

    Curriculum::factory()->create([
        'exam_body_id' => $bodyA->id,
        'name' => '2024 Curriculum',
    ]);
    Curriculum::factory()->create([
        'exam_body_id' => $bodyB->id,
        'name' => 'Pilot Curriculum',
    ]);

    $token = curriculaAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->getJson('/api/curricula?search=pilot')
        ->assertOk()
        ->assertJsonCount(1, 'curricula')
        ->assertJsonPath('curricula.0.name', 'Pilot Curriculum');

    $this->withToken($token)
        ->getJson('/api/curricula?exam_body_id='.$bodyA->id)
        ->assertOk()
        ->assertJsonCount(1, 'curricula')
        ->assertJsonPath('curricula.0.name', '2024 Curriculum');

    $sorted = $this->withToken($token)
        ->getJson('/api/curricula?sort=name&order=desc')
        ->assertOk();

    expect(collect($sorted->json('curricula'))->pluck('name')->all())
        ->toBe(['Pilot Curriculum', '2024 Curriculum'])
        ->and($sorted->json('meta.total'))->toBe(2);
});

test('a curriculum can be created with audit stamps', function () {
    $creator = User::factory()->create();
    $examBody = ExamBody::factory()->create();

    $response = $this->withToken(curriculaAccessTokenFor($creator))
        ->postJson('/api/curricula', [
            'exam_body_id' => $examBody->id,
            'name' => '2024 Curriculum',
            'is_active' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('curriculum.exam_body_id', $examBody->id)
        ->assertJsonPath('curriculum.exam_body_name', $examBody->name)
        ->assertJsonPath('curriculum.name', '2024 Curriculum')
        ->assertJsonPath('curriculum.is_active', true)
        ->assertJsonPath('curriculum.created_by', $creator->id)
        ->assertJsonPath('curriculum.updated_by', $creator->id);

    $this->assertDatabaseHas('curricula', [
        'exam_body_id' => $examBody->id,
        'name' => '2024 Curriculum',
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);
});

test('a curriculum defaults to active', function () {
    $user = User::factory()->create();
    $examBody = ExamBody::factory()->create();

    $this->withToken(curriculaAccessTokenFor($user))
        ->postJson('/api/curricula', [
            'exam_body_id' => $examBody->id,
            'name' => '2024 Curriculum',
        ])
        ->assertCreated()
        ->assertJsonPath('curriculum.is_active', true);
});

test('creating a curriculum requires its core fields', function () {
    $this->withToken(curriculaAccessTokenFor(User::factory()->create()))
        ->postJson('/api/curricula', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['exam_body_id', 'name']);
});

test('the exam body must be a live exam body', function () {
    $user = User::factory()->create();
    $deletedBody = ExamBody::factory()->create();
    $deletedBody->delete();

    $this->withToken(curriculaAccessTokenFor($user))
        ->postJson('/api/curricula', [
            'exam_body_id' => $deletedBody->id,
            'name' => '2024 Curriculum',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['exam_body_id']);

    $this->withToken(curriculaAccessTokenFor($user))
        ->postJson('/api/curricula', [
            'exam_body_id' => 999999,
            'name' => '2024 Curriculum',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['exam_body_id']);
});

test('the same name may be used in different exam bodies', function () {
    $user = User::factory()->create();
    $bodyOne = ExamBody::factory()->create();
    $bodyTwo = ExamBody::factory()->create();

    $this->withToken(curriculaAccessTokenFor($user))
        ->postJson('/api/curricula', [
            'exam_body_id' => $bodyOne->id,
            'name' => '2024 Curriculum',
        ])
        ->assertCreated();

    $this->withToken(curriculaAccessTokenFor($user))
        ->postJson('/api/curricula', [
            'exam_body_id' => $bodyTwo->id,
            'name' => '2024 Curriculum',
        ])
        ->assertCreated();
});

test('a name must be unique within its exam body', function () {
    $examBody = ExamBody::factory()->create();
    Curriculum::factory()->create([
        'exam_body_id' => $examBody->id,
        'name' => '2024 Curriculum',
    ]);

    $this->withToken(curriculaAccessTokenFor(User::factory()->create()))
        ->postJson('/api/curricula', [
            'exam_body_id' => $examBody->id,
            'name' => '2024 Curriculum',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('a curriculum can be updated and stamps the updating user', function () {
    $creator = User::factory()->create();
    $updater = User::factory()->create();
    $examBody = ExamBody::factory()->create();
    $curriculum = Curriculum::factory()->create([
        'exam_body_id' => $examBody->id,
        'name' => '2024 Curriculum',
        'is_active' => true,
        'created_by' => $creator->id,
    ]);

    $this->withToken(curriculaAccessTokenFor($updater))
        ->putJson('/api/curricula/'.$curriculum->id, [
            'exam_body_id' => $examBody->id,
            'name' => '2025 Curriculum',
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('curriculum.name', '2025 Curriculum')
        ->assertJsonPath('curriculum.is_active', false)
        ->assertJsonPath('curriculum.created_by', $creator->id)
        ->assertJsonPath('curriculum.updated_by', $updater->id);

    $this->assertDatabaseHas('curricula', [
        'id' => $curriculum->id,
        'name' => '2025 Curriculum',
        'is_active' => 0,
        'created_by' => $creator->id,
        'updated_by' => $updater->id,
    ]);
});

test('updating a curriculum validates its name excluding itself', function () {
    $examBody = ExamBody::factory()->create();
    $curriculum = Curriculum::factory()->create([
        'exam_body_id' => $examBody->id,
        'name' => '2024 Curriculum',
    ]);
    Curriculum::factory()->create([
        'exam_body_id' => $examBody->id,
        'name' => '2023 Curriculum',
    ]);

    $this->withToken(curriculaAccessTokenFor(User::factory()->create()))
        ->putJson('/api/curricula/'.$curriculum->id, [
            'exam_body_id' => $examBody->id,
            'name' => '2023 Curriculum',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    $this->withToken(curriculaAccessTokenFor(User::factory()->create()))
        ->putJson('/api/curricula/'.$curriculum->id, [
            'exam_body_id' => $examBody->id,
            'name' => '2024 Curriculum',
        ])
        ->assertOk();
});

test('a curriculum is soft deleted and disappears from the api', function () {
    $user = User::factory()->create();
    $curriculum = Curriculum::factory()->create();

    $this->withToken(curriculaAccessTokenFor($user))
        ->deleteJson('/api/curricula/'.$curriculum->id)
        ->assertNoContent();

    $this->assertSoftDeleted('curricula', ['id' => $curriculum->id]);

    $this->withToken(curriculaAccessTokenFor($user))
        ->getJson('/api/curricula/'.$curriculum->id)
        ->assertNotFound();

    $this->withToken(curriculaAccessTokenFor($user))
        ->getJson('/api/curricula')
        ->assertOk()
        ->assertJsonMissing(['id' => $curriculum->id]);
});

test('a soft deleted curriculum frees its name for reuse in the same body', function () {
    $user = User::factory()->create();
    $examBody = ExamBody::factory()->create();
    Curriculum::factory()->create([
        'exam_body_id' => $examBody->id,
        'name' => '2024 Curriculum',
    ])->delete();

    $this->withToken(curriculaAccessTokenFor($user))
        ->postJson('/api/curricula', [
            'exam_body_id' => $examBody->id,
            'name' => '2024 Curriculum',
        ])
        ->assertCreated()
        ->assertJsonPath('curriculum.name', '2024 Curriculum');
});

test('a missing curriculum returns 404', function () {
    $this->withToken(curriculaAccessTokenFor(User::factory()->create()))
        ->getJson('/api/curricula/999')
        ->assertNotFound();
});
