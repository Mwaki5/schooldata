import { Eye, Link2, Pencil, Plus, Power, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { Button, buttonVariants } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Select } from '@/components/ui/select'
import { ListPagination } from '@/components/list-pagination'
import { ListSearch } from '@/components/list-search'
import { SortableTh } from '@/components/sortable-th'
import { useList } from '@/hooks/use-list'
import api from '@/lib/api'
import { cn } from '@/lib/utils'

function StatusBadge({ active }) {
  return (
    <span
      className={cn(
        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
        active
          ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
          : 'bg-muted text-muted-foreground',
      )}
    >
      {active ? 'Active' : 'Inactive'}
    </span>
  )
}

export default function Units() {
  const [deletingId, setDeletingId] = useState(null)
  const [togglingId, setTogglingId] = useState(null)
  const [statusFilter, setStatusFilter] = useState('')

  // The unit catalogue: one row per master unit, no matter how many
  // courses or curricula it is delivered under.
  const {
    items: units,
    meta,
    loading,
    loadError,
    search,
    setSearch,
    goToPage,
    sort,
    order,
    toggleSort,
    refresh,
  } = useList({
    resource: 'units',
    endpoint: '/units',
    errorMessage: 'Unable to load units. Please try again.',
    notFoundMessage:
      'The units list was not found on the server. The API may be out of date.',
    filters: {
      is_active: statusFilter || undefined,
    },
  })

  const handleToggleActive = async (unit) => {
    setTogglingId(unit.id)

    try {
      await api.put(`/units/${unit.id}`, {
        code: unit.code,
        name: unit.name,
        description: unit.description,
        is_active: !unit.is_active,
      })

      toast.success(
        `Unit "${unit.name}" ${unit.is_active ? 'deactivated' : 'activated'}`,
      )
      refresh()
    } catch (error) {
      toast.error(
        error.response?.data?.message ?? 'Unable to update the unit. Please try again.',
      )
    } finally {
      setTogglingId(null)
    }
  }

  const handleDelete = async (unit) => {
    if (
      !window.confirm(
        `Delete the unit "${unit.name}"? It can only be deleted once it is no longer assigned to any course.`,
      )
    ) {
      return
    }

    setDeletingId(unit.id)

    try {
      await api.delete(`/units/${unit.id}`)

      refresh()
      toast.success(`Deleted unit "${unit.name}"`)
    } catch (error) {
      // The backend refuses while assignments exist and explains why.
      toast.error(
        error.response?.data?.message ??
          `Unable to delete unit "${unit.name}". Please try again.`,
      )
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Units</h1>
          <p className="text-sm text-muted-foreground">
            The reusable unit catalogue — one code per unit, assignable to any
            number of courses and curricula, each with its own teaching load.
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Link
            to="/units/assign"
            className={buttonVariants({ variant: 'outline' })}
          >
            <Link2 aria-hidden="true" />
            Assign Existing Unit
          </Link>
          <Link to="/units/create" className={buttonVariants()}>
            <Plus aria-hidden="true" />
            New Unit
          </Link>
        </div>
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
          <CardTitle>All Units</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search by code or name…"
          />
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-end gap-3">
            <div className="w-48">
              <Select
                value={statusFilter}
                onChange={(event) => setStatusFilter(event.target.value)}
                aria-label="Filter by status"
              >
                <option value="">All statuses</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
              </Select>
            </div>
            {(statusFilter !== '' || search !== '') && (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => {
                  setStatusFilter('')
                  setSearch('')
                }}
              >
                Clear filters
              </Button>
            )}
          </div>

          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : units.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {statusFilter !== '' || search !== ''
                ? 'No units match the current filters.'
                : 'No units yet. Create the first one with the button above.'}
            </p>
          ) : (
            <>
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <SortableTh
                      column="code"
                      label="Code"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <SortableTh
                      column="name"
                      label="Name"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <th className="pb-3 font-medium">Description</th>
                    <SortableTh
                      column="assignments_count"
                      label="Assignments"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <SortableTh
                      column="is_active"
                      label="Status"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <th className="pb-3 text-right font-medium">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {units.map((unit) => (
                    <tr key={unit.id}>
                      <td className="py-3 font-medium">
                        <Link
                          to={`/units/${unit.id}`}
                          className="hover:underline"
                        >
                          {unit.code}
                        </Link>
                      </td>
                      <td className="py-3">
                        <Link
                          to={`/units/${unit.id}`}
                          className="font-medium hover:underline"
                        >
                          {unit.name}
                        </Link>
                      </td>
                      <td className="max-w-64 truncate py-3 text-muted-foreground">
                        {unit.description ?? '—'}
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {unit.assignments_count}
                      </td>
                      <td className="py-3">
                        <StatusBadge active={unit.is_active} />
                      </td>
                      <td className="py-3">
                        <div className="flex items-center justify-end gap-1">
                          <Link
                            to={`/units/${unit.id}`}
                            className={buttonVariants({
                              variant: 'ghost',
                              size: 'sm',
                            })}
                            title="View unit"
                            aria-label={`View unit ${unit.name}`}
                          >
                            <Eye aria-hidden="true" />
                          </Link>
                          <Link
                            to={`/units/${unit.id}/edit`}
                            className={buttonVariants({
                              variant: 'ghost',
                              size: 'sm',
                            })}
                            title="Edit unit"
                            aria-label={`Edit unit ${unit.name}`}
                          >
                            <Pencil aria-hidden="true" />
                          </Link>
                          <Link
                            to={`/units/${unit.id}/assign`}
                            className={buttonVariants({
                              variant: 'ghost',
                              size: 'sm',
                            })}
                            title="Assign to courses"
                            aria-label={`Assign unit ${unit.name} to courses`}
                          >
                            <Link2 aria-hidden="true" />
                          </Link>
                          <Button
                            variant="ghost"
                            size="sm"
                            disabled={togglingId === unit.id}
                            title={
                              unit.is_active ? 'Deactivate unit' : 'Activate unit'
                            }
                            aria-label={`${unit.is_active ? 'Deactivate' : 'Activate'} unit ${unit.name}`}
                            onClick={() => handleToggleActive(unit)}
                          >
                            <Power aria-hidden="true" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            disabled={deletingId === unit.id}
                            title="Delete unit"
                            aria-label={`Delete unit ${unit.name}`}
                            onClick={() => handleDelete(unit)}
                          >
                            <Trash2 aria-hidden="true" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>

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
