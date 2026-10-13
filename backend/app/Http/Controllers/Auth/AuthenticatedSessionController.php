<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\IssuesJwtPairs;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthenticatedSessionController extends Controller
{
    use IssuesJwtPairs;

    /**
     * Authenticate the user and issue a new token pair.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        /** @var User $user */
        $user = Auth::user();

        activity('auth')
            ->event('login')
            ->causedBy($user)
            ->log('Logged in');

        return $this->tokenPairResponse($user, $this->issueTokenPair($user));
    }

    /**
     * Rotate the token pair using the httpOnly refresh cookie.
     */
    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->cookie('refresh_token');

        if (! is_string($refreshToken) || $refreshToken === '') {
            return response()->json(['message' => 'Refresh token missing.'], 401);
        }

        try {
            $payload = JWTAuth::setToken($refreshToken)->getPayload();
        } catch (TokenExpiredException) {
            return response()->json(['message' => 'Refresh token expired.'], 401);
        } catch (TokenBlacklistedException) {
            return response()->json(['message' => 'Refresh token has been revoked.'], 401);
        } catch (JWTException) {
            return response()->json(['message' => 'Invalid refresh token.'], 401);
        }

        if ($payload->get('type') !== 'refresh') {
            return response()->json(['message' => 'Invalid refresh token.'], 401);
        }

        $user = User::findOrFail($payload->get('sub'));

        // Rotate: revoke the used refresh token before issuing the new pair,
        // so a leaked token can only be redeemed once.
        JWTAuth::invalidate($refreshToken);

        return $this->tokenPairResponse($user, $this->issueTokenPair($user));
    }

    /**
     * Revoke the presented tokens and clear the refresh cookie.
     */
    public function destroy(Request $request): Response
    {
        /** @var User|null $user */
        $user = auth('api')->user();

        try {
            JWTAuth::parseToken()->invalidate();
        } catch (JWTException) {
            // The access token is already expired or invalid; nothing to revoke.
        }

        $refreshToken = $request->cookie('refresh_token');

        if (is_string($refreshToken) && $refreshToken !== '') {
            try {
                JWTAuth::setToken($refreshToken)->invalidate();
            } catch (JWTException) {
                // Best effort; the cookie is cleared either way.
            }
        }

        if ($user !== null) {
            activity('auth')
                ->event('logout')
                ->causedBy($user)
                ->log('Logged out');
        }

        return response()->noContent()->withCookie($this->forgetRefreshCookie());
    }
}
