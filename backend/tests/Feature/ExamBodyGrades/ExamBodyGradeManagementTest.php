<?php

use App\Models\ExamBody;
use App\Models\ExamBodyGrade;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function examBodyGradesAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('exam body grade endpoints require an access token', function () {
    $this->getJson('/api/exam-body-grades')->assertUnauthorized();
    $this->postJson('/api/exam-body-grades', ['grade' => 'A'])
        ->assertUnauthorized();
});

test('grade bands can be listed grouped by exam body and sequence', function () {
    $knec = ExamBody::factory()->create(['name' => 'Kenya National Examinations Council']);

    ExamBodyGrade::factory()->for($knec, 'examBody')->create(['grade' => 'B', 'sequence' => 2]);
    ExamBodyGrade::factory()->for($knec, 'examBody')->create(['grade' => 'A', 'sequence' => 1]);

    $this->withToken(examBodyGradesAccessTokenFor(User::factory()->create()))
        ->getJson('/api/exam-body-grades')
        ->assertOk()
        ->assertJsonStructure([
            'exam_body_grades' => [[
                'id', 'exam_body_id', 'exam_body_name', 'grade', 'grade_start',
                'grade_end', 'remark', 'sequence', 'is_active', 'created_by', 'updated_by',
            ]],
        ])
        ->assertJsonPath('exam_body_grades.0.grade', 'A')
        ->assertJsonPath('exam_body_grades.0.exam_body_name', 'Kenya National Examinations Council')
        ->assertJsonPath('exam_body_grades.1.grade', 'B');
});

test('the grade list supports exam body and status filters, sorting and pagination', function () {
    $knec = ExamBody::factory()->create();
    $zwa = ExamBody::factory()->create();

    ExamBodyGrade::factory()->for($knec, 'examBody')->create(['grade' => 'A', 'sequence' => 1, 'is_active' => true]);
    ExamBodyGrade::factory()->for($zwa, 'examBody')->create(['grade' => 'F', 'sequence' => 9, 'is_active' => false]);

    $token = examBodyGradesAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->getJson('/api/exam-body-grades?exam_body_id='.$knec->id)
        ->assertOk()
        ->assertJsonCount(1, 'exam_body_grades')
        ->assertJsonPath('exam_body_grades.0.grade', 'A');

    $this->withToken($token)
        ->getJson('/api/exam-body-grades?is_active=0')
        ->assertOk()
        ->assertJsonCount(1, 'exam_body_grades')
        ->assertJsonPath('exam_body_grades.0.grade', 'F');

    $sorted = $this->withToken($token)
        ->getJson('/api/exam-body-grades?sort=grade&order=desc')
        ->assertOk();

    expect(collect($sorted->json('exam_body_grades'))->pluck('grade')->all())
        ->toBe(['F', 'A'])
        ->and($sorted->json('meta.total'))->toBe(2);
});

test('a single grade band can be shown', function () {
    $knec = ExamBody::factory()->create();
    $grade = ExamBodyGrade::factory()->for($knec, 'examBody')->create(['grade' => 'A']);

    $this->withToken(examBodyGradesAccessTokenFor(User::factory()->create()))
        ->getJson('/api/exam-body-grades/'.$grade->id)
        ->assertOk()
        ->assertJsonPath('exam_body_grade.id', $grade->id)
        ->assertJsonPath('exam_body_grade.grade', 'A')
        ->assertJsonPath('exam_body_grade.exam_body_name', $knec->name);
});

