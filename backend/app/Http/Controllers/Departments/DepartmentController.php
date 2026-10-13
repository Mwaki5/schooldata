<?php

namespace App\Http\Controllers\Departments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\StoreDepartmentRequest;
use App\Http\Requests\Departments\UpdateDepartmentRequest;
use App\Http\Resources\Departments\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DepartmentController extends Controller
{
    /**
     * List every department, ordered by name.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->toString();

        $departments = Department::with('hod')
            ->when($search, fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->get();

        return response()->json([
            'departments' => DepartmentResource::collection($departments),
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
