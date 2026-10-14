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

export default function AcademicYears() {
  const [deletingId, setDeletingId] = useState(null)

  const {
    items: years,
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
    resource: 'academic_years',
    endpoint: '/academic-years',
    errorMessage: 'Unable to load academic years.',
  })

  const handleDelete = async (year) => {
    if (!window.confirm(`Delete the "${year.name}" academic year?`)) {
      return
    }

    setDeletingId(year.id)

    try {
      await api.delete(`/academic-years/${year.id}`)

      refresh()
      toast.success(`Deleted "${year.name}"`)
    } catch {
      toast.error(`Unable to delete "${year.name}". Please try again.`)
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Academic Years</h1>
          <p className="text-sm text-muted-foreground">
            Manage the academic years the school runs its programmes in.
          </p>
        </div>
        <Link to="/academic-years/create" className={buttonVariants()}>
          <Plus aria-hidden="true" /> New Academic Year
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
          <CardTitle>All Academic Years</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search academic years…"
          />
        </CardHeader>
        <CardContent className="space-y-4">
          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : years.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {search
                ? `No academic years match “${search}”.`
                : 'No academic years yet. Create the first one with the button above.'}
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
                  {years.map((year) => (
                    <tr key={year.id}>
                      <td className="py-3 font-medium">{year.name}</td>
                      <td className="py-3 text-muted-foreground">
                        {formatDate(year.start_date)}
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {formatDate(year.end_date)}
                      </td>
                      <td className="py-3">
                        <StatusBadge active={year.is_active} />
                      </td>
                      <td className="py-3">
                        <div className="flex items-center justify-end gap-2">
                          <Link
                            to={`/academic-years/${year.id}/edit`}
                            className={buttonVariants({ variant: 'ghost', size: 'sm' })}>
                            <Pencil aria-hidden="true" /> Edit
                          </Link>
                          <Button
                            variant="destructive"
                            size="sm"
                            disabled={deletingId === year.id}
                            onClick={() => handleDelete(year)}
                          >
                            <Trash2 aria-hidden="true" />
                            {deletingId === year.id
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
                label="academic years"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
