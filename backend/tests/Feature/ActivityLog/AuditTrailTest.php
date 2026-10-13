<?php

use App\Models\CertificationLevel;
use App\Models\Department;
use App\Models\ExamBody;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Tymon\JWTAuth\Facades\JWTAuth;

function auditAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('creating a department records an activity attributed to the authenticated user', function () {
    $admin = User::factory()->create();

    $departmentId = $this->withHeaders(['User-Agent' => 'ForensicAgent/1.0'])
        ->withToken(auditAccessTokenFor($admin))
        ->postJson('/api/departments', ['name' => 'Audited Department'])
        ->assertCreated()
        ->json('department.id');

    $activity = Activity::query()
        ->where('event', 'created')
        ->where('subject_type', Department::class)
        ->latest('id')
        ->first();

    expect($activity)
        ->not->toBeNull()
        ->and($activity->log_name)->toBe('default')
        ->and($activity->description)->toBe('created')
        ->and($activity->causer_type)->toBe(User::class)
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->subject_id)->toBe($departmentId)
        ->and($activity->properties['attributes']['name'])->toBe('Audited Department')
        ->and($activity->properties['ip'])->toBe('127.0.0.1')
        ->and($activity->properties['user_agent'])->toBe('ForensicAgent/1.0');
});

test('updating a department records the old and new values', function () {
    $admin = User::factory()->create();
    $department = Department::factory()->create(['name' => 'Old Name']);

    $this->withToken(auditAccessTokenFor($admin))
        ->putJson('/api/departments/'.$department->id, ['name' => 'New Name'])
        ->assertOk();

    $activity = Activity::query()
        ->where('event', 'updated')
        ->where('subject_type', Department::class)
        ->latest('id')
        ->first();

    expect($activity)
        ->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->subject_id)->toBe($department->id)
        ->and($activity->properties['old']['name'])->toBe('Old Name')
        ->and($activity->properties['attributes']['name'])->toBe('New Name');
});

test('deleting a department records the deletion with its last values', function () {
    $admin = User::factory()->create();
    $department = Department::factory()->create(['name' => 'Doomed Department']);

    $this->withToken(auditAccessTokenFor($admin))
        ->deleteJson('/api/departments/'.$department->id)
        ->assertNoContent();

    $activity = Activity::query()
        ->where('event', 'deleted')
        ->where('subject_type', Department::class)
        ->latest('id')
        ->first();

    expect($activity)
        ->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->subject_id)->toBe($department->id)
        ->and($activity->properties['old']['name'])->toBe('Doomed Department')
        ->and($activity->properties)->not->toHaveKey('attributes');
});

test('updating a department without real changes records no activity', function () {
    $admin = User::factory()->create();
    $department = Department::factory()->create(['name' => 'Stable Department']);

    $this->withToken(auditAccessTokenFor($admin))
        ->putJson('/api/departments/'.$department->id, ['name' => 'Stable Department'])
        ->assertOk();

    expect(
        Activity::query()
            ->where('event', 'updated')
            ->where('subject_type', Department::class)
            ->exists()
    )->toBeFalse();
});

test('exam bodies and certification levels record creation activity', function () {
    $admin = User::factory()->create();
    $token = auditAccessTokenFor($admin);

    $examBodyId = $this->withToken($token)
        ->postJson('/api/exam-bodies', ['name' => 'Logged Body', 'code' => 'LGB'])
        ->assertCreated()
        ->json('exam_body.id');

    $certificationLevelId = $this->withToken($token)
        ->postJson('/api/certification-levels', [
            'exam_body_id' => $examBodyId,
            'name' => 'Logged Level',
            'code' => 'LGL',
            'description' => 'An audited level.',
            'sequence' => 1,
        ])
        ->assertCreated()
        ->json('certification_level.id');

    $examBodyActivity = Activity::query()
        ->where('event', 'created')
        ->where('subject_type', ExamBody::class)
        ->latest('id')
        ->first();

    $certificationLevelActivity = Activity::query()
        ->where('event', 'created')
        ->where('subject_type', CertificationLevel::class)
        ->latest('id')
        ->first();

    expect($examBodyActivity->causer_id)->toBe($admin->id)
        ->and($examBodyActivity->subject_id)->toBe($examBodyId)
        ->and($examBodyActivity->properties['attributes']['name'])->toBe('Logged Body')
        ->and($certificationLevelActivity->causer_id)->toBe($admin->id)
        ->and($certificationLevelActivity->subject_id)->toBe($certificationLevelId)
        ->and($certificationLevelActivity->properties['attributes']['name'])->toBe('Logged Level');
});

test('logging in records an activity attributed to the user', function () {
    $user = User::factory()->create();

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    $activity = Activity::query()
        ->where('log_name', 'auth')
        ->latest('id')
        ->first();

    expect($activity)
        ->not->toBeNull()
        ->and($activity->event)->toBe('login')
        ->and($activity->description)->toBe('Logged in')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties['ip'])->toBe('127.0.0.1');
});

test('logging out records an activity attributed to the user', function () {
    $user = User::factory()->create();

    $this->withToken(auditAccessTokenFor($user))
        ->postJson('/api/logout')
        ->assertNoContent();

    $activity = Activity::query()
        ->where('log_name', 'auth')
        ->latest('id')
        ->first();

    expect($activity)
        ->not->toBeNull()
        ->and($activity->event)->toBe('logout')
        ->and($activity->description)->toBe('Logged out')
        ->and($activity->causer_id)->toBe($user->id);
});

test('registering records an activity attributed to the new user', function () {
    $this->postJson('/api/register', [
        'name' => 'Fresh Student',
        'email' => 'fresh@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $user = User::query()->where('email', 'fresh@example.com')->firstOrFail();

    $activity = Activity::query()
        ->where('log_name', 'auth')
        ->latest('id')
        ->first();

    expect($activity)
        ->not->toBeNull()
        ->and($activity->event)->toBe('register')
        ->and($activity->description)->toBe('Registered')
        ->and($activity->causer_id)->toBe($user->id);
});
