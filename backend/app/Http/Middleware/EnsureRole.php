<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Authorise the request when the authenticated user holds one of the
     * roles listed as middleware parameters.
     *
     * One role or many, comma-separated on the route definition — the
     * check is OR across the list, so `role:admin,finance` admits an
     * admin OR a finance user:
     *
     *     Route::get(...)->middleware('role:admin,finance');
     *
     * Unknown role names are ignored; if no valid role remains the route
     * fails closed (nobody passes). Runs after `auth:api`, so the user is
     * always resolved fresh from the database — a role change takes effect
     * on the very next request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $allowed = array_values(array_filter(array_map(Role::tryFrom(...), $roles)));

        if (! in_array($user->role, $allowed, true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
