<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Authorise the request when the authenticated user holds one of the
     * permissions listed as middleware parameters.
     *
     * One permission or many, comma-separated on the route definition —
     * the check is OR across the list, so `permission:fees.view,reports.view`
     * admits a user holding either:
     *
     *     Route::get(...)->middleware('permission:units.view');
     *
     * Permissions come from Spatie's tables: granted directly or through
     * any of the user's roles, whose union forms their effective set.
     * Names not in the catalog grant nothing, so an unknown name fails
     * closed (nobody passes). Runs after `auth:api`, so the user is
     * always resolved fresh from the database — a role or permission
     * change takes effect on the very next request.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Load the role and permission relations with the user: the
        // effective set spans roles and their permissions, and lazy
        // loading is prevented app-wide.
        $user->loadMissing('roles.permissions', 'permissions');

        $granted = $user->getAllPermissions()->pluck('name');

        foreach ($permissions as $permission) {
            if ($granted->contains($permission)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Forbidden.'], 403);
    }
}
