<?php

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tymon\JWTAuth\Facades\JWTAuth;

function rolesAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('the roles endpoints require an access token', function () {
    $this->getJson('/api/roles')->assertUnauthorized();
    $this->postJson('/api/roles', ['name' => 'registrar'])->assertUnauthorized();
});

test('the roles list is gated by roles.view', function () {
    $trainer = User::factory()->trainer()->create();

    $this->withToken(rolesAccessTokenFor($trainer))
        ->getJson('/api/roles')
        ->assertForbidden();
});

test('the roles list returns the seeded catalog in enum order with each roles permissions', function () {
    $response = $this->withToken(rolesAccessTokenFor(User::factory()->create()))
        ->getJson('/api/roles')
        ->assertOk()
        ->assertJsonStructure(['roles' => [['id', 'name', 'slug', 'description', 'locked', 'built_in', 'permissions']]])
        ->assertJsonCount(4, 'roles')
        ->assertJsonPath('roles.0.slug', 'admin')
        ->assertJsonPath('roles.0.name', 'Admin')
        ->assertJsonPath('roles.0.locked', true)
        ->assertJsonPath('roles.0.built_in', true)
        ->assertJsonPath('roles.1.slug', 'trainer')
        ->assertJsonPath('roles.2.slug', 'student')
        ->assertJsonPath('roles.3.slug', 'finance');

    $roles = collect($response->json('roles'))->keyBy('slug');

    // The admin bundle is every permission except the student portal,
    // which renders the signed-in account's own student data.
    $adminPermissions = collect(Permission::cases())
        ->map->value
        ->reject(fn (string $permission): bool => $permission === 'student-portal')
        ->sort()
        ->values()
        ->all();

    expect($roles['admin']['permissions'])
        ->toBe($adminPermissions)
        ->and($roles['trainer']['permissions'])
        ->toBe(['unit-grades.update', 'unit-grades.view'])
        ->and($roles['student']['permissions'])
        ->toBe(['student-portal'])
        ->and($roles['finance']['permissions'])
        ->toBe([
            'course-fee-assignments.create',
            'course-fee-assignments.delete',
            'course-fee-assignments.update',
            'course-fee-assignments.view',
            'fee-structures.create',
            'fee-structures.delete',
            'fee-structures.update',
            'fee-structures.view',
        ]);
});

test('the response carries the permission catalog grouped by resource', function () {
    $response = $this->withToken(rolesAccessTokenFor(User::factory()->create()))
        ->getJson('/api/roles')
        ->assertOk();

    $groups = collect($response->json('catalog'))->keyBy('name');

    expect($groups->count())->toBe(count(collect(Permission::cases())->map->resource()->unique()))
        ->and($groups['students']['label'])->toBe('Students')
        ->and(collect($groups['students']['permissions'])->pluck('name')->all())
        ->toBe(['students.view', 'students.create', 'students.update', 'students.delete'])
        ->and(collect($groups['activities']['permissions'])->pluck('name')->all())
        ->toBe(['activities.view'])
        ->and(collect($groups['student-portal']['permissions'])->pluck('name')->all())
        ->toBe(['student-portal']);
});

test('the roles list supports search, sorting and pagination', function () {
    $token = rolesAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->getJson('/api/roles?search=finan')
        ->assertOk()
        ->assertJsonCount(1, 'roles')
        ->assertJsonPath('roles.0.slug', 'finance');

    $sorted = $this->withToken($token)
        ->getJson('/api/roles?sort=name&order=desc')
        ->assertOk();

    expect(collect($sorted->json('roles'))->pluck('name')->all())
        ->toBe(['Trainer', 'Student', 'Finance', 'Admin']);

    $this->withToken($token)
        ->getJson('/api/roles?per_page=2&page=2')
        ->assertOk()
        ->assertJsonCount(2, 'roles')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.total', 4);
});

test('the catalog is mirrored into the permission tables for assignment', function () {
    expect(Schema::hasTable('roles'))->toBeTrue()
        ->and(Schema::hasTable('permissions'))->toBeTrue()
        ->and(DB::table('roles')->orderBy('id')->pluck('name')->all())
        ->toBe(['admin', 'trainer', 'student', 'finance'])
        ->and(DB::table('permissions')->count())
        ->toBe(count(Permission::cases()));
});

test('a custom role can be created without permissions', function () {
    $this->withToken(rolesAccessTokenFor(User::factory()->create()))
        ->postJson('/api/roles', ['name' => 'registrar'])
        ->assertCreated()
        ->assertJsonPath('role.slug', 'registrar')
        ->assertJsonPath('role.name', 'Registrar')
        ->assertJsonPath('role.description', null)
        ->assertJsonPath('role.locked', false)
        ->assertJsonPath('role.built_in', false)
        ->assertJsonPath('role.permissions', []);

    // Custom roles follow the seeded catalog in the list.
    $this->withToken(rolesAccessTokenFor(User::factory()->create()))
        ->getJson('/api/roles')
        ->assertOk()
        ->assertJsonCount(5, 'roles')
        ->assertJsonPath('roles.4.slug', 'registrar');
});

