<?php

namespace App\Http\Controllers\Departments;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\StoreDepartmentRequest;
use App\Http\Requests\Departments\UpdateDepartmentRequest;
use App\Http\Resources\Departments\DepartmentResource;
use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DepartmentController extends Controller
{
    use PaginatesLists;

    /**
     * List departments ordered by name, with the shared search / sort /
     * pagination contract, the head of department eagerly loaded.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'is_active' => ['nullable', 'boolean'],
        ]);

        $paginator = $this->paginateList(
            $request,
            Department::query()->with('hod'),
            searchable: ['name'],
            sortable: [
                'name' => 'name',
                'is_active' => 'is_active',
                'created_at' => 'created_at',
            ],
            defaultOrder: [['name', 'asc']],
            applyFilters: function (Builder $query) use ($filters): void {
                if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
                    $query->where('is_active', (bool) $filters['is_active']);
                }
            },
        );

        return response()->json([
            'departments' => DepartmentResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * Create a new department.
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());
        $department->load('hod');

        return response()->json([
            'department' => new DepartmentResource($department),
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a single department.
     */
    public function show(Department $department): JsonResponse
    {
        $department->load('hod');

        return response()->json([
            'department' => new DepartmentResource($department),
        ]);
    }

    /**
     * Update an existing department.
     */
    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $department->update($request->validated());
        $department->load('hod');

        return response()->json([
            'department' => new DepartmentResource($department),
        ]);
    }

    /**
     * Delete a department.
     */
    public function destroy(Department $department): Response
    {
        $department->delete();

        return response()->noContent();
    }
}
