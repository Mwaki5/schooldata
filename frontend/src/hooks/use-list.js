import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import api, { isNotFound } from '@/lib/api'

const SEARCH_DEBOUNCE_MS = 300

/**
 * Shared data layer for every paginated list table in the app.
 *
 * Wires a list endpoint that follows the API's list contract
 * (`?search=&sort=&order=&page=&per_page=` plus endpoint-specific
 * filters, items under `resource`, pagination info under `meta`).
 *
 * - `search` is debounced before it hits the network
 * - the page snaps back to 1 whenever search, sort or filters change
 * - `toggleSort` cycles a column: none → asc → desc → none (default order)
 * - a page that empties out (e.g. after a delete) steps back automatically
 *
 * State changes are triggered from event handlers and timer callbacks, never
 * synchronously from an effect body, to satisfy the `react-hooks` v7 rules
 * (`set-state-in-effect` / `refs`).
 *
 * @param {object} options
 * @param {string} options.resource Response key holding the items (e.g. `'courses'`).
 * @param {string} options.endpoint API path (e.g. `'/courses'`).
 * @param {Record<string, unknown>} [options.filters] Endpoint-specific query filters.
 * @param {string|null} [options.defaultSort] Sort key the table starts on.
 * @param {'asc'|'desc'} [options.defaultOrder] Direction paired with `defaultSort`.
 * @param {number} [options.perPage] Rows per page (API maximum is 100).
 * @param {string} [options.errorMessage] Shown when the request fails.
 * @param {string} [options.notFoundMessage] Shown instead when the API answers 404.
 * @param {((data: Record<string, unknown>) => void)|null} [options.onData] Called with the full response (extra payloads like `filters`).
 */
export function useList({
  resource,
  endpoint,
  filters = {},
  defaultSort = null,
  defaultOrder = 'asc',
  perPage = 20,
  errorMessage = 'Unable to load records. Please try again.',
  notFoundMessage = `The ${resource} list was not found on the server. The API may be out of date.`,
  onData = null,
}) {
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [page, setPage] = useState(1)
  const [sort, setSort] = useState(defaultSort)
  const [order, setOrder] = useState(defaultOrder)
  const [refreshTick, setRefreshTick] = useState(0)

  // The filters object is rebuilt on every render by its caller; a stable
  // JSON key keeps it out of the effect dependencies while still reacting
  // to real filter changes. Refs carry the live values into the effect.
  const filterKey = useMemo(() => JSON.stringify(filters), [filters])
  const filtersRef = useRef(filters)
  const onDataRef = useRef(onData)

  // Effects run in declaration order, so this one always updates the refs
  // before the fetch effect reads them. Writing to a ref during render is
  // not allowed, hence an effect instead of assignments in the body.
  useEffect(() => {
    filtersRef.current = filters
    onDataRef.current = onData
  }, [filters, onData])

  // Debounce keystrokes so typing does not fire a request per character.
  // When the term really changes, the spinner goes up and the list restarts
  // from the first page — from the timer callback, because setState called
  // synchronously inside an effect body would trigger a cascading render.
  useEffect(() => {
    const handle = setTimeout(() => {
      const next = search.trim()
      if (next === debouncedSearch) {
        return
      }

      setLoading(true)
      setDebouncedSearch(next)
      setPage(1)
    }, SEARCH_DEBOUNCE_MS)

    return () => clearTimeout(handle)
  }, [search, debouncedSearch])

  // Filter values arrive as a prop rather than through a handler, so the
  // page restarts here. If the table sits on a later page, one superseded
  // request for that old page may go out first; the fetch effect's `active`
  // flag drops its response.
  useEffect(() => {
    const handle = setTimeout(() => {
      setPage(1)
      setLoading(true)
    }, 0)

    return () => clearTimeout(handle)
  }, [filterKey])

  useEffect(() => {
    let active = true

    const params = { page, per_page: perPage, ...filtersRef.current }

    if (debouncedSearch) {
      params.search = debouncedSearch
    }

    if (sort) {
      params.sort = sort
      params.order = order
    }

    api
      .get(endpoint, { params })
      .then(({ data }) => {
        if (!active) {
          return
        }

        const rows = data[resource] ?? []

        setItems(rows)
        setMeta(data.meta ?? null)
        setLoadError(null)
        onDataRef.current?.(data)

        // Deleting the last row of a later page would strand the table on
        // an empty page — step back instead. The spinner stays on while
        // that next page loads (promise callbacks may set state).
        if (rows.length === 0 && page > 1) {
          setLoading(true)
          setPage(page - 1)
        }
      })
      .catch((error) => {
        if (active) {
          setLoadError(isNotFound(error) ? notFoundMessage : errorMessage)
        }
      })
      .finally(() => {
        if (active) {
          setLoading(false)
        }
      })

    return () => {
      active = false
    }
  }, [
    endpoint,
    resource,
    debouncedSearch,
    filterKey,
    sort,
    order,
    page,
    perPage,
    refreshTick,
    errorMessage,
    notFoundMessage,
  ])

  /** Cycle a sortable column: none → ascending → descending → default. */
  const toggleSort = useCallback(
    (column) => {
      if (sort !== column) {
        setSort(column)
        setOrder('asc')
      } else if (order === 'asc') {
        setOrder('desc')
      } else {
        setSort(null)
      }

      // Every branch changes the criteria, so restart from page 1 with the
      // spinner up (event handlers may call setState).
      setPage(1)
      setLoading(true)
    },
    [sort, order],
  )

  const goToPage = useCallback(
    (nextPage) => {
      if (nextPage === page) {
        return
      }

      setLoading(true)
      setPage(nextPage)
    },
    [page],
  )

  const refresh = useCallback(() => {
    setLoading(true)
    setRefreshTick((tick) => tick + 1)
  }, [])

  return {
    items,
    meta,
    loading,
    loadError,
    search,
    setSearch,
    page,
    goToPage,
    sort,
    order,
    toggleSort,
    refresh,
  }
}
