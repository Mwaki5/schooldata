import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Select } from '@/components/ui/select'
import { ListPagination } from '@/components/list-pagination'
import { ListSearch } from '@/components/list-search'
import { useList } from '@/hooks/use-list'

/**
 * The signed-in student's own marks: one row per registered unit that
 * carries at least one recorded mark, laid out like the class marksheet
 * but scoped to their own papers. The module options ride along with
 * every response, so the select always offers every module they have
 * registered units in, even while rows are filtered down.
 */
export default function Grades() {
  const [moduleFilter, setModuleFilter] = useState('')
  const [moduleOptions, setModuleOptions] = useState([])

  const {
    items: rows,
    meta,
    loading,
    loadError,
    search,
    setSearch,
    goToPage,
  } = useList({
    resource: 'rows',
    endpoint: '/me/grades',
    errorMessage: 'Unable to load your grades. Please try again.',
    notFoundMessage:
      'Your grades were not found on the server. The API may be out of date.',
    filters: {
      module: moduleFilter || undefined,
    },
    onData: (data) => setModuleOptions(data.modules ?? []),
  })

  const clearFilters = () => {
    setModuleFilter('')
    setSearch('')
  }

  const hasFilters = moduleFilter !== '' || search !== ''

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Grades</h1>
        <p className="text-sm text-muted-foreground">
          Your marks for every unit you have registered. A unit appears here once
          at least one mark has been recorded for it.
        </p>
      </div>

      {loadError && (
        <div
          role="alert"
          className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
        >
          {loadError}
        </div>
      )}

      <Card>
        <CardHeader className="flex flex-row items-center justify-between gap-4">
          <CardTitle>My Marks</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search by unit code or name…"
          />
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-end gap-3">
            <div className="w-56">
              <Select
                value={moduleFilter}
                onChange={(event) => setModuleFilter(event.target.value)}
                aria-label="Filter by module"
              >
                <option value="">All modules</option>
                {moduleOptions.map((module) => (
                  <option key={module} value={module}>
                    Module {module}
                  </option>
                ))}
              </Select>
            </div>
            {hasFilters && (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={clearFilters}
              >
                Clear filters
              </Button>
            )}
          </div>

          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : rows.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {hasFilters
                ? 'No units match the current filters.'
                : 'No marks recorded yet. Your grades appear here once your trainers record marks for the units you registered.'}
            </p>
          ) : (
            <>
              <div className="overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[1100px] text-sm">
                  <thead>
                    <tr className="border-b text-left text-xs uppercase tracking-wider text-muted-foreground">
                      <th rowSpan={2} scope="col" className="whitespace-nowrap px-3 py-2 font-semibold">
                        S/N
                      </th>
                      <th rowSpan={2} scope="col" className="whitespace-nowrap px-3 py-2 font-semibold">
                        Session
                      </th>
                      <th rowSpan={2} scope="col" className="whitespace-nowrap px-3 py-2 font-semibold">
                        Unit
                      </th>
                      <th rowSpan={2} scope="col" className="whitespace-nowrap px-3 py-2 font-semibold">
                        Module
                      </th>
                      <th
                        colSpan={4}
                        scope="colgroup"
                        className="whitespace-nowrap border-l bg-muted px-3 py-2 text-center font-semibold"
                      >
                        Theory Marks (100%)
                      </th>
                      <th
                        colSpan={4}
                        scope="colgroup"
                        className="whitespace-nowrap border-l bg-orange-100 px-3 py-2 text-center font-semibold text-orange-900 dark:bg-orange-950/50 dark:text-orange-300"
                      >
                        Practical Marks (100%)
                      </th>
                    </tr>
                    <tr className="border-b text-left text-xs uppercase tracking-wider text-muted-foreground">
                      <th scope="col" className="whitespace-nowrap border-l bg-muted/60 px-3 py-2 text-center font-semibold">
                        CAM 1
                      </th>
                      <th scope="col" className="whitespace-nowrap bg-muted/60 px-3 py-2 text-center font-semibold">
                        CAM 2
                      </th>
                      <th scope="col" className="whitespace-nowrap bg-muted/60 px-3 py-2 text-center font-semibold">
                        CAM 3
                      </th>
                      <th
                        scope="col"
                        className="whitespace-nowrap px-3 py-2 text-center font-semibold text-red-600 dark:text-red-400"
                      >
                        AVERAGE
                      </th>
                      <th scope="col" className="whitespace-nowrap border-l bg-orange-100/60 px-3 py-2 text-center font-semibold text-orange-900 dark:bg-orange-950/40 dark:text-orange-300">
                        PRACT 1
                      </th>
                      <th scope="col" className="whitespace-nowrap bg-orange-100/60 px-3 py-2 text-center font-semibold text-orange-900 dark:bg-orange-950/40 dark:text-orange-300">
                        PRACT 2
                      </th>
                      <th scope="col" className="whitespace-nowrap bg-orange-100/60 px-3 py-2 text-center font-semibold text-orange-900 dark:bg-orange-950/40 dark:text-orange-300">
                        PRACT 3
                      </th>
                      <th
                        scope="col"
                        className="whitespace-nowrap px-3 py-2 text-center font-semibold text-red-600 dark:text-red-400"
                      >
                        AVERAGE
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((row, index) => (
                      <tr key={row.registration_id} className="border-b last:border-b-0">
                        <td className="whitespace-nowrap px-3 py-2 text-muted-foreground">
                          {index + 1}
                        </td>
                        <td className="whitespace-nowrap px-3 py-2">
                          {row.session.name ?? '—'}
                        </td>
                        <td className="whitespace-nowrap px-3 py-2">
                          <span className="font-medium">{row.unit.code ?? '—'}</span>
                          <span className="ml-2">{row.unit.name}</span>
                          <span className="block text-xs text-muted-foreground">
                            {row.course ?? '—'}
                          </span>
                        </td>
                        <td className="whitespace-nowrap px-3 py-2">
                          {row.modules_taught === null
                            ? '—'
                            : `Module ${row.modules_taught}`}
                        </td>
                        {row.theory.map((mark, slot) => (
                          <td
                            key={`theory-${slot}`}
                            className={`whitespace-nowrap px-3 py-2 text-center tabular-nums ${
                              slot === 0 ? 'border-l' : ''
                            }`}
                          >
                            {mark ?? '—'}
                          </td>
                        ))}
                        <td className="whitespace-nowrap px-3 py-2 text-center font-semibold tabular-nums text-red-600 dark:text-red-400">
                          {row.theory_average ?? '—'}
                        </td>
                        {row.practical.map((mark, slot) => (
                          <td
                            key={`practical-${slot}`}
                            className={`whitespace-nowrap px-3 py-2 text-center tabular-nums ${
                              slot === 0 ? 'border-l' : ''
                            }`}
                          >
                            {mark ?? '—'}
                          </td>
                        ))}
                        <td className="whitespace-nowrap px-3 py-2 text-center font-semibold tabular-nums text-red-600 dark:text-red-400">
                          {row.practical_average ?? '—'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <ListPagination
                meta={meta}
                loading={loading}
                onPageChange={goToPage}
                label="units"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
