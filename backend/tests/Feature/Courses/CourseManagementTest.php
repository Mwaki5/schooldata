<?php

use App\Models\CertificationLevel;
use App\Models\Course;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function coursesAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('course endpoints require an access token', function () {
    $this->getJson('/api/courses')->assertUnauthorized();
    $this->postJson('/api/courses', ['name' => 'Diploma in Computing'])
        ->assertUnauthorized();
});

test('courses are listed ordered by name', function () {
    Course::factory()->create(['name' => 'Zulu Studies', 'code' => 'ZULU']);
    Course::factory()->create(['name' => 'Accounting', 'code' => 'ACCT']);

    $this->withToken(coursesAccessTokenFor(User::factory()->create()))
        ->getJson('/api/courses')
        ->assertOk()
        ->assertJsonStructure([
            'courses' => [[
                'id', 'code', 'initials', 'name', 'duration_months',
                'description', 'is_active', 'certification_level_id',
                'certification_level_name', 'department_id', 'department_name',
                'created_by', 'updated_by',
            ]],
        ])
        ->assertJsonPath('courses.0.name', 'Accounting')
        ->assertJsonPath('courses.1.name', 'Zulu Studies');
});

test('the course list supports filters, sorting and validates sort keys', function () {
    $level = CertificationLevel::factory()->create();

    Course::factory()->create([
        'name' => 'Accounting',
        'code' => 'ACCT',
        'certification_level_id' => $level->id,
        'is_active' => true,
    ]);
    Course::factory()->create([
        'name' => 'Zulu Studies',
        'code' => 'ZULU',
        'certification_level_id' => null,
        'is_active' => false,
    ]);

    $token = coursesAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->getJson('/api/courses?certification_level_id='.$level->id)
        ->assertOk()
        ->assertJsonCount(1, 'courses')
        ->assertJsonPath('courses.0.name', 'Accounting');

    $this->withToken($token)
        ->getJson('/api/courses?is_active=0')
        ->assertOk()
        ->assertJsonCount(1, 'courses')
        ->assertJsonPath('courses.0.name', 'Zulu Studies');

    $sorted = $this->withToken($token)
        ->getJson('/api/courses?sort=code&order=desc')
        ->assertOk();

    expect(collect($sorted->json('courses'))->pluck('code')->all())
        ->toBe(['ZULU', 'ACCT'])
        ->and($sorted->json('meta.total'))->toBe(2);

    $this->withToken($token)->getJson('/api/courses?sort=password')->assertUnprocessable();
});

