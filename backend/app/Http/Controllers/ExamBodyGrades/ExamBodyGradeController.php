<?php

namespace App\Http\Controllers\ExamBodyGrades;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExamBodyGrades\StoreExamBodyGradeRequest;
use App\Http\Requests\ExamBodyGrades\UpdateExamBodyGradeRequest;
use App\Http\Resources\ExamBodyGrades\ExamBodyGradeResource;
use App\Models\ExamBodyGrade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExamBodyGradeController extends Controller
{
    use PaginatesLists;

    /**
     * List grade bands grouped by exam body and sequence, with the shared
     * search / sort / pagination contract.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'exam_body_id' => ['nullable', 'integer', 'exists:exam_bodies,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $paginator = $this->paginateList(
            $request,
            ExamBodyGrade::query()->with('examBody'),
            searchable: ['grade', 'remark'],
            sortable: [
                'grade' => 'grade',
                'grade_start' => 'grade_start',
                'grade_end' => 'grade_end',
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
            'exam_body_grades' => ExamBodyGradeResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * Create a new grade band, stamping the creating user.
     */
    public function store(StoreExamBodyGradeRequest $request): JsonResponse
    {
        $grade = ExamBodyGrade::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'exam_body_grade' => new ExamBodyGradeResource($grade->load('examBody')),
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a single grade band.
     */
    public function show(ExamBodyGrade $examBodyGrade): JsonResponse
    {
        return response()->json([
            'exam_body_grade' => new ExamBodyGradeResource($examBodyGrade->load('examBody')),
        ]);
    }

    /**
     * Update an existing grade band, stamping the updating user.
     */
    public function update(UpdateExamBodyGradeRequest $request, ExamBodyGrade $examBodyGrade): JsonResponse
    {
        $examBodyGrade->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'exam_body_grade' => new ExamBodyGradeResource($examBodyGrade->refresh()->load('examBody')),
        ]);
    }

    /**
     * Soft delete a grade band.
     */
    public function destroy(ExamBodyGrade $examBodyGrade): Response
    {
        $examBodyGrade->delete();

        return response()->noContent();
    }
}
