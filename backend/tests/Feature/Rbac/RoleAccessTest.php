<?php

use App\Models\CertificationLevel;
use App\Models\Department;
use App\Models\ExamBody;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tymon\JWTAuth\Facades\JWTAuth;

function rbacAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('non-admin roles are forbidden from every management endpoint', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $token = rbacAccessTokenFor($user);

    $department = Department::factory()->create();
    $examBody = ExamBody::factory()->create();
    $certificationLevel = CertificationLevel::factory()->create();
    $staffMember = Staff::factory()->create();

    $this->withToken($token)->getJson('/api/roles')->assertForbidden();
    $this->withToken($token)->getJson('/api/users')->assertForbidden();
    $this->withToken($token)->getJson('/api/departments')->assertForbidden();
    $this->withToken($token)->getJson('/api/exam-bodies')->assertForbidden();
    $this->withToken($token)->getJson('/api/certification-levels')->assertForbidden();
    $this->withToken($token)->getJson('/api/staff')->assertForbidden();
    $this->withToken($token)->postJson('/api/departments', ['name' => 'Intrusion'])->assertForbidden();
    $this->withToken($token)->putJson('/api/exam-bodies/'.$examBody->id, ['name' => 'Intrusion'])->assertForbidden();
    $this->withToken($token)->deleteJson('/api/departments/'.$department->id)->assertForbidden();
    $this->withToken($token)->deleteJson('/api/certification-levels/'.$certificationLevel->id)->assertForbidden();
    $this->withToken($token)->postJson('/api/staff', ['employment_no' => 'EMP-9999', 'name' => 'Intruder'])->assertForbidden();
    $this->withToken($token)->deleteJson('/api/staff/'.$staffMember->id)->assertForbidden();
})->with(['trainer', 'student', 'finance']);

test('an admin can access every management endpoint', function () {
    $token = rbacAccessTokenFor(User::factory()->create());

    $this->withToken($token)->getJson('/api/roles')->assertOk();
    $this->withToken($token)->getJson('/api/users')->assertOk();
    $this->withToken($token)->getJson('/api/departments')->assertOk();
    $this->withToken($token)->getJson('/api/exam-bodies')->assertOk();
    $this->withToken($token)->getJson('/api/certification-levels')->assertOk();
    $this->withToken($token)->getJson('/api/staff')->assertOk();
});

test('management endpoints still require an access token', function () {
    $this->getJson('/api/roles')->assertUnauthorized();
    $this->getJson('/api/users')->assertUnauthorized();
    $this->getJson('/api/departments')->assertUnauthorized();
    $this->getJson('/api/exam-bodies')->assertUnauthorized();
    $this->getJson('/api/certification-levels')->assertUnauthorized();
    $this->getJson('/api/staff')->assertUnauthorized();
});

test('a route accepts one or many roles in a single middleware tag', function (string $roleList, string $role, int $expectedStatus) {
    Route::get('/api/_role-probe', fn () => response()->json(['ok' => true]))
        ->middleware(['auth:api', 'access.type', 'role:'.$roleList]);

    $user = User::factory()->create(['role' => $role]);

    // One token per test case: the auth guard caches the resolved user
    // for the lifetime of the app instance, so cases must not share one.
    $this->withToken(rbacAccessTokenFor($user))
        ->getJson('/api/_role-probe')
        ->assertStatus($expectedStatus);
})->with([
    'single role: admin allowed' => ['admin', 'admin', 200],
    'single role: trainer blocked' => ['admin', 'trainer', 403],
    'two roles: admin allowed' => ['admin,finance', 'admin', 200],
    'two roles: finance allowed' => ['admin,finance', 'finance', 200],
    'two roles: trainer blocked' => ['admin,finance', 'trainer', 403],
    'two roles: student blocked' => ['admin,finance', 'student', 403],
    'two roles reversed: finance allowed' => ['finance,admin', 'finance', 200],
    'three roles: trainer allowed' => ['admin,trainer,finance', 'trainer', 200],
    'three roles: student blocked' => ['admin,trainer,finance', 'student', 403],
    'all four roles: student allowed' => ['admin,trainer,student,finance', 'student', 200],
    'unknown role in the tag fails closed for everyone' => ['bogus', 'admin', 403],
]);

test('the authenticated user profile includes their role', function () {
    $user = User::factory()->create(['role' => 'finance']);

    $this->withToken(rbacAccessTokenFor($user))
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('user.role', 'finance');
});

test('new registrations default to the student role', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Fresh Student',
        'email' => 'fresh.student@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()->assertJsonPath('user.role', 'student');

    $this->assertDatabaseHas('users', [
        'email' => 'fresh.student@example.com',
        'role' => 'student',
    ]);
});

test('a registration request cannot choose its own role', function () {
    $this->postJson('/api/register', [
        'name' => 'Sneaky User',
        'email' => 'sneaky@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'admin',
    ])->assertCreated();

    $this->assertDatabaseHas('users', [
        'email' => 'sneaky@example.com',
        'role' => 'student',
    ]);
});