test('a grade band can be created with a score range and audit stamps', function () {
    $knec = ExamBody::factory()->create();
    $creator = User::factory()->create();

    $this->withToken(examBodyGradesAccessTokenFor($creator))
        ->postJson('/api/exam-body-grades', [
            'exam_body_id' => $knec->id,
            'grade' => 'A',
            'grade_start' => 70,
            'grade_end' => 100,
            'remark' => 'Distinction',
            'sequence' => 1,
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('exam_body_grade.exam_body_id', $knec->id)
        ->assertJsonPath('exam_body_grade.exam_body_name', $knec->name)
        ->assertJsonPath('exam_body_grade.grade', 'A')
        ->assertJsonPath('exam_body_grade.grade_start', '70.00')
        ->assertJsonPath('exam_body_grade.grade_end', '100.00')
        ->assertJsonPath('exam_body_grade.remark', 'Distinction')
        ->assertJsonPath('exam_body_grade.created_by', $creator->id)
        ->assertJsonPath('exam_body_grade.updated_by', $creator->id);

    $this->assertDatabaseHas('exam_body_grades', [
        'exam_body_id' => $knec->id,
        'grade' => 'A',
        'created_by' => $creator->id,
    ]);
});

test('the same grade name may be reused across different exam bodies', function () {
    $knec = ExamBody::factory()->create();
    $zwa = ExamBody::factory()->create();

    ExamBodyGrade::factory()->for($knec, 'examBody')->create(['grade' => 'A']);

    $this->withToken(examBodyGradesAccessTokenFor(User::factory()->create()))
        ->postJson('/api/exam-body-grades', [
            'exam_body_id' => $zwa->id,
            'grade' => 'A',
            'grade_start' => 70,
            'grade_end' => 100,
        ])
        ->assertCreated();
});

test('a duplicate grade name within the same exam body is rejected', function () {
    $knec = ExamBody::factory()->create();

    ExamBodyGrade::factory()->for($knec, 'examBody')->create(['grade' => 'A']);

    $this->withToken(examBodyGradesAccessTokenFor(User::factory()->create()))
        ->postJson('/api/exam-body-grades', [
            'exam_body_id' => $knec->id,
            'grade' => 'A',
            'grade_start' => 65,
            'grade_end' => 100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['grade']);
});

test('the score range must be within bounds and not inverted', function () {
    $knec = ExamBody::factory()->create();

    $token = examBodyGradesAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->postJson('/api/exam-body-grades', [
            'exam_body_id' => $knec->id,
            'grade' => 'A',
            'grade_start' => 80,
            'grade_end' => 70,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['grade_end']);

    $this->withToken($token)
        ->postJson('/api/exam-body-grades', [
            'exam_body_id' => $knec->id,
            'grade' => 'B',
            'grade_start' => 0,
            'grade_end' => 120,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['grade_end']);
});

test('a grade band requires an existing exam body', function () {
    $this->withToken(examBodyGradesAccessTokenFor(User::factory()->create()))
        ->postJson('/api/exam-body-grades', [
            'exam_body_id' => 999999,
            'grade' => 'A',
            'grade_start' => 70,
            'grade_end' => 100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['exam_body_id']);
});

test('a grade band can be updated', function () {
    $knec = ExamBody::factory()->create();
    $grade = ExamBodyGrade::factory()->for($knec, 'examBody')->create(['grade' => 'A']);
    $updater = User::factory()->create();

    $this->withToken(examBodyGradesAccessTokenFor($updater))
        ->putJson('/api/exam-body-grades/'.$grade->id, [
            'exam_body_id' => $knec->id,
            'grade' => 'A*',
            'grade_start' => 80,
            'grade_end' => 100,
            'remark' => 'Top band',
            'sequence' => 1,
            'is_active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('exam_body_grade.grade', 'A*')
        ->assertJsonPath('exam_body_grade.grade_start', '80.00')
        ->assertJsonPath('exam_body_grade.updated_by', $updater->id);
});

test('a grade band can be soft deleted, freeing its name for reuse', function () {
    $knec = ExamBody::factory()->create();
    $grade = ExamBodyGrade::factory()->for($knec, 'examBody')->create(['grade' => 'A']);

    $token = examBodyGradesAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->deleteJson('/api/exam-body-grades/'.$grade->id)
        ->assertNoContent();

    $this->assertSoftDeleted('exam_body_grades', ['id' => $grade->id]);

    $this->withToken($token)
        ->getJson('/api/exam-body-grades')
        ->assertOk()
        ->assertJsonCount(0, 'exam_body_grades');

    $this->withToken($token)
        ->postJson('/api/exam-body-grades', [
            'exam_body_id' => $knec->id,
            'grade' => 'A',
            'grade_start' => 70,
            'grade_end' => 100,
        ])
        ->assertCreated();
});
