<?php

use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function usersAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('the user list requires an access token', function () {
    $this->getJson('/api/users')->assertUnauthorized();
});

test('users can be listed for reference pickers', function () {
    User::factory()->create(['name' => 'Zelda Zwane']);

    $this->withToken(usersAccessTokenFor(User::factory()->create()))
        ->getJson('/api/users')
        ->assertOk()
        ->assertJsonStructure(['users' => [['id', 'name', 'email']]]);
});

test('the user list is ordered by name', function () {
    User::factory()->create(['name' => 'Zoe Zed']);
    User::factory()->create(['name' => 'Adam Abebe']);

    $response = $this->withToken(usersAccessTokenFor(User::factory()->create()))
        ->getJson('/api/users')
        ->assertOk();

    $names = collect($response->json('users'))->pluck('name')->all();

    expect($names)->toBe(collect($names)->sort()->values()->all());
    expect($names)->toContain('Adam Abebe', 'Zoe Zed');
});

test('the user list can be searched by name or email', function () {
    User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@school.test']);
    User::factory()->create(['name' => 'Ada King', 'email' => 'king@school.test']);
    User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@school.test']);

    $byName = $this->withToken(usersAccessTokenFor(User::factory()->create()))
        ->getJson('/api/users?search=ada')
        ->assertOk()
        ->json('users');

    expect(collect($byName)->pluck('name')->all())->toBe(['Ada King', 'Ada Lovelace']);

    $byEmail = $this->withToken(usersAccessTokenFor(User::factory()->create()))
        ->getJson('/api/users?search=grace@')
        ->assertOk()
        ->json('users');

    expect(collect($byEmail)->pluck('name')->all())->toBe(['Grace Hopper']);
});

test('the user list can be filtered by role and sorted descending', function () {
    User::factory()->create(['name' => 'Ada Lovelace', 'role' => 'trainer']);
    User::factory()->create(['name' => 'Grace Hopper', 'role' => 'admin']);

    $token = usersAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->getJson('/api/users?role=trainer')
        ->assertOk()
        ->assertJsonCount(1, 'users')
        ->assertJsonPath('users.0.name', 'Ada Lovelace');

    $sorted = $this->withToken($token)
        ->getJson('/api/users?sort=email&order=desc')
        ->assertOk();

    $emails = collect($sorted->json('users'))->pluck('email')->all();

    expect($emails)->toBe(collect($emails)->sortDesc()->values()->all())
        ->and($sorted->json('meta.total'))->toBe(3);

    $this->withToken($token)->getJson('/api/users?sort=password')->assertUnprocessable();
});

test('searching with no matches returns an empty list', function () {
    User::factory()->create(['name' => 'Ada Lovelace']);

    $this->withToken(usersAccessTokenFor(User::factory()->create()))
        ->getJson('/api/users?search=zzzz-no-such-user')
        ->assertOk()
        ->assertJson(['users' => []]);
});

test('the user list paginates huge datasets so a single page stays fast', function () {
    User::factory()->count(60)->create();

    $token = usersAccessTokenFor(User::factory()->create());

    $response = $this->withToken($token)
        ->getJson('/api/users')
        ->assertOk();

    expect(count($response->json('users')))->toBe(20)
        ->and($response->json('meta.total'))->toBe(61)
        ->and($response->json('meta.last_page'))->toBe(4);

    $this->withToken($token)->getJson('/api/users?per_page=500')->assertUnprocessable();
});
