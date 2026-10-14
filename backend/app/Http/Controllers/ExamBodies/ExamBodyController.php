<?php

namespace App\Http\Controllers\ExamBodies;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExamBodies\StoreExamBodyRequest;
use App\Http\Requests\ExamBodies\UpdateExamBodyRequest;
use App\Http\Resources\ExamBodies\ExamBodyResource;
use App\Models\ExamBody;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExamBodyController extends Controller
{
    use PaginatesLists;

    /**
     * List exam bodies ordered by name, with the shared search / sort /
     * pagination contract. The certification level picker searches this
     * endpoint, so it uses the same `search` parameter as the table.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'is_active' => ['nullable', 'boolean'],
        ]);

        $paginator = $this->paginateList(
            $request,
            ExamBody::query(),
            searchable: ['name', 'code'],
            sortable: [
                'name' => 'name',
                'code' => 'code',
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
            'exam_bodies' => ExamBodyResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * Create a new exam body, stamping the creating user.
     */
    public function store(StoreExamBodyRequest $request): JsonResponse
    {
        $examBody = ExamBody::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'exam_body' => new ExamBodyResource($examBody),
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a single exam body.
     */
    public function show(ExamBody $examBody): JsonResponse
    {
        return response()->json([
            'exam_body' => new ExamBodyResource($examBody),
        ]);
    }

    /**
     * Update an existing exam body, stamping the updating user.
     */
    public function update(UpdateExamBodyRequest $request, ExamBody $examBody): JsonResponse
    {
        $examBody->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'exam_body' => new ExamBodyResource($examBody->refresh()),
        ]);
    }

    /**
     * Soft delete an exam body.
     */
    public function destroy(ExamBody $examBody): Response
    {
        $examBody->delete();

        return response()->noContent();
    }
}
