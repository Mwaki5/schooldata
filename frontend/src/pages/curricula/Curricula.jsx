import { Pencil, Plus, Trash2 } from 'lucide-react'
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

export default function Curricula() {
  const { can } = useAuth()
  const [deletingId, setDeletingId] = useState(null)

  const {
    items: curricula,
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
    resource: 'curricula',
    endpoint: '/curricula',
    errorMessage: 'Unable to load curricula. Please try again.',
  })

  const handleDelete = async (curriculum) => {
    if (!window.confirm(`Delete the "${curriculum.name}" curriculum?`)) {
      return
    }

    setDeletingId(curriculum.id)

    try {
      await api.delete(`/curricula/${curriculum.id}`)

      refresh()
      toast.success(`Deleted "${curriculum.name}"`)
    } catch {
      toast.error(
        `Unable to delete "${curriculum.name}". Please try again.`,
      )
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Curriculum</h1>
          <p className="text-sm text-muted-foreground">
            Manage the course versions published by each exam body.
          </p>
        </div>
        {can('curricula.create') && (
          <Link to="/curricula/create" className={buttonVariants()}>
            <Plus aria-hidden="true" />
            New Curriculum
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
          <CardTitle>All Curricula</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search curricula…"
          />
        </CardHeader>
        <CardContent className="space-y-4">
          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : curricula.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {search
                ? `No curricula match “${search}”.`
                : 'No curricula yet. Create the first one with the button above.'}
            </p>
          ) : (
            <>
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <th className="pb-3 font-medium">Exam Body</th>
                    <SortableTh
                      column="name"
                      label="Name"
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
                  {curricula.map((curriculum) => (
                    <tr key={curriculum.id}>
                      <td className="py-3 text-muted-foreground">
                        {curriculum.exam_body_name}
                      </td>
                      <td className="py-3 font-medium">{curriculum.name}</td>
                      <td className="py-3">
                        <StatusBadge active={curriculum.is_active} />
                      </td>
                      <td className="py-3">
                        <div className="flex items-center justify-end gap-2">
                          {can('curricula.update') && (
                            <Link
                              to={`/curricula/${curriculum.id}/edit`}
                              className={buttonVariants({
                                variant: 'ghost',
                                size: 'sm',
                              })}
                            >
                              <Pencil aria-hidden="true" />
                              Edit
                            </Link>
                          )}
                          {can('curricula.delete') && (
                            <Button
                              variant="destructive"
                              size="sm"
                              disabled={deletingId === curriculum.id}
                              onClick={() => handleDelete(curriculum)}
                            >
                              <Trash2 aria-hidden="true" />
                              {deletingId === curriculum.id
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
                label="curricula"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
