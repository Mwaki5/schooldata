import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
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

/** Scores arrive as `decimal:2` strings; render them as plain numbers. */
function formatScore(value) {
  const score = Number(value)

  return Number.isNaN(score) ? '—' : score.toFixed(2)
}

export default function ExamBodyGrades() {
  const { can } = useAuth()
  const [deletingId, setDeletingId] = useState(null)
  const [examBodyFilter, setExamBodyFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [examBodyOptions, setExamBodyOptions] = useState([])

  // The exam-body filter dropdown lists the bodies once on mount; failures
  // are swallowed because the table itself loads independently.
  useEffect(() => {
    let active = true

    api
      .get('/exam-bodies', { params: { per_page: 100 } })
      .then(({ data }) => {
        if (active) {
          setExamBodyOptions(data.exam_bodies)
        }
      })
      .catch(() => {})

    return () => {
      active = false
    }
  }, [])

  const {
    items: grades,
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
    resource: 'exam_body_grades',
    endpoint: '/exam-body-grades',
    errorMessage: 'Unable to load grade bands. Please try again.',
    filters: {
      exam_body_id: examBodyFilter || undefined,
      is_active: statusFilter || undefined,
    },
  })

  const clearFilters = () => {
    setExamBodyFilter('')
    setStatusFilter('')
    setSearch('')
  }

  const hasFilters = examBodyFilter !== '' || statusFilter !== '' || search !== ''

  const handleDelete = async (grade) => {
    if (!window.confirm(`Delete the "${grade.grade}" grade band?`)) {
      return
    }

    setDeletingId(grade.id)

    try {
      await api.delete(`/exam-body-grades/${grade.id}`)

      refresh()
      toast.success(`Deleted grade "${grade.grade}"`)
    } catch {
      toast.error(`Unable to delete grade "${grade.grade}". Please try again.`)
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">
            Exam Body Grades
          </h1>
          <p className="text-sm text-muted-foreground">
            Manage the grade bands each examination authority awards.
          </p>
        </div>
        {can('exam-body-grades.create') && (
          <Link to="/exam-body-grades/create" className={buttonVariants()}>
            <Plus aria-hidden="true" />
            New Grade Band
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
          <CardTitle>All Grade Bands</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search grades…"
          />
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-end gap-3">
            <div className="w-56">
              <Select
                value={examBodyFilter}
                onChange={(event) => setExamBodyFilter(event.target.value)}
                aria-label="Filter by exam body"
              >
                <option value="">All exam bodies</option>
                {examBodyOptions.map((examBody) => (
                  <option key={examBody.id} value={examBody.id}>
                    {examBody.name}
                  </option>
                ))}
              </Select>
            </div>
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
          ) : grades.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {hasFilters
                ? 'No grade bands match the current filters.'
                : 'No grade bands yet. Create the first one with the button above.'}
            </p>
          ) : (
            <>
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <SortableTh
                      column="grade"
                      label="Grade"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <SortableTh
                      column="grade_start"
                      label="Score Range"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <th className="pb-3 font-medium">Exam Body</th>
                    <SortableTh
                      column="sequence"
                      label="Sequence"
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
                  {grades.map((grade) => (
                    <tr key={grade.id}>
                      <td className="py-3 font-medium">{grade.grade}</td>
                      <td className="py-3 text-muted-foreground">
                        {formatScore(grade.grade_start)} –{' '}
                        {formatScore(grade.grade_end)}
                      </td>
                      <td className="max-w-56 truncate py-3 text-muted-foreground">
                        {grade.exam_body_name ?? '—'}
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {grade.sequence ?? '—'}
                      </td>
                      <td className="py-3">
                        <StatusBadge active={grade.is_active} />
                      </td>
                      <td className="py-3">
                        <div className="flex items-center justify-end gap-2">
                          {can('exam-body-grades.update') && (
                            <Link
                              to={`/exam-body-grades/${grade.id}/edit`}
                              className={buttonVariants({
                                variant: 'ghost',
                                size: 'sm',
                              })}
                            >
                              <Pencil aria-hidden="true" />
                              Edit
                            </Link>
                          )}
                          {can('exam-body-grades.delete') && (
                            <Button
                              variant="destructive"
                              size="sm"
                              disabled={deletingId === grade.id}
                              onClick={() => handleDelete(grade)}
                            >
                              <Trash2 aria-hidden="true" />
                              {deletingId === grade.id ? 'Deleting…' : 'Delete'}
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
                label="grade bands"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
