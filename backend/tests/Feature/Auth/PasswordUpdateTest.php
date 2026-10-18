<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tymon\JWTAuth\Facades\JWTAuth;

function passwordUpdateAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('the password endpoint requires an access token', function () {
    $this->putJson('/api/profile/password', [
        'current_password' => 'password',
        'password' => 'brand-new-secret',
        'password_confirmation' => 'brand-new-secret',
    ])->assertUnauthorized();
});

test('users can change their own password', function () {
    $user = User::factory()->create();

    $this->withToken(passwordUpdateAccessTokenFor($user))
        ->putJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'brand-new-secret',
            'password_confirmation' => 'brand-new-secret',
        ])
        ->assertNoContent();

    $user->refresh();

    expect(Hash::check('brand-new-secret', $user->password))->toBeTrue()
        ->and($user->password_changed_at)->not->toBeNull();

    // The new password works for login and the old one no longer does.
    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'brand-new-secret',
    ])->assertOk();
});

test('a wrong current password is rejected', function () {
    $user = User::factory()->create();

    $this->withToken(passwordUpdateAccessTokenFor($user))
        ->putJson('/api/profile/password', [
            'current_password' => 'not-the-password',
            'password' => 'brand-new-secret',
            'password_confirmation' => 'brand-new-secret',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('the new password must be confirmed', function () {
    $user = User::factory()->create();

    $this->withToken(passwordUpdateAccessTokenFor($user))
        ->putJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'brand-new-secret',
            'password_confirmation' => 'mismatched',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('the new password must differ from the current one', function () {
    $user = User::factory()->create();

    $this->withToken(passwordUpdateAccessTokenFor($user))
        ->putJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('changing the password clears the forced-reset flag', function () {
    $user = User::factory()->create(['must_reset_password' => true]);

    $this->withToken(passwordUpdateAccessTokenFor($user))
        ->putJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'brand-new-secret',
            'password_confirmation' => 'brand-new-secret',
        ])
        ->assertNoContent();

    expect($user->fresh()->must_reset_password)->toBeFalse();
});

test('changing the password is recorded in the activity log', function () {
    $user = User::factory()->create();

    $this->withToken(passwordUpdateAccessTokenFor($user))
        ->putJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'brand-new-secret',
            'password_confirmation' => 'brand-new-secret',
        ])
        ->assertNoContent();

    $activity = Activity::query()
        ->where('log_name', 'auth')
        ->where('event', 'password_update')
        ->first();

    expect($activity)
        ->not->toBeNull()
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->description)->toBe('Changed password');
});
