<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class EnsureAccessToken
{
    /**
     * Ensure the presented bearer token is an access token (not a refresh token).
     *
     * Without this check a long-lived refresh token could be used as a bearer
     * token, defeating the short access token lifetime.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $payload = JWTAuth::setToken($token)->getPayload();
        } catch (JWTException) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($payload->get('type') !== 'access') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
