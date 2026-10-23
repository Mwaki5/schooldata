import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { Button, buttonVariants } from '@/components/ui/button'
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
import { useAuth } from '@/context/AuthContext'

const STATUS_STYLES = {
  active: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
  inactive: 'bg-muted text-muted-foreground',
}

const GENDER_OPTIONS = [
  { value: 'male', label: 'Male' },
  { value: 'female', label: 'Female' },
  { value: 'other', label: 'Other' },
]

function StatusBadge({ status }) {
  const label = status
    ? status.charAt(0).toUpperCase() + status.slice(1)
    : 'Unknown'

  return (
    <span
      className={cn(
        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
        STATUS_STYLES[status] ?? STATUS_STYLES.inactive,
      )}
    >
      {label}
    </span>
  )
}

/** `2004-05-17` or a full ISO timestamp → locale date; `—` when empty. */
function formatDate(value) {
  if (!value) {
    return '—'
  }

  const date = new Date(`${String(value).slice(0, 10)}T00:00:00`)

  return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString()
}

function capitalize(value) {
  return value ? value.charAt(0).toUpperCase() + value.slice(1) : '—'
}

export default function Students() {
  const { can } = useAuth()
  const [deletingId, setDeletingId] = useState(null)
  const [statusFilter, setStatusFilter] = useState('')
  const [genderFilter, setGenderFilter] = useState('')

  const {
    items: students,
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
    resource: 'students',
    endpoint: '/students',
    errorMessage: 'Unable to load students. Please try again.',
    filters: {
      status: statusFilter || undefined,
      gender: genderFilter || undefined,
    },
  })

  const clearFilters = () => {
    setStatusFilter('')
    setGenderFilter('')
    setSearch('')
  }

  const hasFilters = statusFilter !== '' || genderFilter !== '' || search !== ''

  const handleDelete = async (student) => {
    if (!window.confirm(`Delete the "${student.name}" student record?`)) {
      return
    }

    setDeletingId(student.id)

    try {
      await api.delete(`/students/${student.id}`)

      refresh()
      toast.success(`Deleted "${student.name}"`)
    } catch {
      toast.error(`Unable to delete "${student.name}". Please try again.`)
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Students</h1>
          <p className="text-sm text-muted-foreground">
            Manage student admissions and their profile records.
          </p>
        </div>
        {can('students.create') && (
          <Link to="/students/create" className={buttonVariants()}>
            <Plus aria-hidden="true" />
            New Student
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
          <CardTitle>All Students</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search students…"
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
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </Select>
            </div>
            <div className="w-48">
              <Select
                value={genderFilter}
                onChange={(event) => setGenderFilter(event.target.value)}
                aria-label="Filter by gender"
              >
                <option value="">All genders</option>
                {GENDER_OPTIONS.map((gender) => (
                  <option key={gender.value} value={gender.value}>
                    {gender.label}
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
          ) : students.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {search
                ? `No students match “${search}”.`
                : hasFilters
                  ? 'No students match the selected filters.'
                  : 'No students yet. Create the first one with the button above.'}
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
                    <th className="pb-3 font-medium">Admission No</th>
                    <SortableTh
                      column="gender"
                      label="Gender"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <th className="pb-3 font-medium">Phone</th>
                    <SortableTh
                      column="status"
                      label="Status"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <SortableTh
                      column="created_at"
                      label="Joined"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <th className="pb-3 text-right font-medium">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {students.map((student) => (
                    <tr key={student.id}>
                      <td className="py-3 font-medium">{student.name}</td>
                      <td className="py-3 text-muted-foreground">
                        {student.login_id ?? '—'}
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {capitalize(student.gender)}
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {student.phone ?? '—'}
                      </td>
                      <td className="py-3">
                        <StatusBadge status={student.status} />
                      </td>
                      <td className="py-3 whitespace-nowrap text-muted-foreground">
                        {formatDate(student.created_at)}
                      </td>
                      <td className="py-3">
                        <div className="flex items-center justify-end gap-2">
                          {can('students.update') && (
                            <Link
                              to={`/students/${student.id}/edit`}
                              className={buttonVariants({
                                variant: 'ghost',
                                size: 'sm',
                              })}
                            >
                              <Pencil aria-hidden="true" />
                              Edit
                            </Link>
                          )}
                          {can('students.delete') && (
                            <Button
                              variant="destructive"
                              size="sm"
                              disabled={deletingId === student.id}
                              onClick={() => handleDelete(student)}
                            >
                              <Trash2 aria-hidden="true" />
                              {deletingId === student.id
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
                label="students"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
