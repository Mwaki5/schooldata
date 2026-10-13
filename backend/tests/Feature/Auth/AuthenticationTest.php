<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tymon\JWTAuth\Facades\JWTAuth;

function accessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

function refreshTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.refresh_ttl'));

    return JWTAuth::claims(['type' => 'refresh'])->fromUser($user);
}

function refreshCookieFrom(TestResponse $response): Cookie
{
    $cookie = collect($response->headers->getCookies())
        ->first(fn (Cookie $cookie) => $cookie->getName() === 'refresh_token');

    expect($cookie)->not->toBeNull();

    return $cookie;
}

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'user' => ['id', 'name', 'email']])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.email', $user->email);

    $cookie = refreshCookieFrom($response);

    expect($cookie->getValue())->not->toBe('')
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getPath())->toBe('/api');

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable();

    $this->assertGuest();
});

test('users can authenticate with their login id instead of an email', function () {
    $user = User::factory()->create(['login_id' => 'EMP-0001']);

    $response = $this->postJson('/api/login', [
        'email' => 'EMP-0001',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'user' => ['id', 'name', 'email', 'login_id']])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('user.login_id', 'EMP-0001');

    $this->assertAuthenticated();
});

test('an unknown login id is rejected', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'EMP-4044',
        'password' => 'password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    $this->assertGuest();
});

test('authenticated users can fetch their profile', function () {
    $user = User::factory()->create();

    $this->withToken(accessTokenFor($user))
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonStructure(['user' => ['id', 'name', 'email']])
        ->assertJsonPath('user.email', $user->email);
});

test('unauthenticated users can not fetch a profile', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

test('refresh tokens can not be used as bearer tokens', function () {
    $user = User::factory()->create();

    $this->withToken(refreshTokenFor($user))
        ->getJson('/api/user')
        ->assertUnauthorized();
});

test('access tokens can not be used as refresh tokens', function () {
    $user = User::factory()->create();

    $this->withCookie('refresh_token', accessTokenFor($user))
        ->disableCookieEncryption()
        ->withCredentials()
        ->postJson('/api/refresh')
        ->assertUnauthorized();
});

test('refreshing without a cookie is rejected', function () {
    $this->postJson('/api/refresh')->assertUnauthorized();
});

test('expired refresh tokens are rejected', function () {
    Carbon::setTestNow(Carbon::now());

    $user = User::factory()->create();
    $refreshToken = refreshTokenFor($user);

    Carbon::setTestNow(Carbon::now()->addDays(8));

    $this->withCookie('refresh_token', $refreshToken)
        ->disableCookieEncryption()
        ->withCredentials()
        ->postJson('/api/refresh')
        ->assertUnauthorized();
});

test('the refresh endpoint rotates the token pair', function () {
    $user = User::factory()->create();

    $login = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $usedRefreshToken = refreshCookieFrom($login)->getValue();

    $refreshed = $this->withCookie('refresh_token', $usedRefreshToken)
        ->disableCookieEncryption()
        ->withCredentials()
        ->postJson('/api/refresh');

    $refreshed->assertOk()
        ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'user' => ['id', 'name', 'email']])
        ->assertJsonPath('user.email', $user->email);

    // The freshly issued access token works.
    $this->withToken($refreshed->json('access_token'))
        ->getJson('/api/user')
        ->assertOk();

    // The used refresh token was rotated and can not be replayed.
    $this->withCookie('refresh_token', $usedRefreshToken)
        ->disableCookieEncryption()
        ->withCredentials()
        ->postJson('/api/refresh')
        ->assertUnauthorized();

    // The newly issued refresh cookie works.
    $this->withCookie('refresh_token', refreshCookieFrom($refreshed)->getValue())
        ->disableCookieEncryption()
        ->withCredentials()
        ->postJson('/api/refresh')
        ->assertOk();
});

test('users can logout and their tokens are revoked', function () {
    $user = User::factory()->create();

    $login = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $accessToken = $login->json('access_token');
    $refreshToken = refreshCookieFrom($login)->getValue();

    $this->withToken($accessToken)
        ->withCookie('refresh_token', $refreshToken)
        ->disableCookieEncryption()
        ->withCredentials()
        ->postJson('/api/logout')
        ->assertNoContent();

    // The access token was blacklisted.
    $this->withToken($accessToken)
        ->getJson('/api/user')
        ->assertUnauthorized();

    // The refresh token was revoked as well.
    $this->withCookie('refresh_token', $refreshToken)
        ->disableCookieEncryption()
        ->withCredentials()
        ->postJson('/api/refresh')
        ->assertUnauthorized();
});
