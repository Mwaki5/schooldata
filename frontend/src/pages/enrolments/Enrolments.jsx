import { Trash2 } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Select } from '@/components/ui/select'
import { ListPagination } from '@/components/list-pagination'
import { ListSearch } from '@/components/list-search'
import { SortableTh } from '@/components/sortable-th'
import { useList } from '@/hooks/use-list'
import api from '@/lib/api'
import { cn } from '@/lib/utils'

const STATUS_STYLES = {
  ENROLLED: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
  COMPLETED: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
  DROPPED: 'bg-destructive/10 text-destructive',
}

function StatusBadge({ status }) {
  const label = status
    ? status.charAt(0).toUpperCase() + status.slice(1).toLowerCase()
    : 'Unknown'

  return (
    <span
      className={cn(
        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
        STATUS_STYLES[status] ?? 'bg-muted text-muted-foreground',
      )}
    >
      {label}
    </span>
  )
}

/** `2026-01-10T00:00:00.000000Z` or `2026-01-10` → locale date; `—` when empty. */
function formatDate(value) {
  if (!value) {
    return '—'
  }

  const date = new Date(`${String(value).slice(0, 10)}T00:00:00`)

  return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString()
}

export default function Enrolments() {
  const [deletingId, setDeletingId] = useState(null)
  const [statusFilter, setStatusFilter] = useState('')

  const {
    items: enrolments,
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
    resource: 'enrolments',
    endpoint: '/enrolments',
    errorMessage: 'Unable to load enrolments. Please try again.',
    filters: {
      status: statusFilter || undefined,
    },
  })

  const clearFilters = () => {
    setStatusFilter('')
    setSearch('')
  }

  const hasFilters = statusFilter !== '' || search !== ''

  const handleDelete = async (enrolment) => {
    if (
      !window.confirm(
        `Delete the enrolment of "${enrolment.student_name}"?`,
      )
    ) {
      return
    }

    setDeletingId(enrolment.id)

    try {
      await api.delete(`/enrolments/${enrolment.id}`)

      refresh()
      toast.success(`Deleted the enrolment of "${enrolment.student_name}"`)
    } catch {
      toast.error(
        `Unable to delete the enrolment of "${enrolment.student_name}". Please try again.`,
      )
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Enrolments</h1>
          <p className="text-sm text-muted-foreground">
            Track student enrolments across courses and curricula.
          </p>
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
          <CardTitle>All Enrolments</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search enrolments…"
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
                <option value="ENROLLED">Enrolled</option>
                <option value="COMPLETED">Completed</option>
                <option value="DROPPED">Dropped</option>
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
          ) : enrolments.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {search
                ? `No enrolments match “${search}”.`
                : hasFilters
                  ? 'No enrolments match the selected filters.'
                  : 'No enrolments yet. Enrolments are created from the Students form.'}
            </p>
          ) : (
            <>
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <th className="pb-3 font-medium">Student</th>
                    <th className="pb-3 font-medium">Course</th>
                    <th className="pb-3 font-medium">Curriculum</th>
                    <th className="pb-3 font-medium">Session</th>
                    <th className="pb-3 font-medium">Level</th>
                    <SortableTh
                      column="enrolment_date"
                      label="Enrolled On"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <SortableTh
                      column="status"
                      label="Status"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <th className="pb-3 text-right font-medium">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {enrolments.map((enrolment) => (
                    <tr key={enrolment.id}>
                      <td className="py-3">
                        <p className="font-medium">
                          {enrolment.student_name ?? '—'}
                        </p>
                        {enrolment.student_login_id && (
                          <p className="text-xs text-muted-foreground">
                            {enrolment.student_login_id}
                          </p>
                        )}
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {enrolment.course_name
                          ? `${enrolment.course_code} — ${enrolment.course_name}`
                          : '—'}
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {enrolment.curriculum_name ?? '—'}
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {enrolment.academic_session_name ?? '—'}
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {enrolment.certification_level_name ?? '—'}
                      </td>
                      <td className="py-3 whitespace-nowrap text-muted-foreground">
                        {formatDate(enrolment.enrolment_date)}
                      </td>
                      <td className="py-3">
                        <StatusBadge status={enrolment.status} />
                      </td>
                      <td className="py-3">
                        <div className="flex items-center justify-end gap-2">
                          <Button
                            variant="destructive"
                            size="sm"
                            disabled={deletingId === enrolment.id}
                            onClick={() => handleDelete(enrolment)}
                          >
                            <Trash2 aria-hidden="true" />
                            {deletingId === enrolment.id
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
                label="enrolments"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
