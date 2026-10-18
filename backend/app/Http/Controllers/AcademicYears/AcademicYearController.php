<?php

namespace App\Http\Controllers\AcademicYears;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicYears\StoreAcademicYearRequest;
use App\Http\Requests\AcademicYears\UpdateAcademicYearRequest;
use App\Http\Resources\AcademicYears\AcademicYearResource;
use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AcademicYearController extends Controller
{
    use PaginatesLists;

    /**
     * List academic years newest first, with the shared search / sort /
     * pagination contract.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'is_active' => ['nullable', 'boolean'],
        ]);

        $paginator = $this->paginateList(
            $request,
            AcademicYear::query(),
            searchable: ['name'],
            sortable: [
                'name' => 'name',
                'start_date' => 'start_date',
                'end_date' => 'end_date',
                'is_active' => 'is_active',
                'created_at' => 'created_at',
            ],
            defaultOrder: [['start_date', 'desc'], ['id', 'desc']],
            applyFilters: function (Builder $query) use ($filters): void {
                if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
                    $query->where('is_active', (bool) $filters['is_active']);
                }
            },
        );

        return response()->json([
            'academic_years' => AcademicYearResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * Create a new academic year, stamping the creating user.
     */
    public function store(StoreAcademicYearRequest $request): JsonResponse
    {
        $academicYear = AcademicYear::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'academic_year' => new AcademicYearResource($academicYear),
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a single academic year.
     */
    public function show(AcademicYear $academicYear): JsonResponse
    {
        return response()->json([
            'academic_year' => new AcademicYearResource($academicYear),
        ]);
    }

    /**
     * Update an existing academic year, stamping the updating user.
     */
    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear): JsonResponse
    {
        $academicYear->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'academic_year' => new AcademicYearResource($academicYear->refresh()),
        ]);
    }

    /**
     * Delete an academic year.
     */
    public function destroy(AcademicYear $academicYear): Response
    {
        $academicYear->delete();

        return response()->noContent();
    }
}
