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

/** Course options everywhere show "CODE — Name", matching StudentForm. */
function courseLabel(course) {
  if (!course) {
    return '—'
  }

  return course.code ? `${course.code} — ${course.name}` : course.name
}

const SPAN_LABELS = {
  session: 'Per Session',
  year: 'Per Year',
}

const amountFormat = new Intl.NumberFormat('en-US', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

function SpanBadge({ span }) {
  return (
    <span className="inline-flex rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
      {SPAN_LABELS[span] ?? span}
    </span>
  )
}

export default function CourseFeeAssignments() {
  const [deletingId, setDeletingId] = useState(null)

  const {
    items: assignments,
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
    resource: 'course_fee_assignments',
    endpoint: '/course-fee-assignments',
    errorMessage: 'Unable to load fee assignments. Please try again.',
  })

  const handleDelete = async (assignment) => {
    if (
      !window.confirm(
        `Delete the fee assignment for "${courseLabel(assignment.course)}"? The pair charges no fee until a new assignment is made.`,
      )
    ) {
      return
    }

    setDeletingId(assignment.id)

    try {
      await api.delete(`/course-fee-assignments/${assignment.id}`)

      refresh()
      toast.success(`Deleted the fee assignment for "${courseLabel(assignment.course)}"`)
    } catch {
      toast.error('Unable to delete the fee assignment. Please try again.')
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">
            Course Fee Assignments
          </h1>
          <p className="text-sm text-muted-foreground">
            The fee structure each course + curriculum pair charges, and whether
            it repeats per session or per year. A student&rsquo;s enrolment
            carries the pair, so their fee is found here.
          </p>
        </div>
        <Link to="/course-fee-assignments/create" className={buttonVariants()}>
          <Plus aria-hidden="true" />
          New Assignment
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
          <CardTitle>All Assignments</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search courses, curricula, structures…"
          />
        </CardHeader>
        <CardContent className="space-y-4">
          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : assignments.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {search
                ? `No assignments match “${search}”.`
                : 'No fee assignments yet. Create the first one with the button above.'}
            </p>
          ) : (
            <>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b text-left text-muted-foreground">
                      <th className="pb-3 font-medium">Course</th>
                      <th className="pb-3 font-medium">Curriculum</th>
                      <th className="pb-3 font-medium">Fee Structure</th>
                      <th className="pb-3 text-right font-medium">
                        Fee Amount
                      </th>
                      <SortableTh
                        column="span"
                        label="Span"
                        sort={sort}
                        order={order}
                        onSort={toggleSort}
                      />
                      <th className="pb-3 text-right font-medium">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {assignments.map((assignment) => (
                      <tr key={assignment.id}>
                        <td className="py-3 font-medium">
                          {courseLabel(assignment.course)}
                        </td>
                        <td className="py-3">
                          {assignment.curriculum?.name ?? '—'}
                        </td>
                        <td className="py-3">
                          {assignment.fee_structure?.name ?? '—'}
                        </td>
                        <td className="py-3 text-right tabular-nums">
                          {amountFormat.format(assignment.fee_structure?.total ?? 0)}
                        </td>
                        <td className="py-3">
                          <SpanBadge span={assignment.span} />
                        </td>
                        <td className="py-3">
                          <div className="flex items-center justify-end gap-2">
                            <Link
                              to={`/course-fee-assignments/${assignment.id}/edit`}
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
                              disabled={deletingId === assignment.id}
                              onClick={() => handleDelete(assignment)}
                            >
                              <Trash2 aria-hidden="true" />
                              {deletingId === assignment.id
                                ? 'Deleting…'
                                : 'Delete'}
                            </Button>
                          </div>
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
                label="assignments"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