test('a course can be created with audit stamps', function () {
    $creator = User::factory()->create();

    $response = $this->withToken(coursesAccessTokenFor($creator))
        ->postJson('/api/courses', [
            'code' => 'ND-IT',
            'initials' => 'IT',
            'name' => 'National Diploma in IT',
            'duration_months' => 24,
            'description' => 'A two year diploma.',
            'is_active' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('course.code', 'ND-IT')
        ->assertJsonPath('course.initials', 'IT')
        ->assertJsonPath('course.name', 'National Diploma in IT')
        ->assertJsonPath('course.duration_months', 24)
        ->assertJsonPath('course.is_active', true)
        ->assertJsonPath('course.created_by', $creator->id)
        ->assertJsonPath('course.updated_by', $creator->id);

    $this->assertDatabaseHas('courses', [
        'code' => 'ND-IT',
        'name' => 'National Diploma in IT',
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);
});

test('a course defaults to active and tolerates null optional fields', function () {
    $user = User::factory()->create();

    $this->withToken(coursesAccessTokenFor($user))
        ->postJson('/api/courses', [
            'code' => 'CERT-1',
            'initials' => 'C1',
            'name' => 'Short Course',
        ])
        ->assertCreated()
        ->assertJsonPath('course.is_active', true)
        ->assertJsonPath('course.duration_months', null)
        ->assertJsonPath('course.description', null)
        ->assertJsonPath('course.certification_level_id', null)
        ->assertJsonPath('course.department_id', null);
});

test('creating a course requires its core fields', function () {
    $this->withToken(coursesAccessTokenFor(User::factory()->create()))
        ->postJson('/api/courses', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code', 'initials', 'name']);
});

test('code and name must each be unique across all courses', function () {
    $user = User::factory()->create();
    Course::factory()->create(['code' => 'ND-IT', 'name' => 'National Diploma in IT']);

    $this->withToken(coursesAccessTokenFor($user))
        ->postJson('/api/courses', [
            'code' => 'ND-IT',
            'initials' => 'XX',
            'name' => 'A Different Name',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);

    $this->withToken(coursesAccessTokenFor($user))
        ->postJson('/api/courses', [
            'code' => 'OTHER-1',
            'initials' => 'XX',
            'name' => 'National Diploma in IT',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('the certification level and department must exist', function () {
    $user = User::factory()->create();

    $this->withToken(coursesAccessTokenFor($user))
        ->postJson('/api/courses', [
            'code' => 'X1',
            'initials' => 'X1',
            'name' => 'Course X',
            'certification_level_id' => 999999,
            'department_id' => 999999,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['certification_level_id', 'department_id']);
});

test('a soft deleted certification level cannot be linked', function () {
    $user = User::factory()->create();
    $level = CertificationLevel::factory()->create();
    $level->delete();

    $this->withToken(coursesAccessTokenFor($user))
        ->postJson('/api/courses', [
            'code' => 'X2',
            'initials' => 'X2',
            'name' => 'Course X2',
            'certification_level_id' => $level->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['certification_level_id']);
});

test('duration months must be a non negative integer', function () {
    $user = User::factory()->create();

    $this->withToken(coursesAccessTokenFor($user))
        ->postJson('/api/courses', [
            'code' => 'X3',
            'initials' => 'X3',
            'name' => 'Course X3',
            'duration_months' => 'two years',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['duration_months']);

    $this->withToken(coursesAccessTokenFor($user))
        ->postJson('/api/courses', [
            'code' => 'X4',
            'initials' => 'X4',
            'name' => 'Course X4',
            'duration_months' => -1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['duration_months']);
});

test('a course can be updated and stamps the updating user', function () {
    $creator = User::factory()->create();
    $updater = User::factory()->create();
    $course = Course::factory()->create([
        'is_active' => true,
        'created_by' => $creator->id,
    ]);

    $this->withToken(coursesAccessTokenFor($updater))
        ->putJson('/api/courses/'.$course->id, [
            'code' => $course->code,
            'initials' => 'BD',
            'name' => 'Bachelor of Data Science',
            'duration_months' => 36,
            'description' => 'Revised programme.',
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('course.name', 'Bachelor of Data Science')
        ->assertJsonPath('course.initials', 'BD')
        ->assertJsonPath('course.duration_months', 36)
        ->assertJsonPath('course.is_active', false)
        ->assertJsonPath('course.created_by', $creator->id)
        ->assertJsonPath('course.updated_by', $updater->id);

    $this->assertDatabaseHas('courses', [
        'id' => $course->id,
        'name' => 'Bachelor of Data Science',
        'is_active' => 0,
        'created_by' => $creator->id,
        'updated_by' => $updater->id,
    ]);
});

test('updating a course validates code and name excluding itself', function () {
    $course = Course::factory()->create(['code' => 'ND-IT', 'name' => 'National Diploma in IT']);
    Course::factory()->create(['code' => 'ND-CS', 'name' => 'National Diploma in CS']);

    $this->withToken(coursesAccessTokenFor(User::factory()->create()))
        ->putJson('/api/courses/'.$course->id, [
            'code' => 'ND-CS',
            'initials' => 'IT',
            'name' => 'Another Name',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);

    $this->withToken(coursesAccessTokenFor(User::factory()->create()))
        ->putJson('/api/courses/'.$course->id, [
            'code' => 'ND-IT',
            'initials' => 'IT',
            'name' => 'National Diploma in IT',
        ])
        ->assertOk();
});

test('a course is soft deleted and disappears from the api', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create();

    $this->withToken(coursesAccessTokenFor($user))
        ->deleteJson('/api/courses/'.$course->id)
        ->assertNoContent();

    $this->assertSoftDeleted('courses', ['id' => $course->id]);

    $this->withToken(coursesAccessTokenFor($user))
        ->getJson('/api/courses/'.$course->id)
        ->assertNotFound();

    $this->withToken(coursesAccessTokenFor($user))
        ->getJson('/api/courses')
        ->assertOk()
        ->assertJsonMissing(['id' => $course->id]);
});

test('a soft deleted course frees its code and name for reuse', function () {
    $user = User::factory()->create();
    Course::factory()->create(['code' => 'ND-IT', 'name' => 'National Diploma in IT'])
        ->delete();

    $this->withToken(coursesAccessTokenFor($user))
        ->postJson('/api/courses', [
            'code' => 'ND-IT',
            'initials' => 'IT',
            'name' => 'National Diploma in IT',
        ])
        ->assertCreated()
        ->assertJsonPath('course.code', 'ND-IT');
});

test('the course list can be searched by name or code', function () {
    Course::factory()->create(['name' => 'Accounting', 'code' => 'ACCT']);
    Course::factory()->create(['name' => 'Biology', 'code' => 'BIO']);

    $this->withToken(coursesAccessTokenFor(User::factory()->create()))
        ->getJson('/api/courses?search=biol')
        ->assertOk()
        ->assertJsonCount(1, 'courses')
        ->assertJsonPath('courses.0.name', 'Biology');

    $this->withToken(coursesAccessTokenFor(User::factory()->create()))
        ->getJson('/api/courses?search=ACCT')
        ->assertOk()
        ->assertJsonCount(1, 'courses')
        ->assertJsonPath('courses.0.code', 'ACCT');
});

test('a missing course returns 404', function () {
    $this->withToken(coursesAccessTokenFor(User::factory()->create()))
        ->getJson('/api/courses/999')
        ->assertNotFound();
});
