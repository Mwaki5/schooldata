<?php

namespace App\Http\Controllers\Users;

use App\Enums\Role;
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
            'role' => ['nullable', Rule::enum(Role::class)],
        ]);

        $paginator = $this->paginateList(
            $request,
            User::query(),
            searchable: ['name', 'email', 'login_id'],
            sortable: [
                'name' => 'name',
                'email' => 'email',
                'created_at' => 'created_at',
            ],
            defaultOrder: [['name', 'asc']],
            applyFilters: function (Builder $query) use ($filters): void {
                $query->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role));
            },
        );

        return response()->json([
            'users' => UserResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }
}
