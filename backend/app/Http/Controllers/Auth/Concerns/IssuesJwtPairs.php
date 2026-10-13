<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tymon\JWTAuth\Facades\JWTAuth;

trait IssuesJwtPairs
{
    /**
     * Issue a short-lived access token and a long-lived refresh token.
     *
     * @return array{access_token: string, expires_in: int, refresh_token: string}
     */
    protected function issueTokenPair(User $user): array
    {
        $accessTtl = (int) config('jwt.ttl');
        $refreshTtl = (int) config('jwt.refresh_ttl');

        JWTAuth::factory()->setTTL($accessTtl);
        $accessToken = JWTAuth::claims(['type' => 'access'])->fromUser($user);

        JWTAuth::factory()->setTTL($refreshTtl);
        $refreshToken = JWTAuth::claims(['type' => 'refresh'])->fromUser($user);

        return [
            'access_token' => $accessToken,
            'expires_in' => $accessTtl * 60,
            'refresh_token' => $refreshToken,
        ];
    }

    /**
     * Build the JSON response carrying the access token plus refresh cookie.
     *
     * @param  array{access_token: string, expires_in: int, refresh_token: string}  $tokens
     */
    protected function tokenPairResponse(User $user, array $tokens, int $status = JsonResponse::HTTP_OK): JsonResponse
    {
        return response()->json([
            'access_token' => $tokens['access_token'],
            'token_type' => 'Bearer',
            'expires_in' => $tokens['expires_in'],
            'user' => new UserResource($user),
        ], $status)->withCookie($this->refreshCookie($tokens['refresh_token']));
    }

    /**
     * Build the httpOnly cookie that carries the refresh token.
     */
    protected function refreshCookie(string $refreshToken): Cookie
    {
        return $this->makeRefreshCookie(
            value: $refreshToken,
            minutes: (int) config('jwt.refresh_ttl'),
        );
    }

    /**
     * Build the expired cookie used to clear the refresh cookie on logout.
     */
    protected function forgetRefreshCookie(): Cookie
    {
        return $this->makeRefreshCookie(value: '', minutes: -60);
    }

    private function makeRefreshCookie(string $value, int $minutes): Cookie
    {
        return cookie(
            name: 'refresh_token',
            value: $value,
            minutes: $minutes,
            path: '/api',
            httpOnly: true,
            secure: app()->environment('production'),
            sameSite: 'lax',
        );
    }
}
