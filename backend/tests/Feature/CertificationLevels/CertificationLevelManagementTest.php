<?php

use App\Models\CertificationLevel;
use App\Models\ExamBody;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function certificationLevelsAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('certification level endpoints require an access token', function () {
    $this->getJson('/api/certification-levels')->assertUnauthorized();
    $this->postJson('/api/certification-levels', ['name' => 'Diploma'])
        ->assertUnauthorized();
});

test('certification levels are listed grouped by exam body then sequence', function () {
    $bodyA = ExamBody::factory()->create(['name' => 'A Council']);
    $bodyB = ExamBody::factory()->create(['name' => 'B Council']);

    CertificationLevel::factory()->create([
        'exam_body_id' => $bodyA->id,
        'name' => 'Diploma',
        'code' => 'DIPLOMA',
        'sequence' => 2,
    ]);
    CertificationLevel::factory()->create([
        'exam_body_id' => $bodyA->id,
        'name' => 'Certificate',
        'code' => 'CERTIFICATE',
        'sequence' => 1,
    ]);
    CertificationLevel::factory()->create([
        'exam_body_id' => $bodyB->id,
        'name' => 'Masters Degree',
        'code' => 'MASTERS',
        'sequence' => 1,
    ]);

    $this->withToken(certificationLevelsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/certification-levels')
        ->assertOk()
        ->assertJsonStructure([
            'certification_levels' => [[
                'id', 'exam_body_id', 'exam_body_name', 'name', 'code',
                'description', 'sequence', 'is_active', 'created_by', 'updated_by',
            ]],
        ])
        ->assertJsonPath('certification_levels.0.name', 'Certificate')
        ->assertJsonPath('certification_levels.1.name', 'Diploma')
        ->assertJsonPath('certification_levels.2.name', 'Masters Degree')
        ->assertJsonPath('certification_levels.2.exam_body_name', 'B Council');
});

test('a certification level can be created with audit stamps', function () {
    $creator = User::factory()->create();
    $examBody = ExamBody::factory()->create();

    $response = $this->withToken(certificationLevelsAccessTokenFor($creator))
        ->postJson('/api/certification-levels', [
            'exam_body_id' => $examBody->id,
            'name' => 'Diploma',
            'code' => 'DIPLOMA',
            'description' => 'A diploma level qualification.',
            'sequence' => 2,
            'is_active' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('certification_level.exam_body_id', $examBody->id)
        ->assertJsonPath('certification_level.exam_body_name', $examBody->name)
        ->assertJsonPath('certification_level.name', 'Diploma')
        ->assertJsonPath('certification_level.code', 'DIPLOMA')
        ->assertJsonPath('certification_level.sequence', 2)
        ->assertJsonPath('certification_level.is_active', true)
        ->assertJsonPath('certification_level.created_by', $creator->id)
        ->assertJsonPath('certification_level.updated_by', $creator->id);

    $this->assertDatabaseHas('certification_levels', [
        'exam_body_id' => $examBody->id,
        'code' => 'DIPLOMA',
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);
});

test('a certification level defaults to active', function () {
    $user = User::factory()->create();
    $examBody = ExamBody::factory()->create();

    $this->withToken(certificationLevelsAccessTokenFor($user))
        ->postJson('/api/certification-levels', [
            'exam_body_id' => $examBody->id,
            'name' => 'Certificate',
            'code' => 'CERTIFICATE',
            'description' => 'Entry level.',
            'sequence' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('certification_level.is_active', true);
});

test('creating a certification level requires its core fields', function () {
    $this->withToken(certificationLevelsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/certification-levels', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'exam_body_id', 'name', 'code', 'description', 'sequence',
        ]);
});

test('the exam body must be a live exam body', function () {
    $user = User::factory()->create();
    $deletedBody = ExamBody::factory()->create();
    $deletedBody->delete();

    $this->withToken(certificationLevelsAccessTokenFor($user))
        ->postJson('/api/certification-levels', [
            'exam_body_id' => $deletedBody->id,
            'name' => 'Diploma',
            'code' => 'DIPLOMA',
            'description' => 'Orphaned level.',
            'sequence' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['exam_body_id']);

    $this->withToken(certificationLevelsAccessTokenFor($user))
        ->postJson('/api/certification-levels', [
            'exam_body_id' => 999999,
            'name' => 'Diploma',
            'code' => 'DIPLOMA',
            'description' => 'Missing body.',
            'sequence' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['exam_body_id']);
});

test('the same code may be used in different exam bodies', function () {
    $user = User::factory()->create();
    ExamBody::factory()->create();

    $bodyOne = ExamBody::factory()->create();
    $bodyTwo = ExamBody::factory()->create();

    $this->withToken(certificationLevelsAccessTokenFor($user))
        ->postJson('/api/certification-levels', [
            'exam_body_id' => $bodyOne->id,
            'name' => 'Diploma',
            'code' => 'DIPLOMA',
            'description' => 'Level one.',
            'sequence' => 1,
        ])
        ->assertCreated();

    $this->withToken(certificationLevelsAccessTokenFor($user))
        ->postJson('/api/certification-levels', [
            'exam_body_id' => $bodyTwo->id,
            'name' => 'Diploma',
            'code' => 'DIPLOMA',
            'description' => 'Level two.',
            'sequence' => 1,
        ])
        ->assertCreated();
});

test('a code must be unique within its exam body', function () {
    $examBody = ExamBody::factory()->create();
    CertificationLevel::factory()->create([
        'exam_body_id' => $examBody->id,
        'code' => 'DIPLOMA',
    ]);

    $this->withToken(certificationLevelsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/certification-levels', [
            'exam_body_id' => $examBody->id,
            'name' => 'Another Diploma',
            'code' => 'DIPLOMA',
            'description' => 'Duplicate.',
            'sequence' => 2,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('sequence must be a small integer', function () {
    $examBody = ExamBody::factory()->create();

    $this->withToken(certificationLevelsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/certification-levels', [
            'exam_body_id' => $examBody->id,
            'name' => 'Diploma',
            'code' => 'DIPLOMA',
            'description' => 'Level.',
            'sequence' => 'first',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sequence']);

    $this->withToken(certificationLevelsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/certification-levels', [
            'exam_body_id' => $examBody->id,
            'name' => 'Diploma',
            'code' => 'DIPLOMA',
            'description' => 'Level.',
            'sequence' => 40000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sequence']);
});

test('a certification level can be updated and stamps the updating user', function () {
    $creator = User::factory()->create();
    $updater = User::factory()->create();
    $examBody = ExamBody::factory()->create();
    $level = CertificationLevel::factory()->create([
        'exam_body_id' => $examBody->id,
        'sequence' => 1,
        'is_active' => true,
        'created_by' => $creator->id,
    ]);

    $this->withToken(certificationLevelsAccessTokenFor($updater))
        ->putJson('/api/certification-levels/'.$level->id, [
            'exam_body_id' => $examBody->id,
            'name' => 'Advanced Diploma',
            'code' => 'ADVDIPLOMA',
            'description' => 'Revised level.',
            'sequence' => 5,
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('certification_level.name', 'Advanced Diploma')
        ->assertJsonPath('certification_level.sequence', 5)
        ->assertJsonPath('certification_level.is_active', false)
        ->assertJsonPath('certification_level.created_by', $creator->id)
        ->assertJsonPath('certification_level.updated_by', $updater->id);

    $this->assertDatabaseHas('certification_levels', [
        'id' => $level->id,
        'name' => 'Advanced Diploma',
        'is_active' => 0,
        'created_by' => $creator->id,
        'updated_by' => $updater->id,
    ]);
});

test('updating a level validates its code excluding itself', function () {
    $examBody = ExamBody::factory()->create();
    $level = CertificationLevel::factory()->create([
        'exam_body_id' => $examBody->id,
        'code' => 'DIPLOMA',
    ]);
    CertificationLevel::factory()->create([
        'exam_body_id' => $examBody->id,
        'code' => 'CERTIFICATE',
    ]);

    $this->withToken(certificationLevelsAccessTokenFor(User::factory()->create()))
        ->putJson('/api/certification-levels/'.$level->id, [
            'exam_body_id' => $examBody->id,
            'name' => 'Diploma',
            'code' => 'CERTIFICATE',
            'description' => 'Duplicate code.',
            'sequence' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);

    $this->withToken(certificationLevelsAccessTokenFor(User::factory()->create()))
        ->putJson('/api/certification-levels/'.$level->id, [
            'exam_body_id' => $examBody->id,
            'name' => 'Diploma',
            'code' => 'DIPLOMA',
            'description' => 'Own code.',
            'sequence' => 1,
        ])
        ->assertOk();
});

test('a certification level is soft deleted and disappears from the api', function () {
    $user = User::factory()->create();
    $level = CertificationLevel::factory()->create();

    $this->withToken(certificationLevelsAccessTokenFor($user))
        ->deleteJson('/api/certification-levels/'.$level->id)
        ->assertNoContent();

    $this->assertSoftDeleted('certification_levels', ['id' => $level->id]);

    $this->withToken(certificationLevelsAccessTokenFor($user))
        ->getJson('/api/certification-levels/'.$level->id)
        ->assertNotFound();

    $this->withToken(certificationLevelsAccessTokenFor($user))
        ->getJson('/api/certification-levels')
        ->assertOk()
        ->assertJsonMissing(['id' => $level->id]);
});

test('a soft deleted level frees its code for reuse in the same body', function () {
    $user = User::factory()->create();
    $examBody = ExamBody::factory()->create();
    CertificationLevel::factory()->create([
        'exam_body_id' => $examBody->id,
        'code' => 'DIPLOMA',
    ])->delete();

    $this->withToken(certificationLevelsAccessTokenFor($user))
        ->postJson('/api/certification-levels', [
            'exam_body_id' => $examBody->id,
            'name' => 'Diploma',
            'code' => 'DIPLOMA',
            'description' => 'Recreated.',
            'sequence' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('certification_level.code', 'DIPLOMA');
});

test('a missing certification level returns 404', function () {
    $this->withToken(certificationLevelsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/certification-levels/999')
        ->assertNotFound();
});
