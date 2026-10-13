<?php

namespace App\Http\Controllers\CertificationLevels;

use App\Http\Controllers\Controller;
use App\Http\Requests\CertificationLevels\StoreCertificationLevelRequest;
use App\Http\Requests\CertificationLevels\UpdateCertificationLevelRequest;
use App\Http\Resources\CertificationLevels\CertificationLevelResource;
use App\Models\CertificationLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CertificationLevelController extends Controller
{
    /**
     * List every certification level, grouped by exam body and sequence.
     */
    public function index(): JsonResponse
    {
        $levels = CertificationLevel::with('examBody')
            ->orderBy('exam_body_id')
            ->orderBy('sequence')
            ->get();

        return response()->json([
            'certification_levels' => CertificationLevelResource::collection($levels),
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
