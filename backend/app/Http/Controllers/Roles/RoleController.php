<?php

namespace App\Http\Controllers\Roles;

use App\Enums\Role;
use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role as RoleModel;

class RoleController extends Controller
{
    use PaginatesLists;

    /**
     * List the roles and the permissions each one bundles.
     *
     * The catalog is fixed by the application (admin, trainer, student,
     * finance) and mirrored into Spatie's tables, where the permissions
     * are granted; the endpoint stays read-only — the enum sets the order
     * and supplies the human labels — so the client can render each role's
     * access with no way to alter it. Search, sort and pagination run over
     * the in-memory list instead of a query.
     */
    public function index(Request $request): JsonResponse
    {
        $roleModels = RoleModel::query()
            ->with('permissions')
            ->get()
            ->keyBy('name');

        $roles = collect(Role::cases())
            ->map(function (Role $role) use ($roleModels): array {
                // keyBy() hands back a base collection, so the row is
                // fetched by key; the migration guarantees the catalog
                // exists for every enum case.
                $roleModel = $roleModels->get($role->value);

                abort_if($roleModel === null, 500, 'Role catalog is out of sync with the database.');

                return [
                    'id' => $roleModel->id,
                    'name' => $role->label(),
                    'slug' => $role->value,
                    'description' => $role->description(),
                    'permissions' => $roleModel->permissions
                        ->pluck('name')
                        ->sort()
                        ->values()
                        ->all(),
                ];
            });

        $paginator = $this->paginateCollection(
            $request,
            $roles->all(),
            searchable: ['name', 'slug'],
            sortable: ['name' => 'name', 'slug' => 'slug', 'id' => 'id'],
        );

        return response()->json([
            'roles' => $paginator->items(),
            'meta' => $this->listMeta($paginator),
        ]);
    }
}
