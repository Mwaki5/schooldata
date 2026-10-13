<?php

use App\Models\ExamBody;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function examBodiesAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('exam body endpoints require an access token', function () {
    $this->getJson('/api/exam-bodies')->assertUnauthorized();
    $this->postJson('/api/exam-bodies', ['name' => 'KNEC', 'code' => 'KNEC'])
        ->assertUnauthorized();
});

test('exam bodies can be listed ordered by name', function () {
    ExamBody::factory()->create(['name' => 'ZWA Examination Board']);
    ExamBody::factory()->create(['name' => 'Amanza Testing Authority']);

    $this->withToken(examBodiesAccessTokenFor(User::factory()->create()))
        ->getJson('/api/exam-bodies')
        ->assertOk()
        ->assertJsonStructure([
            'exam_bodies' => [[
                'id', 'name', 'code', 'website', 'email', 'phone',
                'address', 'description', 'is_active', 'created_by', 'updated_by',
            ]],
        ])
        ->assertJsonPath('exam_bodies.0.name', 'Amanza Testing Authority')
        ->assertJsonPath('exam_bodies.1.name', 'ZWA Examination Board');
});

test('an exam body can be created with every field and audit stamps', function () {
    $creator = User::factory()->create();

    $response = $this->withToken(examBodiesAccessTokenFor($creator))
        ->postJson('/api/exam-bodies', [
            'name' => 'Kenya National Examinations Council',
            'code' => 'KNEC',
            'website' => 'https://www.knec.ac.ke',
            'email' => 'info@knec.ac.ke',
            'phone' => '+254 20 123 4567',
            'address' => 'P.O. Box 30000, Nairobi',
            'description' => 'National examination authority.',
            'is_active' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('exam_body.name', 'Kenya National Examinations Council')
        ->assertJsonPath('exam_body.code', 'KNEC')
        ->assertJsonPath('exam_body.website', 'https://www.knec.ac.ke')
        ->assertJsonPath('exam_body.email', 'info@knec.ac.ke')
        ->assertJsonPath('exam_body.phone', '+254 20 123 4567')
        ->assertJsonPath('exam_body.address', 'P.O. Box 30000, Nairobi')
        ->assertJsonPath('exam_body.description', 'National examination authority.')
        ->assertJsonPath('exam_body.is_active', true)
        ->assertJsonPath('exam_body.created_by', $creator->id)
        ->assertJsonPath('exam_body.updated_by', $creator->id);

    $this->assertDatabaseHas('exam_bodies', [
        'code' => 'KNEC',
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);
});

test('an exam body only needs a name and code', function () {
    $user = User::factory()->create();

    $this->withToken(examBodiesAccessTokenFor($user))
        ->postJson('/api/exam-bodies', [
            'name' => 'WAEC',
            'code' => 'WAEC',
        ])
        ->assertCreated()
        ->assertJsonPath('exam_body.is_active', true)
        ->assertJsonPath('exam_body.website', null)
        ->assertJsonPath('exam_body.email', null)
        ->assertJsonPath('exam_body.phone', null)
        ->assertJsonPath('exam_body.address', null)
        ->assertJsonPath('exam_body.description', null)
        ->assertJsonPath('exam_body.created_by', $user->id);
});

test('creating an exam body requires a name and code', function () {
    $this->withToken(examBodiesAccessTokenFor(User::factory()->create()))
        ->postJson('/api/exam-bodies', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'code']);
});

test('exam body codes must be unique among live records', function () {
    ExamBody::factory()->create(['code' => 'KNEC']);

    $this->withToken(examBodiesAccessTokenFor(User::factory()->create()))
        ->postJson('/api/exam-bodies', [
            'name' => 'Another Council',
            'code' => 'KNEC',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('exam body website and email must be valid formats', function () {
    $this->withToken(examBodiesAccessTokenFor(User::factory()->create()))
        ->postJson('/api/exam-bodies', [
            'name' => 'Bad Formats Council',
            'code' => 'BFC',
            'website' => 'not-a-url',
            'email' => 'not-an-email',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['website', 'email']);
});

test('an exam body can be updated and stamps the updating user', function () {
    $creator = User::factory()->create();
    $updater = User::factory()->create();
    $examBody = ExamBody::factory()->create([
        'code' => 'KNEC',
        'is_active' => true,
        'created_by' => $creator->id,
    ]);

    $this->withToken(examBodiesAccessTokenFor($updater))
        ->putJson('/api/exam-bodies/'.$examBody->id, [
            'name' => 'Revised Council Name',
            'code' => 'KNEC',
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('exam_body.name', 'Revised Council Name')
        ->assertJsonPath('exam_body.is_active', false)
        ->assertJsonPath('exam_body.created_by', $creator->id)
        ->assertJsonPath('exam_body.updated_by', $updater->id);

    $this->assertDatabaseHas('exam_bodies', [
        'id' => $examBody->id,
        'name' => 'Revised Council Name',
        'is_active' => 0,
        'created_by' => $creator->id,
        'updated_by' => $updater->id,
    ]);
});

test('updating an exam body validates its code excluding itself', function () {
    $examBody = ExamBody::factory()->create(['code' => 'KNEC']);
    ExamBody::factory()->create(['code' => 'WAEC']);

    $this->withToken(examBodiesAccessTokenFor(User::factory()->create()))
        ->putJson('/api/exam-bodies/'.$examBody->id, [
            'name' => 'Kenya Council',
            'code' => 'WAEC',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);

    $this->withToken(examBodiesAccessTokenFor(User::factory()->create()))
        ->putJson('/api/exam-bodies/'.$examBody->id, [
            'name' => 'Kenya Council',
            'code' => 'KNEC',
        ])
        ->assertOk();
});

test('an exam body is soft deleted and disappears from the api', function () {
    $user = User::factory()->create();
    $examBody = ExamBody::factory()->create(['code' => 'KNEC']);

    $this->withToken(examBodiesAccessTokenFor($user))
        ->deleteJson('/api/exam-bodies/'.$examBody->id)
        ->assertNoContent();

    $this->assertSoftDeleted('exam_bodies', ['id' => $examBody->id]);

    $this->withToken(examBodiesAccessTokenFor($user))
        ->getJson('/api/exam-bodies/'.$examBody->id)
        ->assertNotFound();

    $this->withToken(examBodiesAccessTokenFor($user))
        ->getJson('/api/exam-bodies')
        ->assertOk()
        ->assertJsonMissing(['id' => $examBody->id]);
});

test('a soft deleted exam body frees its code for reuse', function () {
    $user = User::factory()->create();
    ExamBody::factory()->create(['code' => 'KNEC'])->delete();

    $this->withToken(examBodiesAccessTokenFor($user))
        ->postJson('/api/exam-bodies', [
            'name' => 'Kenya National Examinations Council',
            'code' => 'KNEC',
        ])
        ->assertCreated()
        ->assertJsonPath('exam_body.code', 'KNEC');
});

test('exam bodies can be searched by name', function () {
    ExamBody::factory()->create(['name' => 'Kenya National Examinations Council']);
    ExamBody::factory()->create(['name' => 'West African Examinations Council']);

    $this->withToken(examBodiesAccessTokenFor(User::factory()->create()))
        ->getJson('/api/exam-bodies?search=kenya')
        ->assertOk()
        ->assertJsonCount(1, 'exam_bodies')
        ->assertJsonPath('exam_bodies.0.name', 'Kenya National Examinations Council');
});

test('exam bodies can be searched by code', function () {
    ExamBody::factory()->create(['code' => 'KNEC']);
    ExamBody::factory()->create(['code' => 'WAEC']);

    $this->withToken(examBodiesAccessTokenFor(User::factory()->create()))
        ->getJson('/api/exam-bodies?search=knec')
        ->assertOk()
        ->assertJsonCount(1, 'exam_bodies')
        ->assertJsonPath('exam_bodies.0.code', 'KNEC');
});

test('a search with no matches returns an empty list', function () {
    ExamBody::factory()->create(['name' => 'Kenya National Examinations Council']);

    $this->withToken(examBodiesAccessTokenFor(User::factory()->create()))
        ->getJson('/api/exam-bodies?search=zzzz')
        ->assertOk()
        ->assertJson(['exam_bodies' => []]);
});

test('a missing exam body returns 404', function () {
    $this->withToken(examBodiesAccessTokenFor(User::factory()->create()))
        ->getJson('/api/exam-bodies/999')
        ->assertNotFound();
});
