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

test('roles without management permissions are forbidden from every management endpoint', function (string $role) {
    $user = User::factory()->{$role}()->create();
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

test('a role holding every permission can access every management endpoint', function () {
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

test('a route accepts one or many permissions in a single middleware tag', function (string $permissionList, string $role, int $expectedStatus) {
    Route::get('/api/_permission-probe', fn () => response()->json(['ok' => true]))
        ->middleware(['auth:api', 'access.type', 'permission:'.$permissionList]);

    $user = User::factory()->{$role}()->create();

    // One token per test case: the auth guard caches the resolved user
    // for the lifetime of the app instance, so cases must not share one.
    $this->withToken(rbacAccessTokenFor($user))
        ->getJson('/api/_permission-probe')
        ->assertStatus($expectedStatus);
})->with([
    'single permission: admin holds it' => ['units.view', 'admin', 200],
    'single permission: trainer blocked' => ['units.view', 'trainer', 403],
    'two permissions: admin allowed' => ['units.view,fee-structures.view', 'admin', 200],
    'two permissions: finance allowed through fees' => ['units.view,fee-structures.view', 'finance', 200],
    'two permissions: trainer blocked' => ['units.view,fee-structures.view', 'trainer', 403],
    'unknown permission fails closed for everyone' => ['bogus', 'admin', 403],
]);

test('the authenticated user profile carries their roles and permissions', function () {
    $user = User::factory()->finance()->create();

    $this->withToken(rbacAccessTokenFor($user))
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('user.roles', ['finance'])
        ->assertJsonPath('user.permissions', [
            'course-fee-assignments.manage',
            'course-fee-assignments.view',
            'fee-structures.manage',
            'fee-structures.view',
        ]);
});

test('a user may hold many roles and their permissions add up', function () {
    $user = User::factory()->finance()->create();
    $user->assignRole('trainer');
    $user->load('roles');

    $token = rbacAccessTokenFor($user);

    // From finance: the fees modules open.
    $this->withToken($token)->getJson('/api/fee-structures')->assertOk();

    // From trainer: the grading console's endpoints are reachable — a
    // student without the permission is refused the same one.
    $reachable = $this->withToken($token)->getJson('/api/unit-grades/units');

    expect($reachable->status())->not->toBe(403);

    // Still nothing beyond the two bundles.
    $this->withToken($token)->getJson('/api/departments')->assertForbidden();

    expect($user->getRoleNames()->all())->toContain('finance', 'trainer');
});

test('a permission granted to the user alone decides access', function () {
    $user = User::factory()->finance()->create();
    $user->givePermissionTo('departments.view');

    $token = rbacAccessTokenFor($user);

    // The direct grant opens the read endpoint only; manage stays closed.
    $this->withToken($token)->getJson('/api/departments')->assertOk();
    $this->withToken($token)->postJson('/api/departments', ['name' => 'Intrusion'])->assertForbidden();
});

test('new registrations default to the student role', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Fresh Student',
        'email' => 'fresh.student@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()->assertJsonPath('user.roles', ['student']);

    $user = User::where('email', 'fresh.student@example.com')->firstOrFail();

    expect($user->load('roles')->hasRole('student'))->toBeTrue();
});

test('a registration request cannot choose its own access', function () {
    $this->postJson('/api/register', [
        'name' => 'Sneaky User',
        'email' => 'sneaky@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'admin',
        'roles' => ['admin'],
        'permissions' => ['users.manage'],
    ])->assertCreated();

    $user = User::where('email', 'sneaky@example.com')->firstOrFail();
    $user->load(['roles.permissions', 'permissions']);

    expect($user->getRoleNames()->all())->toBe(['student'])
        ->and($user->getAllPermissions()->pluck('name')->all())->toBe(['student-portal']);
});
