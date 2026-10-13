<?php

namespace App\Http\Controllers\Roles;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    /**
     * List the hardcoded role catalog.
     *
     * Roles are fixed by the application (admin, trainer, student, finance)
     * and cannot be created, edited or deleted through the API or the
     * database, so this endpoint only ever renders the enum.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'roles' => Role::catalog(),
        ]);
    }
}
