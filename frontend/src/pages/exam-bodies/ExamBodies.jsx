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

export default function ExamBodies() {
  const [deletingId, setDeletingId] = useState(null)

  const {
    items: examBodies,
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
    resource: 'exam_bodies',
    endpoint: '/exam-bodies',
    errorMessage: 'Unable to load exam bodies. Please try again.',
  })

  const handleDelete = async (examBody) => {
    if (!window.confirm(`Delete the "${examBody.code}" exam body?`)) {
      return
    }

    setDeletingId(examBody.id)

    try {
      await api.delete(`/exam-bodies/${examBody.id}`)

      refresh()
      toast.success(`Deleted "${examBody.code}"`)
    } catch {
      toast.error(
        `Unable to delete "${examBody.code}". Please try again.`,
      )
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">
            Exam Bodies
          </h1>
          <p className="text-sm text-muted-foreground">
            Manage the examination authorities recognised by the school.
          </p>
        </div>
        <Link to="/exam-bodies/create" className={buttonVariants()}>
          <Plus aria-hidden="true" />
          New Exam Body
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
          <CardTitle>All Exam Bodies</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search exam bodies…"
          />
        </CardHeader>
        <CardContent className="space-y-4">
          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : examBodies.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {search
                ? `No exam bodies match “${search}”.`
                : 'No exam bodies yet. Create the first one with the button above.'}
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
                    <th className="pb-3 font-medium">Website</th>
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
                  {examBodies.map((examBody) => (
                    <tr key={examBody.id}>
                      <td className="py-3 text-muted-foreground">
                        {examBody.code}
                      </td>
                      <td className="py-3 font-medium">{examBody.name}</td>
                      <td className="max-w-56 truncate py-3 text-muted-foreground">
                        {examBody.website ? (
                          <a
                            href={examBody.website}
                            target="_blank"
                            rel="noreferrer"
                            className="underline-offset-4 hover:underline"
                          >
                            {examBody.website}
                          </a>
                        ) : (
                          '—'
                        )}
                      </td>
                      <td className="py-3">
                        <StatusBadge active={examBody.is_active} />
                      </td>
                      <td className="py-3">
                        <div className="flex items-center justify-end gap-2">
                          <Link
                            to={`/exam-bodies/${examBody.id}/edit`}
                            className={buttonVariants({
                              variant: 'ghost',
                              size: 'sm',
                            })}
                          >
                            <Pencil aria-hidden="true" />
                            Edit
                          </Link>
                          <Button
                            variant="destructive"
                            size="sm"
                            disabled={deletingId === examBody.id}
                            onClick={() => handleDelete(examBody)}
                          >
                            <Trash2 aria-hidden="true" />
                            {deletingId === examBody.id
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
                label="exam bodies"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
