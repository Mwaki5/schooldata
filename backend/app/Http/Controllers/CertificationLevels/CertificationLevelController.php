<?php

namespace App\Http\Controllers\CertificationLevels;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\CertificationLevels\StoreCertificationLevelRequest;
use App\Http\Requests\CertificationLevels\UpdateCertificationLevelRequest;
use App\Http\Resources\CertificationLevels\CertificationLevelResource;
use App\Models\CertificationLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CertificationLevelController extends Controller
{
    use PaginatesLists;

    /**
     * List certification levels grouped by exam body and sequence, with
     * the shared search / sort / pagination contract.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'exam_body_id' => ['nullable', 'integer', 'exists:exam_bodies,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $paginator = $this->paginateList(
            $request,
            CertificationLevel::query()->with('examBody'),
            searchable: ['name', 'code'],
            sortable: [
                'name' => 'name',
                'code' => 'code',
                'sequence' => 'sequence',
                'is_active' => 'is_active',
                'created_at' => 'created_at',
            ],
            defaultOrder: [['exam_body_id', 'asc'], ['sequence', 'asc']],
            applyFilters: function (Builder $query) use ($filters): void {
                $query
                    ->when($filters['exam_body_id'] ?? null, fn ($query, $examBodyId) => $query->where('exam_body_id', $examBodyId))
                    ->when(
                        array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                        fn ($query) => $query->where('is_active', (bool) $filters['is_active']),
                    );
            },
        );

        return response()->json([
            'certification_levels' => CertificationLevelResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * Create a new certification level, stamping the creating user.
     */
    public function store(StoreCertificationLevelRequest $request): JsonResponse
    {
        $level = CertificationLevel::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'certification_level' => new CertificationLevelResource($level->load('examBody')),
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a single certification level.
     */
    public function show(CertificationLevel $certificationLevel): JsonResponse
    {
        return response()->json([
            'certification_level' => new CertificationLevelResource($certificationLevel->load('examBody')),
        ]);
    }

    /**
     * Update an existing certification level, stamping the updating user.
     */
    public function update(UpdateCertificationLevelRequest $request, CertificationLevel $certificationLevel): JsonResponse
    {
        $certificationLevel->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'certification_level' => new CertificationLevelResource($certificationLevel->refresh()->load('examBody')),
        ]);
    }

    /**
     * Soft delete a certification level.
     */
    public function destroy(CertificationLevel $certificationLevel): Response
    {
        $certificationLevel->delete();

        return response()->noContent();
    }
}
