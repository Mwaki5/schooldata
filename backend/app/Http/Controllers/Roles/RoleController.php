<?php

namespace App\Http\Controllers\Roles;

use App\Enums\Role;
use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use PaginatesLists;

    /**
     * List the hardcoded role catalog.
     *
     * Roles are fixed by the application (admin, trainer, student, finance)
     * and cannot be created, edited or deleted through the API or the
     * database, so the endpoint renders the enum through the shared list
     * contract — search, sort and pagination run over the in-memory
     * catalog instead of a query.
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->paginateCollection(
            $request,
            Role::catalog(),
            searchable: ['name', 'slug'],
            sortable: ['name' => 'name', 'slug' => 'slug', 'id' => 'id'],
        );

        return response()->json([
            'roles' => $paginator->items(),
            'meta' => $this->listMeta($paginator),
        ]);
    }
}
