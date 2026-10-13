<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Http\Resources\Staff\StaffResource;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StaffController extends Controller
{
    /**
     * List every staff member, optionally filtered by name or number.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->toString();

        $staff = Staff::with('department')
            ->when($search, function ($query, $search) {
                $like = '%'.$search.'%';

                $query->where(function ($query) use ($like) {
                    $query->where('name', 'like', $like)
                        ->orWhere('employment_no', 'like', $like);
                });
            })
            ->orderBy('employment_no')
            ->get();

        return response()->json([
            'staff' => StaffResource::collection($staff),
        ]);
    }

    /**
     * Create a new staff profile, stamping the creating user.
     */
    public function store(StoreStaffRequest $request): JsonResponse
    {
        $staffMember = Staff::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'staff' => new StaffResource($staffMember->load('department')),
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a single staff member.
     */
    public function show(Staff $staff): JsonResponse
    {
        return response()->json([
            'staff' => new StaffResource($staff->load('department')),
        ]);
    }

    /**
     * Update an existing staff member, stamping the updating user.
     */
    public function update(UpdateStaffRequest $request, Staff $staff): JsonResponse
    {
        $staff->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'staff' => new StaffResource($staff->refresh()->load('department')),
        ]);
    }

    /**
     * Soft delete a staff member.
     */
    public function destroy(Staff $staff): Response
    {
        $staff->delete();

        return response()->noContent();
    }
}
