<?php

namespace App\Http\Controllers\Activities;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Resources\Activities\ActivityResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    use PaginatesLists;

    /**
     * List the audit trail newest first, with the shared search / sort /
     * pagination contract plus the log's own event and subject filters.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'event' => ['nullable', 'string', 'max:50'],
            'subject_type' => ['nullable', 'string', 'max:255'],
        ]);

        $paginator = $this->paginateList(
            $request,
            Activity::query()->with('causer'),
            // Activity search also spans the polymorphic causer, so the
            // whole clause is composed in `applyFilters` below.
            searchable: [],
            sortable: [
                'created_at' => 'created_at',
                'event' => 'event',
            ],
            defaultOrder: [['created_at', 'desc'], ['id', 'desc']],
            applyFilters: function (Builder $query) use ($filters, $request): void {
                $search = trim((string) $request->query('search', ''));

                if ($search !== '') {
                    $like = '%'.addcslashes($search, '%_\\').'%';

                    $query->where(function (Builder $query) use ($like): void {
                        $query->where('description', 'like', $like)
                            ->orWhereHasMorph('causer', ['*'], function (Builder $query) use ($like): void {
                                $query->where('name', 'like', $like)
                                    ->orWhere('email', 'like', $like);
                            });
                    });
                }

                $query
                    ->when($filters['event'] ?? null, fn ($query, $event) => $query->where('event', $event))
                    ->when($filters['subject_type'] ?? null, fn ($query, $subjectType) => $query->where('subject_type', $subjectType));
            },
        );

        return response()->json([
            'activities' => ActivityResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
            'filters' => [
                'events' => Activity::query()
                    ->whereNotNull('event')
                    ->distinct()
                    ->orderBy('event')
                    ->pluck('event')
                    ->values(),
                'subject_types' => Activity::query()
                    ->whereNotNull('subject_type')
                    ->distinct()
                    ->orderBy('subject_type')
                    ->pluck('subject_type')
                    ->values(),
            ],
        ]);
    }
}
