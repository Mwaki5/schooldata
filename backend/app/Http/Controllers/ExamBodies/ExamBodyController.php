<?php

namespace App\Http\Controllers\ExamBodies;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExamBodies\StoreExamBodyRequest;
use App\Http\Requests\ExamBodies\UpdateExamBodyRequest;
use App\Http\Resources\ExamBodies\ExamBodyResource;
use App\Models\ExamBody;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExamBodyController extends Controller
{
    /**
     * List exam bodies, ordered by name, optionally filtered by search.
     *
     * The certification level picker searches this endpoint, so a single
     * capped page of matches is ever returned.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $like = '%'.addcslashes($search, '%_\\').'%';

        $examBodies = ExamBody::query()
            ->when($search !== '', function ($query) use ($like) {
                $query->where(function ($query) use ($like) {
                    $query->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like);
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get();

        return response()->json([
            'exam_bodies' => ExamBodyResource::collection($examBodies),
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
