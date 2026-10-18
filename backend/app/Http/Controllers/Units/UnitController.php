<?php

namespace App\Http\Controllers\Units;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Units\StoreUnitRequest;
use App\Http\Requests\Units\UpdateUnitRequest;
use App\Http\Resources\Units\UnitResource;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UnitController extends Controller
{
    use PaginatesLists;

    /**
     * List the master unit catalogue with the shared
     * search / sort / pagination contract.
     *
     * Each unit appears exactly once no matter how many courses or
     * curricula it is assigned to; `assignments_count` carries its
     * number of live deliveries.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'is_active' => ['nullable', 'boolean'],
        ]);

        $paginator = $this->paginateList(
            $request,
            Unit::query()->withCount('assignments'),
            searchable: ['code', 'name', 'description'],
            sortable: [
                'code' => 'code',
                'name' => 'name',
                'assignments_count' => 'assignments_count',
                'is_active' => 'is_active',
                'created_at' => 'created_at',
            ],
            defaultOrder: [['name', 'asc']],
            applyFilters: function (Builder $query) use ($filters): void {
                $query->when(
                    array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                    fn ($query) => $query->where('is_active', (bool) $filters['is_active']),
                );
            },
        );

        return response()->json([
            'units' => UnitResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * Create a new master unit, stamping the creating user.
     *
     * Deliberately allows names that resemble existing units —
     * duplicate detection is advisory (`duplicates()`), never a merge.
     */
    public function store(StoreUnitRequest $request): JsonResponse
    {
        $unit = Unit::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'unit' => new UnitResource($unit->loadCount('assignments')),
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a single master unit with all of its assignments.
     */
    public function show(Unit $unit): JsonResponse
    {
        return response()->json([
            'unit' => new UnitResource($unit->loadCount('assignments')->load([
                'assignments.unit:id,name',
                'assignments.course:id,code,name',
                'assignments.curriculum:id,name',
            ])),
        ]);
    }

    /**
     * Update a master unit, stamping the updating user.
     *
     * Renaming or deactivating the master never alters its assignments,
     * whose teaching loads stand on their own.
     */
    public function update(UpdateUnitRequest $request, Unit $unit): JsonResponse
    {
        $unit->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'unit' => new UnitResource($unit->refresh()->loadCount('assignments')),
        ]);
    }

    /**
     * Soft delete a unit that has no assignments left.
     *
     * A unit still delivered by courses keeps its history: the caller
     * is told to remove the assignments first instead of losing them.
     */
    public function destroy(Unit $unit): Response|JsonResponse
    {
        if ($unit->assignments()->exists()) {
            return response()->json([
                'message' => 'This unit is still assigned to courses. Remove its assignments before deleting it.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $unit->delete();

        return response()->noContent();
    }

    /**
     * Report master units whose names collide case-insensitively.
     *
     * Advisory only: nothing is merged automatically. The report includes
     * soft-deleted masters so a "duplicate" that was already retired is
     * still visible to whoever reviews the list.
     */
    public function duplicates(): JsonResponse
    {
        $groups = Unit::withTrashed()
            ->selectRaw('LOWER(TRIM(name)) AS normalized_name, COUNT(*) AS total')
            ->groupBy('normalized_name')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('total')
            ->orderBy('normalized_name')
            ->limit(50)
            ->get();

        $duplicates = $groups->map(fn ($group) => [
            'normalized_name' => $group->normalized_name,
            'total' => (int) $group->total,
            'units' => UnitResource::collection(
                Unit::withTrashed()
                    ->whereRaw('LOWER(TRIM(name)) = ?', [$group->normalized_name])
                    ->orderBy('id')
                    ->get(),
            ),
        ]);

        return response()->json(['duplicates' => $duplicates]);
    }
}
