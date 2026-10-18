<?php

namespace App\Http\Controllers\AcademicSessions;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicSessions\StoreAcademicSessionRequest;
use App\Http\Requests\AcademicSessions\UpdateAcademicSessionRequest;
use App\Http\Resources\AcademicSessions\AcademicSessionResource;
use App\Models\AcademicSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AcademicSessionController extends Controller
{
    use PaginatesLists;

    /**
     * List academic sessions newest first, with the shared search / sort /
     * pagination contract and the owning academic year eagerly loaded.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $paginator = $this->paginateList(
            $request,
            AcademicSession::query()->with('academicYear:id,name'),
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
                $query
                    ->when($filters['academic_year_id'] ?? null, fn ($query, $yearId) => $query->where('academic_year_id', $yearId))
                    ->when(
                        array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                        fn ($query) => $query->where('is_active', (bool) $filters['is_active']),
                    );
            },
        );

        return response()->json([
            'academic_sessions' => AcademicSessionResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * Create a new academic session, stamping the creating user.
     */
    public function store(StoreAcademicSessionRequest $request): JsonResponse
    {
        $academicSession = AcademicSession::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'academic_session' => new AcademicSessionResource(
                $academicSession->load('academicYear:id,name')
            ),
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a single academic session.
     */
    public function show(AcademicSession $academicSession): JsonResponse
    {
        return response()->json([
            'academic_session' => new AcademicSessionResource(
                $academicSession->loadMissing('academicYear:id,name')
            ),
        ]);
    }

    /**
     * Update an existing academic session, stamping the updating user.
     */
    public function update(UpdateAcademicSessionRequest $request, AcademicSession $academicSession): JsonResponse
    {
        $academicSession->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'academic_session' => new AcademicSessionResource(
                $academicSession->refresh()->load('academicYear:id,name')
            ),
        ]);
    }

    /**
     * Delete an academic session.
     */
    public function destroy(AcademicSession $academicSession): Response
    {
        $academicSession->delete();

        return response()->noContent();
    }
}
