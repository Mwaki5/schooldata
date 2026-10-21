import { Pencil, Plus, Power, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { Button, buttonVariants } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Select } from '@/components/ui/select'
import { ListPagination } from '@/components/list-pagination'
import { ListSearch } from '@/components/list-search'
import { SortableTh } from '@/components/sortable-th'
import { UnitTypeBadge } from '@/components/unit-type-badge'
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

/**
 * The Course Units catalogue: every unit-course-curriculum link with its
 * teaching load, editable in place. Units themselves live under Units;
 * this page only manages where and how a unit is taught.
 */
export default function CourseUnits() {
  const [deletingId, setDeletingId] = useState(null)
  const [togglingId, setTogglingId] = useState(null)
  const [unitFilter, setUnitFilter] = useState('')
  const [courseFilter, setCourseFilter] = useState('')
  const [curriculumFilter, setCurriculumFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [unitOptions, setUnitOptions] = useState([])
  const [courseOptions, setCourseOptions] = useState([])
  const [curriculumOptions, setCurriculumOptions] = useState([])

  // The filter dropdowns list their options once on mount; failures are
  // swallowed because the table itself loads independently.
  useEffect(() => {
    let active = true

    api
      .get('/units', { params: { per_page: 100 } })
      .then(({ data }) => {
        if (active) {
          setUnitOptions(data.units)
        }
      })
      .catch(() => {})

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

  const {
    items: courseUnits,
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
    resource: 'course_units',
    endpoint: '/course-units',
    errorMessage: 'Unable to load course units. Please try again.',
    notFoundMessage:
      'The course units list was not found on the server. The API may be out of date.',
    filters: {
      unit_id: unitFilter || undefined,
      course_id: courseFilter || undefined,
      curriculum_id: curriculumFilter || undefined,
      is_active:
        statusFilter === ''
          ? undefined
          : statusFilter === 'active'
            ? '1'
            : '0',
    },
  })

  const handleToggleActive = async (link) => {
    setTogglingId(link.id)

    try {
      await api.put(`/course-units/${link.id}`, {
        is_active: !link.is_active,
      })

      refresh()
      toast.success(
        `${link.unit?.code ?? 'Unit'} is now ${link.is_active ? 'inactive' : 'active'}`,
      )
    } catch (error) {
      toast.error(
        error.response?.data?.message ??
          'Unable to change the status. Please try again.',
      )
    } finally {
      setTogglingId(null)
    }
  }

  const handleDelete = async (link) => {
    if (
      !window.confirm(
        `Unlink ${link.unit?.code ?? 'this unit'} (${link.unit?.name ?? ''}) from ${link.course?.name ?? 'this course'}${link.curriculum ? ` — ${link.curriculum.name}` : ''}? The unit itself stays in the catalogue.`,
      )
    ) {
      return
    }

    setDeletingId(link.id)

    try {
      await api.delete(`/course-units/${link.id}`)

      refresh()
      toast.success('Course unit unlinked')
    } catch (error) {
      toast.error(
        error.response?.data?.message ??
          'Unable to unlink the course unit. Please try again.',
      )
    } finally {
      setDeletingId(null)
    }
  }

  const clearFilters = () => {
    setUnitFilter('')
    setCourseFilter('')
    setCurriculumFilter('')
    setStatusFilter('')
    setSearch('')
  }

  const hasFilters =
    unitFilter !== '' ||
    courseFilter !== '' ||
    curriculumFilter !== '' ||
    statusFilter !== '' ||
    search !== ''

  return (
    <div className="mx-auto max-w-7xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">
            Course Units
          </h1>
          <p className="text-sm text-muted-foreground">
            Assign catalogue units to a course and curriculum with their
            teaching load, credit factor and role.
          </p>
        </div>
        <Link to="/course-units/create" className={buttonVariants()}>
          <Plus aria-hidden="true" />
          Link Unit
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
          <CardTitle>All Links</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search by unit code or name…"
          />
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-end gap-3">
            <div className="w-52">
              <Select
                value={unitFilter}
                onChange={(event) => setUnitFilter(event.target.value)}
                aria-label="Filter by unit"
              >
                <option value="">All units</option>
                {unitOptions.map((unit) => (
                  <option key={unit.id} value={unit.id}>
                    {unit.code} — {unit.name}
                  </option>
                ))}
              </Select>
            </div>
            <div className="w-52">
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
            <div className="w-52">
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
            <div className="w-40">
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
          ) : courseUnits.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {hasFilters
                ? 'No course units match the current filters.'
                : 'No units assigned to courses yet. Link the first one with the button above.'}
            </p>
          ) : (
            <>
              <div className="overflow-x-auto">
                <table className="w-full min-w-[1024px] text-sm">
                  <thead>
                    <tr className="border-b text-left text-muted-foreground">
                      <SortableTh
                        column="units.code"
                        label="Unit"
                        sort={sort}
                        order={order}
                        onSort={toggleSort}
                      />
                      <th className="pb-3 font-medium whitespace-nowrap">Course</th>
                      <th className="pb-3 font-medium whitespace-nowrap">Curriculum</th>
                      <SortableTh
                        column="modules_taught"
                        label="Module"
                        sort={sort}
                        order={order}
                        onSort={toggleSort}
                      />
                      <th className="pb-3 font-medium whitespace-nowrap">Allocated Hours</th>
                      <th className="pb-3 font-medium whitespace-nowrap">Credit Factor</th>
                      <th className="pb-3 font-medium whitespace-nowrap">
                        Unit Type
                      </th>
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
                    {courseUnits.map((link) => (
                      <tr key={link.id}>
                        <td className="py-3">
                          <Link
                            to={`/units/${link.unit_id}`}
                            className="font-medium hover:underline"
                          >
                            {link.unit?.name ?? '—'}
                          </Link>
                          <span className="ml-2 font-mono text-xs text-muted-foreground">
                            {link.unit?.code}
                          </span>
                        </td>
                        <td className="py-3 text-muted-foreground">
                          {link.course?.name ?? '—'}
                        </td>
                        <td className="py-3 text-muted-foreground">
                          {link.curriculum?.name ?? '—'}
                        </td>
                        <td className="py-3 text-muted-foreground">
                          {link.modules_taught}
                        </td>
                        <td className="py-3 text-muted-foreground">
                          {link.taught_hours}
                        </td>
                        <td className="py-3 text-muted-foreground">
                          {link.credit_factor}
                        </td>
                        <td className="py-3">
                          <UnitTypeBadge type={link.unit_type} />
                        </td>
                        <td className="py-3">
                          <StatusBadge active={link.is_active} />
                        </td>
                        <td className="py-3">
                          <div className="flex items-center justify-end gap-1">
                            <Link
                              to={`/course-units/${link.id}/edit`}
                              className={buttonVariants({
                                variant: 'ghost',
                                size: 'sm',
                              })}
                              title="Edit course unit"
                              aria-label={`Edit ${link.unit?.code ?? 'course unit'}`}
                            >
                              <Pencil aria-hidden="true" />
                            </Link>
                            <Button
                              variant="ghost"
                              size="sm"
                              disabled={togglingId === link.id}
                              title={
                                link.is_active
                                  ? 'Mark inactive'
                                  : 'Mark active'
                              }
                              aria-label={`${link.is_active ? 'Deactivate' : 'Activate'} ${link.unit?.code ?? 'course unit'}`}
                              onClick={() => handleToggleActive(link)}
                            >
                              <Power aria-hidden="true" />
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              disabled={deletingId === link.id}
                              title="Unlink from course"
                              aria-label={`Unlink ${link.unit?.code ?? 'course unit'}`}
                              onClick={() => handleDelete(link)}
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
                label="course units"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
