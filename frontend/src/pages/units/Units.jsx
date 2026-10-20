import { Eye, Pencil, Plus, Power, Trash2 } from 'lucide-react'
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
  const [courseFilter, setCourseFilter] = useState('')
  const [curriculumFilter, setCurriculumFilter] = useState('')
  const [courseOptions, setCourseOptions] = useState([])
  const [curriculumOptions, setCurriculumOptions] = useState([])

  // The course and curriculum filter dropdowns list their options once on
  // mount; failures are swallowed because the table itself loads
  // independently.
  useEffect(() => {
    let active = true

    api
      .get('/courses', { params: { per_page: 100 } })
      .then(({ data }) => {
        if (active) {
          setCourseOptions(data.courses)
        }
      })
      .catch(() => {})

    api
      .get('/curricula', { params: { per_page: 100 } })
      .then(({ data }) => {
        if (active) {
          setCurriculumOptions(data.curricula)
        }
      })
      .catch(() => {})

    return () => {
      active = false
    }
  }, [])

  // Unit offerings: one row per course + curriculum + code.
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
      course_id: courseFilter || undefined,
      curriculum_id: curriculumFilter || undefined,
    },
  })

  const handleToggleActive = async (unit) => {
    setTogglingId(unit.id)

    try {
      await api.put(`/units/${unit.id}`, {
        course_id: unit.course_id,
        curriculum_id: unit.curriculum_id,
        code: unit.code,
        name: unit.name,
        description: unit.description,
        modules_taught: unit.modules_taught,
        taught_hours: unit.taught_hours,
        credit_factor: unit.credit_factor,
        core: unit.core,
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
        `Delete the unit "${unit.name}" (${unit.code})? Its code becomes available again inside this course and curriculum.`,
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
      toast.error(
        error.response?.data?.message ??
          `Unable to delete unit "${unit.name}". Please try again.`,
      )
    } finally {
      setDeletingId(null)
    }
  }

  const clearFilters = () => {
    setCourseFilter('')
    setCurriculumFilter('')
    setStatusFilter('')
    setSearch('')
  }

  const hasFilters =
    courseFilter !== '' ||
    curriculumFilter !== '' ||
    statusFilter !== '' ||
    search !== ''

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Units</h1>
          <p className="text-sm text-muted-foreground">
            Every unit offering, one record per course and curriculum. The same
            code may identify different units in different courses.
          </p>
        </div>
        <Link to="/units/create" className={buttonVariants()}>
          <Plus aria-hidden="true" />
          New Unit
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
          <CardTitle>All Units</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search by code or name…"
          />
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-end gap-3">
            <div className="w-56">
              <Select
                value={courseFilter}
                onChange={(event) => setCourseFilter(event.target.value)}
                aria-label="Filter by course"
              >
                <option value="">All courses</option>
                {courseOptions.map((course) => (
                  <option key={course.id} value={course.id}>
                    {course.name}
                  </option>
                ))}
              </Select>
            </div>
            <div className="w-56">
              <Select
                value={curriculumFilter}
                onChange={(event) => setCurriculumFilter(event.target.value)}
                aria-label="Filter by curriculum"
              >
                <option value="">All curricula</option>
                {curriculumOptions.map((curriculum) => (
                  <option key={curriculum.id} value={curriculum.id}>
                    {curriculum.name}
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
          ) : units.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {hasFilters
                ? 'No units match the current filters.'
                : 'No units yet. Add the first one with the button above.'}
            </p>
          ) : (
            <>
              <div className="overflow-x-auto">
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
                      <th className="pb-3 font-medium">Course</th>
                      <th className="pb-3 font-medium">Curriculum</th>
                      <th className="pb-3 font-medium">Core</th>
                      <th className="pb-3 font-medium">Module</th>
                      <th className="pb-3 font-medium">Hours</th>
                      <th className="pb-3 font-medium">Credit</th>
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
                        <td className="max-w-48 truncate py-3 text-muted-foreground">
                          {unit.course?.name ?? '—'}
                        </td>
                        <td className="max-w-40 truncate py-3 text-muted-foreground">
                          {unit.curriculum?.name ?? '—'}
                        </td>
                        <td className="py-3">
                          <span
                            className={cn(
                              'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                              unit.core
                                ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400'
                                : 'bg-muted text-muted-foreground',
                            )}
                          >
                            {unit.core ? 'Core' : 'Elective'}
                          </span>
                        </td>
                        <td className="py-3 text-muted-foreground">
                          {unit.modules_taught ?? '—'}
                        </td>
                        <td className="py-3 text-muted-foreground">
                          {unit.taught_hours ?? '—'}
                        </td>
                        <td className="py-3 text-muted-foreground">
                          {unit.credit_factor ?? '—'}
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
              </div>

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
