<?php

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tymon\JWTAuth\Facades\JWTAuth;

function rateLimitAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('login attempts are rate limited per ip', function () {
    RateLimiter::for('auth', fn () => Limit::perMinute(2));

    $attempt = fn () => $this->postJson('/api/login', [
        'email' => 'nobody@example.com',
        'password' => 'wrong-password',
    ]);

    $attempt()->assertUnprocessable();
    $attempt()->assertUnprocessable();
    $attempt()->assertStatus(429);
});

test('registration is rate limited per ip', function () {
    RateLimiter::for('auth', fn () => Limit::perMinute(2));

    $attempt = fn () => $this->postJson('/api/register', [
        'name' => 'Rate Probe',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $attempt()->assertCreated();
    $attempt()->assertCreated();
    $attempt()->assertStatus(429);
});

test('token refresh is rate limited per ip', function () {
    RateLimiter::for('session', fn () => Limit::perMinute(2));

    $this->postJson('/api/refresh')->assertUnauthorized();
    $this->postJson('/api/refresh')->assertUnauthorized();
    $this->postJson('/api/refresh')->assertStatus(429);
});

test('logout is rate limited per ip', function () {
    RateLimiter::for('session', fn () => Limit::perMinute(2));

    $this->postJson('/api/logout')->assertNoContent();
    $this->postJson('/api/logout')->assertNoContent();
    $this->postJson('/api/logout')->assertStatus(429);
});

test('management endpoints are rate limited per user', function () {
    RateLimiter::for('api', fn () => Limit::perMinute(2));

    $token = rateLimitAccessTokenFor(User::factory()->create());

    $this->withToken($token)->getJson('/api/roles')->assertOk();
    $this->withToken($token)->getJson('/api/users')->assertOk();
    $this->withToken($token)->getJson('/api/departments')->assertStatus(429);
});

test('the api quota is keyed per user rather than per ip', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();

    $limiter = RateLimiter::limiter('api');

    $keyFor = function (User $user) use ($limiter) {
        $request = Request::create('/api/roles');
        $request->setUserResolver(fn () => $user);

        return $limiter($request)->key;
    };

    $guestKey = $limiter(Request::create('/api/roles'))->key;

    expect($keyFor($first))->not->toBe($keyFor($second))
        ->and($keyFor($first))->toBe($keyFor($first))
        ->and($guestKey)->toStartWith('ip:');
});

test('repeated failed logins lock the account before the ip limit is reached', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    // The sixth attempt is refused even with the correct password.
    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});