test('role names must be unique slugs that do not squat the built-ins', function () {
    $token = rolesAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->postJson('/api/roles', ['name' => 'registrar'])
        ->assertCreated();

    $this->withToken($token)
        ->postJson('/api/roles', ['name' => 'registrar'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    $this->withToken($token)
        ->postJson('/api/roles', ['name' => 'trainer'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    $this->withToken($token)
        ->postJson('/api/roles', ['name' => 'Super Admin'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('creating a role requires roles.create', function () {
    $finance = User::factory()->finance()->create();

    $this->withToken(rolesAccessTokenFor($finance))
        ->postJson('/api/roles', ['name' => 'registrar'])
        ->assertForbidden();
});

test('a custom role can be renamed and keeps its permissions', function () {
    $token = rolesAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->postJson('/api/roles', ['name' => 'registrar'])
        ->assertCreated();

    $this->withToken($token)
        ->putJson('/api/roles/registrar/permissions', ['permissions' => ['students.view']])
        ->assertOk();

    $this->withToken($token)
        ->putJson('/api/roles/registrar', ['name' => 'registrar-general'])
        ->assertOk()
        ->assertJsonPath('role.slug', 'registrar-general')
        ->assertJsonPath('role.permissions', ['students.view']);
});

test('built-in roles keep their names and the admin role is locked', function () {
    $token = rolesAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->putJson('/api/roles/trainer', ['name' => 'teacher'])
        ->assertForbidden();

    $this->withToken($token)
        ->putJson('/api/roles/admin', ['name' => 'root'])
        ->assertForbidden();
});

test('renaming a role requires roles.update', function () {
    $token = rolesAccessTokenFor(User::factory()->finance()->create());

    $this->withToken(rolesAccessTokenFor(User::factory()->create()))
        ->postJson('/api/roles', ['name' => 'registrar'])
        ->assertCreated();

    $this->withToken($token)
        ->putJson('/api/roles/registrar', ['name' => 'secretary'])
        ->assertForbidden();
});

test('a roles permissions can be replaced through the sync endpoint', function () {
    $token = rolesAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->postJson('/api/roles', ['name' => 'registrar'])
        ->assertCreated();

    $this->withToken($token)
        ->putJson('/api/roles/registrar/permissions', [
            'permissions' => ['students.view', 'students.create'],
        ])
        ->assertOk()
        ->assertJsonPath('role.permissions', ['students.create', 'students.view']);

    // The new bundle decides access: a holder may list and store, and
    // nothing more. A trainer's own bundle stays out of the way.
    $user = User::factory()->trainer()->create();
    $user->assignRole('registrar');
    $token = rolesAccessTokenFor($user);

    $this->withToken($token)->getJson('/api/students')->assertOk();
    $this->withToken($token)->postJson('/api/students', [])->assertUnprocessable();
    $this->withToken($token)->getJson('/api/departments')->assertForbidden();
});

test('the sync endpoint rejects unknown permission names', function () {
    $token = rolesAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->postJson('/api/roles', ['name' => 'registrar'])
        ->assertCreated();

    $this->withToken($token)
        ->putJson('/api/roles/registrar/permissions', ['permissions' => ['students.nuke']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permissions.0']);

    $this->withToken($token)
        ->putJson('/api/roles/registrar/permissions', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permissions']);
});

test('the admin roles permissions cannot be changed', function () {
    $this->withToken(rolesAccessTokenFor(User::factory()->create()))
        ->putJson('/api/roles/admin/permissions', ['permissions' => []])
        ->assertForbidden();
});

test('syncing a role requires roles.update', function () {
    $finance = User::factory()->finance()->create();

    $this->withToken(rolesAccessTokenFor($finance))
        ->putJson('/api/roles/trainer/permissions', ['permissions' => []])
        ->assertForbidden();
});

test('a custom role can be deleted and its users lose its access', function () {
    $token = rolesAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->postJson('/api/roles', ['name' => 'registrar'])
        ->assertCreated();

    $this->withToken($token)
        ->putJson('/api/roles/registrar/permissions', ['permissions' => ['students.view']])
        ->assertOk();

    $user = User::factory()->trainer()->create();
    $user->assignRole('registrar');

    $this->withToken($token)
        ->deleteJson('/api/roles/registrar')
        ->assertNoContent();

    $this->withToken($token)
        ->getJson('/api/roles')
        ->assertOk()
        ->assertJsonCount(4, 'roles');

    // The holder's access went with the role.
    $this->withToken(rolesAccessTokenFor($user))
        ->getJson('/api/students')
        ->assertForbidden();
});

test('the admin role cannot be deleted', function () {
    $this->withToken(rolesAccessTokenFor(User::factory()->create()))
        ->deleteJson('/api/roles/admin')
        ->assertForbidden();
});

test('deleting a role requires roles.delete', function () {
    $finance = User::factory()->finance()->create();

    $this->withToken(rolesAccessTokenFor($finance))
        ->deleteJson('/api/roles/trainer')
        ->assertForbidden();
});
