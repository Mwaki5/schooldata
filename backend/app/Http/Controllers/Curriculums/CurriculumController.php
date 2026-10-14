<?php

namespace App\Http\Controllers\Curriculums;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Curriculums\StoreCurriculumRequest;
use App\Http\Requests\Curriculums\UpdateCurriculumRequest;
use App\Http\Resources\Curriculums\CurriculumResource;
use App\Models\Curriculum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CurriculumController extends Controller
{
    use PaginatesLists;

    /**
     * List curricula grouped by exam body, with the shared search / sort /
     * pagination contract.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'exam_body_id' => ['nullable', 'integer', 'exists:exam_bodies,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $paginator = $this->paginateList(
            $request,
            Curriculum::query()->with('examBody'),
            searchable: ['name'],
            sortable: [
                'name' => 'name',
                'is_active' => 'is_active',
                'created_at' => 'created_at',
            ],
            defaultOrder: [['exam_body_id', 'asc'], ['name', 'asc']],
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
            'curricula' => CurriculumResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * Create a new curriculum, stamping the creating user.
     */
    public function store(StoreCurriculumRequest $request): JsonResponse
    {
        $curriculum = Curriculum::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'curriculum' => new CurriculumResource($curriculum->load('examBody')),
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a single curriculum.
     */
    public function show(Curriculum $curriculum): JsonResponse
    {
        return response()->json([
            'curriculum' => new CurriculumResource($curriculum->load('examBody')),
        ]);
    }

    /**
     * Update an existing curriculum, stamping the updating user.
     */
    public function update(UpdateCurriculumRequest $request, Curriculum $curriculum): JsonResponse
    {
        $curriculum->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'curriculum' => new CurriculumResource($curriculum->refresh()->load('examBody')),
        ]);
    }

    /**
     * Soft delete a curriculum.
     */
    public function destroy(Curriculum $curriculum): Response
    {
        $curriculum->delete();

        return response()->noContent();
    }
}
