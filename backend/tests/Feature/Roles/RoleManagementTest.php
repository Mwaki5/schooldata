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

test('the roles endpoint requires an access token', function () {
    $this->getJson('/api/roles')->assertUnauthorized();
});

test('the roles endpoint is restricted to admins', function () {
    $trainer = User::factory()->trainer()->create();

    $this->withToken(rolesAccessTokenFor($trainer))
        ->getJson('/api/roles')
        ->assertForbidden();
});

test('the roles endpoint returns the catalog in enum order with each roles permissions', function () {
    $response = $this->withToken(rolesAccessTokenFor(User::factory()->create()))
        ->getJson('/api/roles')
        ->assertOk()
        ->assertJsonStructure(['roles' => [['id', 'name', 'slug', 'description', 'permissions']]])
        ->assertJsonCount(4, 'roles')
        ->assertJsonPath('roles.0.slug', 'admin')
        ->assertJsonPath('roles.0.name', 'Admin')
        ->assertJsonPath('roles.1.slug', 'trainer')
        ->assertJsonPath('roles.1.name', 'Trainer')
        ->assertJsonPath('roles.2.slug', 'student')
        ->assertJsonPath('roles.2.name', 'Student')
        ->assertJsonPath('roles.3.slug', 'finance')
        ->assertJsonPath('roles.3.name', 'Finance');

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
        ->toBe(['unit-grades.manage', 'unit-grades.view'])
        ->and($roles['student']['permissions'])
        ->toBe(['student-portal'])
        ->and($roles['finance']['permissions'])
        ->toBe([
            'course-fee-assignments.manage',
            'course-fee-assignments.view',
            'fee-structures.manage',
            'fee-structures.view',
        ]);
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

test('roles cannot be created through the api', function () {
    $admin = User::factory()->create();

    $this->withToken(rolesAccessTokenFor($admin))
        ->postJson('/api/roles', ['name' => 'Super Admin'])
        ->assertStatus(405);
});

test('roles cannot be updated through the api', function () {
    $admin = User::factory()->create();

    $this->withToken(rolesAccessTokenFor($admin))
        ->putJson('/api/roles/1', ['name' => 'Root'])
        ->assertNotFound();
});

test('roles cannot be deleted through the api', function () {
    $admin = User::factory()->create();

    $this->withToken(rolesAccessTokenFor($admin))
        ->deleteJson('/api/roles/1')
        ->assertNotFound();
});
