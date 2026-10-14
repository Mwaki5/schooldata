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
import { Pencil, Trash2, Plus } from 'lucide-react'

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

export default function AcademicSessions() {
  const [deletingId, setDeletingId] = useState(null)

  const {
    items: sessions,
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
    resource: 'academic_sessions',
    endpoint: '/academic-sessions',
    errorMessage: 'Unable to load academic sessions.',
  })

  const handleDelete = async (session) => {
    if (!window.confirm(`Delete the "${session.name}" academic session?`)) {
      return
    }

    setDeletingId(session.id)

    try {
      await api.delete(`/academic-sessions/${session.id}`)

      refresh()
      toast.success(`Deleted "${session.name}"`)
    } catch {
      toast.error(`Unable to delete "${session.name}". Please try again.`)
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">
            Academic Sessions
          </h1>
          <p className="text-sm text-muted-foreground">
            Manage the terms and sessions within each academic year.
          </p>
        </div>
        <Link to="/academic-sessions/create" className={buttonVariants()}>
          <Plus aria-hidden="true" /> New Academic Session
        </Link>
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
          <CardTitle>All Academic Sessions</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search sessions…"
          />
        </CardHeader>
        <CardContent className="space-y-4">
          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : sessions.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {search
                ? `No sessions match “${search}”.`
                : 'No academic sessions yet. Create the first one with the button above.'}
            </p>
          ) : (
            <>
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <SortableTh
                      column="name"
                      label="Name"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <th className="pb-3 font-medium">Academic Year</th>
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
                  {sessions.map((session) => (
                    <tr key={session.id}>
                      <td className="py-3 font-medium">{session.name}</td>
                      <td className="py-3 text-muted-foreground">
                        {session.academic_year_name ?? '—'}
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {formatDate(session.start_date)}
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {formatDate(session.end_date)}
                      </td>
                      <td className="py-3">
                        <StatusBadge active={session.is_active} />
                      </td>
                      <td className="py-3">
                        <div className="flex items-center justify-end gap-2">
                          <Link
                            to={`/academic-sessions/${session.id}/edit`}
                            className={buttonVariants({
                              variant: 'ghost',
                              size: 'sm',
                            })}
                          >
                            <Pencil aria-hidden="true" /> Edit
                          </Link>
                          <Button
                            variant="destructive"
                            size="sm"
                            disabled={deletingId === session.id}
                            onClick={() => handleDelete(session)}
                          >
                            <Trash2 aria-hidden="true" />
                            {deletingId === session.id
                              ? 'Deleting…'
                              : 'Delete'}
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
                label="sessions"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
