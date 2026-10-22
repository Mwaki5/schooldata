<?php

namespace App\Http\Controllers\FeeStructures;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\FeeStructures\StoreFeeStructureRequest;
use App\Http\Requests\FeeStructures\UpdateFeeStructureRequest;
use App\Http\Resources\FeeStructures\FeeStructureResource;
use App\Models\FeeStructure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FeeStructureController extends Controller
{
    use PaginatesLists;

    /**
     * List fee structures ordered by name, with the shared search / sort /
     * pagination contract. The list can be narrowed to live or retired
     * structures with the `is_active` filter.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'is_active' => ['nullable', 'boolean'],
        ]);

        $paginator = $this->paginateList(
            $request,
            FeeStructure::query()->withSum('items as total_amount', 'amount'),
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
            'fee_structures' => FeeStructureResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * Create a new fee structure, stamping the creating user.
     */
    public function store(StoreFeeStructureRequest $request): JsonResponse
    {
        $feeStructure = FeeStructure::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'fee_structure' => new FeeStructureResource($feeStructure),
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a single fee structure with its summed item total.
     */
    public function show(FeeStructure $feeStructure): JsonResponse
    {
        $feeStructure->loadSum('items as total_amount', 'amount');

        return response()->json([
            'fee_structure' => new FeeStructureResource($feeStructure),
        ]);
    }

    /**
     * Update an existing fee structure, stamping the updating user.
     */
    public function update(UpdateFeeStructureRequest $request, FeeStructure $feeStructure): JsonResponse
    {
        $feeStructure->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'fee_structure' => new FeeStructureResource($feeStructure->refresh()),
        ]);
    }

    /**
     * Soft delete a fee structure.
     */
    public function destroy(FeeStructure $feeStructure): Response
    {
        $feeStructure->delete();

        return response()->noContent();
    }
}
