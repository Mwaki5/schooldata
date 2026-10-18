<?php

namespace App\Http\Controllers\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The shared search / sort / pagination pipeline behind every GET-index
 * endpoint of the API.
 *
 * Every list route answers with the same query-string contract:
 *   - `search`    free-text match over the endpoint's searchable columns
 *   - `sort`      a whitelisted sort key (never a raw column name)
 *   - `order`     `asc` (default) or `desc`
 *   - `page`      1-based page number
 *   - `per_page`  page size, default 20, hard maximum 100
 *
 * and with the same payload shape: the items under the endpoint's own
 * resource key plus a `meta` block built by `listMeta()`. Endpoint-specific
 * filters (foreign keys, status flags, …) are validated by the controller
 * and applied through the `$applyFilters` closure.
 */
trait PaginatesLists
{
    /**
     * Validate the shared list query string for an Eloquent-backed list.
     *
     * @param  array<string, string>  $sortable  sort key => database column
     * @return array{search: string, sort: string|null, order: string, page: int, per_page: int}
     */
    protected function validateListQuery(Request $request, array $sortable): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', array_keys($sortable))],
            'order' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'sort' => $validated['sort'] ?? null,
            'order' => $validated['order'] ?? 'asc',
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 20),
        ];
    }

    /**
     * Search, filter, sort and paginate an Eloquent query in one pass.
     *
     * @param  array<string, string>  $searchable  columns matched by `search`
     * @param  array<string, string>  $sortable  sort key => database column
     * @param  list<array{0: string, 1: string}>  $defaultOrder  fallback `column, direction` pairs
     * @param  (Closure(Builder): void)|null  $applyFilters  endpoint-specific where clauses
     */
    protected function paginateList(
        Request $request,
        Builder $query,
        array $searchable,
        array $sortable,
        array $defaultOrder,
        ?Closure $applyFilters = null,
    ): LengthAwarePaginator {
        $validated = $this->validateListQuery($request, $sortable);

        if ($validated['search'] !== '' && $searchable !== []) {
            $like = '%'.addcslashes($validated['search'], '%_\\').'%';

            $query->where(function (Builder $query) use ($searchable, $like): void {
                $first = true;

                foreach ($searchable as $column) {
                    $first
                        ? $query->where($column, 'like', $like)
                        : $query->orWhere($column, 'like', $like);

                    $first = false;
                }
            });
        }

        if ($applyFilters !== null) {
            $applyFilters($query);
        }

        // A requested sort key replaces the endpoint's default ordering;
        // otherwise the default ordering keeps the list stable for clients
        // (and tests) that do not ask for an explicit order.
        $orders = $validated['sort'] !== null
            ? [[$sortable[$validated['sort']], $validated['order']]]
            : $defaultOrder;

        foreach ($orders as [$column, $direction]) {
            $query->orderBy($column, $direction);
        }

        // Deterministic pagination: tie-break equal sort values by id.
        if (! $this->ordersById($orders)) {
            $query->orderBy('id', $orders[0][1] ?? 'asc');
        }

        return $query->paginate(
            $validated['per_page'],
            ['*'],
            'page',
            $validated['page'],
        );
    }

    /**
     * Search, filter, sort and paginate an in-memory list.
     *
     * Used by the fixed role catalog, which has no table to query: the
     * source order is kept unless an explicit `sort` is requested.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  list<string>  $searchable  item keys matched by `search`
     * @param  array<string, string>  $sortable  sort key => item key
     */
    protected function paginateCollection(
        Request $request,
        array $items,
        array $searchable,
        array $sortable,
    ): LengthAwarePaginator {
        $validated = $this->validateListQuery($request, $sortable);

        if ($validated['search'] !== '') {
            $needle = mb_strtolower($validated['search']);

            $items = array_values(array_filter(
                $items,
                function (array $item) use ($searchable, $needle): bool {
                    foreach ($searchable as $key) {
                        if (str_contains(mb_strtolower((string) ($item[$key] ?? '')), $needle)) {
                            return true;
                        }
                    }

                    return false;
                },
            ));
        }

        if ($validated['sort'] !== null) {
            $key = $sortable[$validated['sort']];
            $direction = $validated['order'] === 'desc' ? -1 : 1;

            usort($items, function (array $left, array $right) use ($key, $direction): int {
                $result = is_numeric($left[$key] ?? null) && is_numeric($right[$key] ?? null)
                    ? ((float) $left[$key] <=> (float) $right[$key])
                    : strnatcasecmp((string) ($left[$key] ?? ''), (string) ($right[$key] ?? ''));

                return $result * $direction;
            });
        }

        return new LengthAwarePaginator(
            array_slice(
                $items,
                ($validated['page'] - 1) * $validated['per_page'],
                $validated['per_page'],
            ),
            count($items),
            $validated['per_page'],
            $validated['page'],
            ['path' => $request->url(), 'pageName' => 'page'],
        );
    }

    /**
     * The pagination block every list response carries beside its items.
     *
     * @return array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}
     */
    protected function listMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /**
     * Whether an order list already pins `id`, so no tie-break is added.
     *
     * @param  list<array{0: string, 1: string}>  $orders
     */
    private function ordersById(array $orders): bool
    {
        foreach ($orders as [$column]) {
            if ($column === 'id') {
                return true;
            }
        }

        return false;
    }
}
