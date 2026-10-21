import { ArrowLeft, BookOpen, Link2, Pencil, Plus, Power, Trash2 } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import PageLoader from '@/components/page-loader'
import { Button, buttonVariants } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { UnitTypeBadge } from '@/components/unit-type-badge'
import api, { loadErrorMessage } from '@/lib/api'
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
 * The course-side view of the unit links: every unit this course
 * teaches, grouped per curriculum — each row one unit-course link with
 * its own teaching load, editable in place. Removing a row unlinks the
 * unit; the unit itself stays in the catalogue.
 */
export default function CourseUnits() {
  const { courseId } = useParams()

  const [course, setCourse] = useState(null)
  const [courseUnits, setCourseUnits] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [busyId, setBusyId] = useState(null) // link being changed

  // No synchronous setState: the effect's fetch reports through promise
  // callbacks, and `loading` starts as `true` for the initial render.
  const load = useCallback(() => {
    Promise.all([api.get(`/courses/${courseId}`), api.get(`/courses/${courseId}/units`)])
      .then(([courseResponse, unitsResponse]) => {
        setCourse(courseResponse.data.course)
        setCourseUnits(unitsResponse.data.course_units ?? [])
        setLoadError(null)
      })
      .catch((error) => {
        setLoadError(
          loadErrorMessage(
            error,
            'Course',
            'Unable to load the course. It may have been deleted.',
          ),
        )
      })
      .finally(() => {
        setLoading(false)
      })
  }, [courseId])

  useEffect(() => {
    load()
  }, [load])

  /** Reload from an event handler, with the spinner up. */
  const reload = () => {
    setLoading(true)
    load()
  }

  const handleToggleActive = async (link) => {
    setBusyId(link.id)

    try {
      await api.put(`/course-units/${link.id}`, {
        is_active: !link.is_active,
      })

      toast.success(
        `${link.unit?.code ?? 'Unit'} ${link.is_active ? 'deactivated in' : 'activated in'} ${link.curriculum?.name ?? 'the curriculum'}`,
      )
      reload()
    } catch (error) {
      toast.error(
        error.response?.data?.message ?? 'Unable to update the course unit.',
      )
    } finally {
      setBusyId(null)
    }
  }

  const handleDelete = async (link) => {
    if (
      !window.confirm(
        `Remove ${link.unit?.code ?? 'this unit'} from this course under ${link.curriculum?.name ?? 'this curriculum'}? The unit stays in the catalogue and can be linked again later.`,
      )
    ) {
      return
    }

    setBusyId(link.id)

    try {
      await api.delete(`/course-units/${link.id}`)

      toast.success(
        `Removed ${link.unit?.code ?? 'unit'} from ${link.curriculum?.name ?? 'the curriculum'}`,
      )
      reload()
    } catch (error) {
      toast.error(
        error.response?.data?.message ?? 'Unable to remove the course unit.',
      )
    } finally {
      setBusyId(null)
    }
  }

  if (loading) {
    return <PageLoader />
  }

  if (loadError) {
    return (
      <div className="mx-auto max-w-6xl space-y-6">
        <div
          role="alert"
          className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
        >
          {loadError}
        </div>
        <Link to="/courses" className={buttonVariants({ variant: 'outline' })}>
          <ArrowLeft aria-hidden="true" /> Back to courses
        </Link>
      </div>
    )
  }

  // One section per curriculum, in the order the API returned them.
  const byCurriculum = []

  for (const link of courseUnits) {
    const existing = byCurriculum.find(
      (section) => section.curriculum.id === link.curriculum_id,
    )

    if (existing) {
      existing.rows.push(link)
    } else {
      byCurriculum.push({
        curriculum: {
          id: link.curriculum_id,
          name: link.curriculum?.name ?? '',
        },
        rows: [link],
      })
    }
  }

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <Link to="/courses" className={buttonVariants({ variant: 'ghost' })}>
        <ArrowLeft aria-hidden="true" /> Back to courses
      </Link>

      {/* Course card: which course's units we are looking at. */}
      <Card className="py-8">
        <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-4">
          <div className="space-y-1">
            <div className="flex flex-wrap items-center gap-3">
              <CardTitle>{course.name}</CardTitle>
              <span className="rounded-md bg-muted px-2 py-0.5 font-mono text-sm font-medium text-muted-foreground">
                {course.code}
              </span>
            </div>
            <CardDescription>
              Every unit this course teaches, grouped by curriculum. Each
              link carries its own teaching load.
            </CardDescription>
            <p className="text-xs text-muted-foreground">
              {courseUnits.length}{' '}
              {courseUnits.length === 1 ? 'unit' : 'units'} across{' '}
              {byCurriculum.length}{' '}
              {byCurriculum.length === 1 ? 'curriculum' : 'curricula'}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Link
              to={`/course-units/create?course=${course.id}`}
              className={buttonVariants({ variant: 'outline' })}
            >
              <Link2 aria-hidden="true" /> Link Existing Unit
            </Link>
            <Link
              to={`/units/create?course=${course.id}`}
              className={buttonVariants()}
            >
              <Plus aria-hidden="true" /> New Unit
            </Link>
          </div>
        </CardHeader>
      </Card>

      {courseUnits.length === 0 ? (
        <Card>
          <CardContent className="space-y-3 py-8">
            <p className="text-sm text-muted-foreground">
              This course has no units yet. Link a unit already in the
              catalogue, or create a new one.
            </p>
            <div className="flex flex-wrap gap-2">
              <Link
                to={`/course-units/create?course=${course.id}`}
                className={buttonVariants({ variant: 'outline' })}
              >
                <Link2 aria-hidden="true" /> Link Existing Unit
              </Link>
              <Link
                to={`/units/create?course=${course.id}`}
                className={buttonVariants({ variant: 'outline' })}
              >
                <Plus aria-hidden="true" /> New Unit
              </Link>
            </div>
          </CardContent>
        </Card>
      ) : (
        byCurriculum.map((section) => (
          <Card key={section.curriculum.id}>
            <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
              <div>
                <CardTitle className="flex items-center gap-2">
                  <BookOpen className="size-4 text-muted-foreground" aria-hidden="true" />
                  {section.curriculum.name}
                </CardTitle>
                <CardDescription>
                  {section.rows.length}{' '}
                  {section.rows.length === 1 ? 'unit' : 'units'} under this
                  curriculum
                </CardDescription>
              </div>
              <Link
                to={`/course-units/create?course=${course.id}&curriculum=${section.curriculum.id}`}
                className={buttonVariants({ variant: 'outline', size: 'sm' })}
              >
                <Link2 aria-hidden="true" /> Link Unit
              </Link>
            </CardHeader>
            <CardContent>
              <div className="overflow-x-auto">
                <table className="w-full min-w-[800px] text-sm">
                  <thead>
                    <tr className="border-b text-left text-muted-foreground">
                      <th className="pb-3 font-medium whitespace-nowrap">Unit</th>
                      <th className="pb-3 font-medium whitespace-nowrap">Unit Type</th>
                      <th className="pb-3 font-medium whitespace-nowrap">Module</th>
                      <th className="pb-3 font-medium whitespace-nowrap">Allocated Hours</th>
                      <th className="pb-3 font-medium whitespace-nowrap">Credit Factor</th>
                      <th className="pb-3 font-medium whitespace-nowrap">Status</th>
                      <th className="pb-3 text-right font-medium whitespace-nowrap">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {section.rows.map((link) => (
                      <tr key={link.id}>
                        <td className="py-3">
                          <Link
                            to={`/units/${link.unit_id}`}
                            className="font-medium hover:underline"
                          >
                            {link.unit?.name ?? '—'}
                          </Link>
                          <span className="ml-1.5 font-mono text-xs text-muted-foreground">
                            {link.unit?.code}
                          </span>
                        </td>
                        <td className="py-3">
                          <UnitTypeBadge type={link.unit_type} />
                        </td>
                        <td className="py-3 text-muted-foreground">
                          {link.modules_taught ?? '—'}
                        </td>
                        <td className="py-3 text-muted-foreground">
                          {link.taught_hours ?? '—'}
                        </td>
                        <td className="py-3 text-muted-foreground">
                          {link.credit_factor ?? '—'}
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
                              aria-label={`Edit ${link.unit?.code ?? 'unit'} in ${link.curriculum?.name ?? 'curriculum'}`}
                            >
                              <Pencil aria-hidden="true" />
                            </Link>
                            <Button
                              variant="ghost"
                              size="sm"
                              disabled={busyId === link.id}
                              title={
                                link.is_active
                                  ? 'Deactivate unit'
                                  : 'Activate unit'
                              }
                              aria-label={`${link.is_active ? 'Deactivate' : 'Activate'} ${link.unit?.code ?? 'unit'} in ${link.curriculum?.name ?? 'curriculum'}`}
                              onClick={() => handleToggleActive(link)}
                            >
                              <Power aria-hidden="true" />
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              disabled={busyId === link.id}
                              title="Unlink from course"
                              aria-label={`Unlink ${link.unit?.code ?? 'unit'} from ${link.curriculum?.name ?? 'curriculum'}`}
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
            </CardContent>
          </Card>
        ))
      )}
    </div>
  )
}
