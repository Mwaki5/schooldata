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
use Illuminate\Support\Facades\DB;

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
     * Create a new academic year, stamping the creating user. Only one
     * year may be active, so activating this one retires the others.
     */
    public function store(StoreAcademicYearRequest $request): JsonResponse
    {
        $academicYear = DB::transaction(function () use ($request): AcademicYear {
            // Lock every year row so concurrent activations serialise and
            // can never leave two (or zero) active years behind.
            AcademicYear::query()->lockForUpdate()->get();

            $academicYear = AcademicYear::create([
                ...$request->validated(),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            if ($academicYear->is_active) {
                $this->deactivateOtherYears($academicYear, $request->user()->id);
            }

            return $academicYear;
        });

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
     * Update an existing academic year, stamping the updating user. Only
     * one year may be active, so activating this one retires the others.
     */
    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear): JsonResponse
    {
        DB::transaction(function () use ($request, $academicYear): void {
            AcademicYear::query()->lockForUpdate()->get();

            $academicYear->update([
                ...$request->validated(),
                'updated_by' => $request->user()->id,
            ]);

            if ($academicYear->is_active) {
                $this->deactivateOtherYears($academicYear, $request->user()->id);
            }
        });

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

    /**
     * Keep at most one active academic year: activating this one turns
     * every other year off, stamped with the acting user.
     */
    private function deactivateOtherYears(AcademicYear $academicYear, int $userId): void
    {
        AcademicYear::query()
            ->whereKeyNot($academicYear->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'updated_by' => $userId,
            ]);
    }
}
