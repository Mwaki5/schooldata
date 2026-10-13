<?php

use App\Models\User;
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
    $trainer = User::factory()->create(['role' => 'trainer']);

    $this->withToken(rolesAccessTokenFor($trainer))
        ->getJson('/api/roles')
        ->assertForbidden();
});

test('the roles endpoint returns the hardcoded catalog in enum order', function () {
    $this->withToken(rolesAccessTokenFor(User::factory()->create()))
        ->getJson('/api/roles')
        ->assertOk()
        ->assertJsonStructure(['roles' => [['id', 'name', 'slug', 'description']]])
        ->assertJsonCount(4, 'roles')
        ->assertJsonPath('roles.0.slug', 'admin')
        ->assertJsonPath('roles.0.name', 'Admin')
        ->assertJsonPath('roles.1.slug', 'trainer')
        ->assertJsonPath('roles.1.name', 'Trainer')
        ->assertJsonPath('roles.2.slug', 'student')
        ->assertJsonPath('roles.2.name', 'Student')
        ->assertJsonPath('roles.3.slug', 'finance')
        ->assertJsonPath('roles.3.name', 'Finance');
});

test('roles cannot be tampered with because they no longer live in the database', function () {
    expect(Schema::hasTable('roles'))->toBeFalse();

    // The catalog still renders even though the table is gone.
    $this->withToken(rolesAccessTokenFor(User::factory()->create()))
        ->getJson('/api/roles')
        ->assertOk()
        ->assertJsonCount(4, 'roles');
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
