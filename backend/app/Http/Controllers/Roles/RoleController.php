<?php

namespace App\Http\Controllers\Roles;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role as RoleModel;

class RoleController extends Controller
{
    use PaginatesLists;

    /**
     * List every role with the permissions it currently bundles, plus
     * the permission catalog grouped by resource.
     *
     * Roles live in Spatie's tables: the built-ins are seeded from the
     * enum (which sets their labels, descriptions and order) and custom
     * roles follow alphabetically. The catalog rides along so the roles
     * screen can render the checkbox matrix from one request.
     */
    public function index(Request $request): JsonResponse
    {
        $roleModels = RoleModel::query()
            ->with('permissions')
            ->get()
            ->keyBy('name');

        $builtIns = collect(Role::cases())
            ->map(function (Role $role) use ($roleModels): array {
                // The seeder guarantees the catalog exists for every
                // enum case on a migrated database.
                $roleModel = $roleModels->get($role->value);

                abort_if($roleModel === null, 500, 'Role catalog is out of sync with the database.');

                return $this->rolePayload($roleModel);
            });

        $custom = $roleModels
            ->reject(fn (RoleModel $roleModel): bool => Role::tryFrom($roleModel->name) !== null)
            ->sortBy('name')
            ->values()
            ->map(fn (RoleModel $roleModel): array => $this->rolePayload($roleModel));

        $paginator = $this->paginateCollection(
            $request,
            $builtIns->merge($custom)->all(),
            searchable: ['name', 'slug'],
            sortable: ['name' => 'name', 'slug' => 'name', 'id' => 'id'],
        );

        return response()->json([
            'roles' => $paginator->items(),
            'meta' => $this->listMeta($paginator),
            'catalog' => Permission::catalog(),
        ]);
    }

    /**
     * Create a custom role with no permissions; access is granted
     * through the roles screen afterwards.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => $this->nameRules(),
        ]);

        $roleModel = RoleModel::findOrCreate($validated['name'], (new User)->guardName());

        return response()->json(['role' => $this->rolePayload($roleModel)], 201);
    }

    /**
     * Rename a custom role. Built-in names are the role's identity in
     * the seeded catalog and stay fixed.
     */
    public function update(Request $request, string $role): JsonResponse
    {
        $roleModel = $this->findOrFailRole($role);

        $this->rejectLocked($roleModel);

        abort_if(
            Role::tryFrom($roleModel->name) !== null,
            403,
            'Built-in roles keep their names.',
        );

        $validated = $request->validate([
            'name' => $this->nameRules(except: $roleModel->name),
        ]);

        $roleModel->name = $validated['name'];
        $roleModel->save();

        return response()->json(['role' => $this->rolePayload($roleModel)]);
    }

    /**
     * Replace a role's bundled permissions — the endpoint the roles
     * screen's checkboxes save through.
     */
    public function updatePermissions(Request $request, string $role): JsonResponse
    {
        $roleModel = $this->findOrFailRole($role);

        $this->rejectLocked($roleModel);

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', Rule::in(Permission::names())],
        ]);

        $roleModel->syncPermissions($validated['permissions']);

        return response()->json(['role' => $this->rolePayload($roleModel->load('permissions'))]);
    }

    /**
     * Delete a custom or seeded role; Spatie detaches its users and
     * permissions on delete. The locked admin role cannot go.
     */
    public function destroy(string $role): Response
    {
        $roleModel = $this->findOrFailRole($role);

        $this->rejectLocked($roleModel);

        $roleModel->delete();

        return response()->noContent();
    }

    /**
     * Look a role up by its name (the route key) within the guard.
     */
    private function findOrFailRole(string $role): RoleModel
    {
        return RoleModel::query()
            ->where('name', $role)
            ->where('guard_name', (new User)->guardName())
            ->firstOrFail();
    }

    /**
     * The admin role is the system's safety net: it always holds every
     * permission, so its shape cannot be altered.
     */
    private function rejectLocked(RoleModel $roleModel): void
    {
        abort_if(
            $roleModel->name === Role::Admin->value,
            403,
            'The admin role always holds every permission and cannot be changed.',
        );
    }

    /**
     * Validation for a role name: a lowercase slug that is not already
     * taken and does not squat on a built-in role's name.
     *
     * @return array<int, mixed>
     */
    private function nameRules(?string $except = null): array
    {
        $reserved = collect(Role::cases())
            ->map(fn (Role $role): string => $role->value)
            ->reject(fn (string $name): bool => $name === $except)
            ->values()
            ->all();

        return [
            'required',
            'string',
            'min:2',
            'max:50',
            Rule::unique('roles', 'name')->ignore($except),
            Rule::notIn($reserved),
            'regex:/^[a-z][a-z0-9-]*$/',
        ];
    }

    /**
     * The wire shape of one role. Built-in roles carry their seeded
     * label and description; a custom role displays its own name. `locked`
     * marks the untouchable admin role and `built_in` the seeded ones.
     *
     * @return array{id: int, name: string, slug: string, description: string|null, locked: bool, built_in: bool, permissions: list<string>}
     */
    private function rolePayload(RoleModel $roleModel): array
    {
        // The payload renders the role's permissions; load them with the
        // role so no path hits a lazy load (lazy loading is prevented
        // app-wide).
        $roleModel->loadMissing('permissions');

        $builtIn = Role::tryFrom($roleModel->name);

        return [
            'id' => $roleModel->id,
            'name' => $builtIn?->label() ?? str($roleModel->name)->title(),
            'slug' => $roleModel->name,
            'description' => $builtIn?->description(),
            'locked' => $roleModel->name === Role::Admin->value,
            'built_in' => $builtIn !== null,
            'permissions' => $roleModel->permissions
                ->pluck('name')
                ->sort()
                ->values()
                ->all(),
        ];
    }
}
