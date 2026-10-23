<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use PaginatesLists;

    /**
     * Search users for reference pickers (department HOD, class teacher, …),
     * with the shared search / sort / pagination contract.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'role' => ['nullable', 'string', 'max:50', Rule::exists('roles', 'name')],
        ]);

        $paginator = $this->paginateList(
            $request,
            // The resource renders each user's roles and permissions, so
            // they ride along with the page instead of loading per row.
            User::query()->with(['roles.permissions', 'permissions']),
            searchable: ['name', 'email', 'login_id'],
            sortable: [
                'name' => 'name',
                'email' => 'email',
                'created_at' => 'created_at',
            ],
            defaultOrder: [['name', 'asc']],
            applyFilters: function (Builder $query) use ($filters): void {
                $query->when(
                    $filters['role'] ?? null,
                    fn ($query, $role) => $query->whereHas(
                        'roles',
                        fn ($roles) => $roles->where('name', $role),
                    ),
                );
            },
        );

        return response()->json([
            'users' => UserResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }
}
