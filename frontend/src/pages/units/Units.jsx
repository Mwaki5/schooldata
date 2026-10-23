import { BookOpen, Eye, Pencil, Plus, Trash2 } from 'lucide-react'
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
import { useAuth } from '@/context/AuthContext'

export default function Units() {
  const { can } = useAuth()
  const [deletingId, setDeletingId] = useState(null)
  const [courseFilter, setCourseFilter] = useState('')
  const [curriculumFilter, setCurriculumFilter] = useState('')
  const [courseOptions, setCourseOptions] = useState([])
  const [curriculumOptions, setCurriculumOptions] = useState([])

  // The course and curriculum filter dropdowns list their options once on
  // mount; failures are swallowed because the table itself loads
  // independently. They narrow to units linked into that course or
  // curriculum.
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

  // The unit catalogue: one row per unit. Teaching loads per course
  // live on the course-unit links (Course Units).
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
      course_id: courseFilter || undefined,
      curriculum_id: curriculumFilter || undefined,
    },
  })

  const handleDelete = async (unit) => {
    if (
      !window.confirm(
        `Delete the unit "${unit.name}" (${unit.code})? Its course links are removed and its code becomes available again.`,
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
    setSearch('')
  }

  const hasFilters = courseFilter !== '' || curriculumFilter !== '' || search !== ''

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Units</h1>
          <p className="text-sm text-muted-foreground">
            The unit catalogue: one record per unit. Assign units to a course
            and curriculum under Course Units.
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          {can('course-units.create') && (
            <Link
              to="/course-units/create"
              className={buttonVariants({ variant: 'outline' })}
            >
              <BookOpen aria-hidden="true" />
              Link to Course
            </Link>
          )}
          {can('units.create') && (
            <Link to="/units/create" className={buttonVariants()}>
              <Plus aria-hidden="true" />
              New Unit
            </Link>
          )}
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
                <table className="w-full min-w-[720px] text-sm">
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
                      <th className="pb-3 font-medium whitespace-nowrap">Description</th>
                      <th className="pb-3 font-medium whitespace-nowrap">Courses</th>
                      <th className="pb-3 text-right font-medium whitespace-nowrap">Actions</th>
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
                        <td className="max-w-72 truncate py-3 text-muted-foreground">
                          {unit.description || '—'}
                        </td>
                        <td className="py-3 text-muted-foreground">
                          {unit.course_units_count ?? 0}
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
                            {can('units.update') && (
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
                            )}
                            <Link
                              to={`/course-units?unit_id=${unit.id}`}
                              className={buttonVariants({
                                variant: 'ghost',
                                size: 'sm',
                              })}
                              title="Course links"
                              aria-label={`Course links for ${unit.name}`}
                            >
                              <BookOpen aria-hidden="true" />
                            </Link>
                            {can('units.delete') && (
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
                            )}
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
