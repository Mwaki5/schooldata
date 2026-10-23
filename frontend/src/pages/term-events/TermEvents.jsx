import { Plus, Pencil, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { Button, buttonVariants } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ListPagination } from '@/components/list-pagination'
import { ListSearch } from '@/components/list-search'
import { SortableTh } from '@/components/sortable-th'
import { useList } from '@/hooks/use-list'
import api from '@/lib/api'
import { cn } from '@/lib/utils'
import { useAuth } from '@/context/AuthContext'

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

function formatDate(value) {
  if (!value) {
    return '—'
  }

  return new Date(`${value}T00:00:00`).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

export default function TermEvents() {
  const { can } = useAuth()
  const [deletingId, setDeletingId] = useState(null)

  const {
    items: events,
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
    resource: 'term_events',
    endpoint: '/term-events',
    errorMessage: 'Unable to load term events.',
  })

  const handleDelete = async (event) => {
    if (!window.confirm(`Delete the "${event.name}" term event?`)) {
      return
    }

    setDeletingId(event.id)

    try {
      await api.delete(`/term-events/${event.id}`)

      refresh()
      toast.success(`Deleted "${event.name}"`)
    } catch {
      toast.error(`Unable to delete "${event.name}". Please try again.`)
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Term Events</h1>
          <p className="text-sm text-muted-foreground">
            Manage the events tied to each academic session — exams, breaks
            and registration weeks.
          </p>
        </div>
        {can('term-events.create') && (
          <Link to="/term-events/create" className={buttonVariants()}>
            <Plus aria-hidden="true" /> New Term Event
          </Link>
        )}
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
          <CardTitle>All Term Events</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search term events…"
          />
        </CardHeader>
        <CardContent className="space-y-4">
          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : events.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {search
                ? `No term events match “${search}”.`
                : 'No term events yet. Create the first one with the button above.'}
            </p>
          ) : (
            <>
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <SortableTh
                      column="name"
                      label="Event"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <th className="pb-3 font-medium">Session</th>
                    <SortableTh
                      column="start_date"
                      label="Start"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <SortableTh
                      column="end_date"
                      label="End"
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
                  {events.map((event) => (
                    <tr key={event.id}>
                      <td className="py-3 font-medium">{event.name}</td>
                      <td className="py-3 text-muted-foreground">
                        {event.academic_session_name ?? '—'}
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {formatDate(event.start_date)}
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {formatDate(event.end_date)}
                      </td>
                      <td className="py-3">
                        <StatusBadge active={event.is_active} />
                      </td>
                      <td className="py-3">
                        <div className="flex items-center justify-end gap-2">
                          {can('term-events.update') && (
                            <Link
                              to={`/term-events/${event.id}/edit`}
                              className={buttonVariants({ variant: 'ghost', size: 'sm' })}>
                              <Pencil aria-hidden="true" /> Edit
                            </Link>
                          )}
                          {can('term-events.delete') && (
                            <Button
                              variant="destructive"
                              size="sm"
                              disabled={deletingId === event.id}
                              onClick={() => handleDelete(event)}
                            >
                              <Trash2 aria-hidden="true" />
                              {deletingId === event.id
                                ? 'Deleting…'
                                : 'Delete'}
                            </Button>
                          )}
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
                label="term events"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
