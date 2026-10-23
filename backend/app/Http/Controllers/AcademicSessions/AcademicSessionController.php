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
use Illuminate\Support\Facades\DB;

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
     * The academic-session picker: any signed-in console may read it,
     * because "pick a session" is where permission-scoped pages start
     * (grading console, marksheet, the enrolment and event forms) and
     * their own permissions cannot apply until that first choice is
     * made. `academic-sessions.view` guards the management screen, not
     * the calendar, so this route carries no permission of its own.
     */
    public function options(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $sessions = AcademicSession::query()
            ->with('academicYear:id,name')
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', (bool) $filters['is_active']),
            )
            ->when($filters['search'] ?? '', fn ($query, $term) => $query->where('name', 'like', "%{$term}%"))
            ->orderBy('start_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'academic_sessions' => AcademicSessionResource::collection($sessions),
        ]);
    }

    /**
     * Create a new academic session, stamping the creating user. Only one
     * session may be active at a time, so activating this one retires the
     * others.
     */
    public function store(StoreAcademicSessionRequest $request): JsonResponse
    {
        $academicSession = DB::transaction(function () use ($request): AcademicSession {
            // Lock every session row so concurrent activations serialise
            // and can never leave two (or zero) active sessions behind.
            AcademicSession::query()->lockForUpdate()->get();

            $academicSession = AcademicSession::create([
                ...$request->validated(),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            if ($academicSession->is_active) {
                $this->deactivateOtherSessions($academicSession, $request->user()->id);
            }

            return $academicSession;
        });

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
     * Only one session may be active at a time, so activating this one
     * retires the others.
     */
    public function update(UpdateAcademicSessionRequest $request, AcademicSession $academicSession): JsonResponse
    {
        DB::transaction(function () use ($request, $academicSession): void {
            AcademicSession::query()->lockForUpdate()->get();

            $academicSession->update([
                ...$request->validated(),
                'updated_by' => $request->user()->id,
            ]);

            if ($academicSession->is_active) {
                $this->deactivateOtherSessions($academicSession, $request->user()->id);
            }
        });

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

    /**
     * Keep at most one active academic session: activating this one turns
     * every other session off, stamped with the acting user.
     */
    private function deactivateOtherSessions(AcademicSession $academicSession, int $userId): void
    {
        AcademicSession::query()
            ->whereKeyNot($academicSession->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'updated_by' => $userId,
            ]);
    }
}
