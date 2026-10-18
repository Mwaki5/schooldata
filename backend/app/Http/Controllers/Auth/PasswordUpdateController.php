<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class PasswordUpdateController extends Controller
{
    /**
     * Change the authenticated user's own password.
     *
     * Any signed-in user may call this (no role gate), so the route only
     * relies on `auth:api`. A successful change also clears the
     * `must_reset_password` flag set when an admin provisions the account.
     */
    public function update(Request $request): Response
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults(), 'different:current_password'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->string('password')),
            'must_reset_password' => false,
            'password_changed_at' => now(),
        ]);

        activity('auth')
            ->event('password_update')
            ->causedBy($user)
            ->log('Changed password');

        return response()->noContent();
    }
}
