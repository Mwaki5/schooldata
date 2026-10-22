<?php

namespace App\Http\Controllers\FeeStructures;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\FeeStructures\StoreFeeStructureItemRequest;
use App\Http\Requests\FeeStructures\UpdateFeeStructureItemRequest;
use App\Http\Resources\FeeStructures\FeeStructureItemResource;
use App\Models\FeeStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FeeStructureItemController extends Controller
{
    use PaginatesLists;

    /**
     * The named charges of one fee structure, ordered by name with the
     * shared search / sort / pagination contract, beside the total their
     * live amounts add up to — the figure the structure charges.
     */
    public function index(Request $request, FeeStructure $feeStructure): JsonResponse
    {
        $paginator = $this->paginateList(
            $request,
            $feeStructure->items()->getQuery(),
            searchable: ['name'],
            sortable: [
                'name' => 'name',
                'amount' => 'amount',
                'created_at' => 'created_at',
            ],
            defaultOrder: [['name', 'asc']],
        );

        return response()->json([
            'items' => FeeStructureItemResource::collection($paginator->items()),
            'total' => (float) $feeStructure->items()->sum('amount'),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * Add a charge to the structure, stamping the creating user.
     */
    public function store(StoreFeeStructureItemRequest $request, FeeStructure $feeStructure): JsonResponse
    {
        $item = $feeStructure->items()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'item' => new FeeStructureItemResource($item),
        ], Response::HTTP_CREATED);
    }

    /**
     * Update a charge of the structure, stamping the updating user. The
     * item is resolved through the parent, so an item of another
     * structure is never reachable here.
     */
    public function update(UpdateFeeStructureItemRequest $request, FeeStructure $feeStructure, int $feeStructureItem): JsonResponse
    {
        $item = $feeStructure->items()->findOrFail($feeStructureItem);

        $item->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'item' => new FeeStructureItemResource($item->refresh()),
        ]);
    }

    /**
     * Soft delete a charge of the structure.
     */
    public function destroy(FeeStructure $feeStructure, int $feeStructureItem): Response
    {
        $feeStructure->items()->findOrFail($feeStructureItem)->delete();

        return response()->noContent();
    }
}
