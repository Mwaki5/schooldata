import { ArrowLeft, BookOpen, Pencil, Plus, Power, Trash2 } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import PageLoader from '@/components/page-loader'
import { Button, buttonVariants } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
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
 * The course-side view of the units list: every unit this course teaches,
 * grouped per curriculum — each row one unit offering, editable in place.
 */
export default function CourseUnits() {
  const { courseId } = useParams()

  const [course, setCourse] = useState(null)
  const [units, setUnits] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [busyId, setBusyId] = useState(null) // unit being changed

  // No synchronous setState: the effect's fetch reports through promise
  // callbacks, and `loading` starts as `true` for the initial render.
  const load = useCallback(() => {
    Promise.all([api.get(`/courses/${courseId}`), api.get(`/courses/${courseId}/units`)])
      .then(([courseResponse, unitsResponse]) => {
        setCourse(courseResponse.data.course)
        setUnits(unitsResponse.data.units ?? [])
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

  const handleToggleActive = async (unit) => {
    setBusyId(unit.id)

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
        `${unit.code} ${unit.is_active ? 'deactivated in' : 'activated in'} ${unit.curriculum?.name}`,
      )
      reload()
    } catch (error) {
      toast.error(
        error.response?.data?.message ?? 'Unable to update the unit.',
      )
    } finally {
      setBusyId(null)
    }
  }

  const handleDelete = async (unit) => {
    if (
      !window.confirm(
        `Remove ${unit.code} from this course under ${unit.curriculum?.name}? Its code becomes available again in this curriculum.`,
      )
    ) {
      return
    }

    setBusyId(unit.id)

    try {
      await api.delete(`/units/${unit.id}`)

      toast.success(
        `Removed ${unit.code} from ${unit.curriculum?.name}`,
      )
      reload()
    } catch (error) {
      toast.error(
        error.response?.data?.message ?? 'Unable to remove the unit.',
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

  for (const unit of units) {
    const existing = byCurriculum.find(
      (section) => section.curriculum.id === unit.curriculum_id,
    )

    if (existing) {
      existing.rows.push(unit)
    } else {
      byCurriculum.push({
        curriculum: {
          id: unit.curriculum_id,
          name: unit.curriculum?.name ?? '',
        },
        rows: [unit],
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
              unit carries its own teaching load.
            </CardDescription>
            <p className="text-xs text-muted-foreground">
              {units.length}{' '}
              {units.length === 1 ? 'unit' : 'units'} across{' '}
              {byCurriculum.length}{' '}
              {byCurriculum.length === 1 ? 'curriculum' : 'curricula'}
            </p>
          </div>
          <Link
            to={`/units/create?course=${course.id}`}
            className={buttonVariants()}
          >
            <Plus aria-hidden="true" /> Add Unit
          </Link>
        </CardHeader>
      </Card>

      {units.length === 0 ? (
        <Card>
          <CardContent className="space-y-3 py-8">
            <p className="text-sm text-muted-foreground">
              This course has no units yet.
            </p>
            <Link
              to={`/units/create?course=${course.id}`}
              className={buttonVariants({ variant: 'outline' })}
            >
              <Plus aria-hidden="true" /> Add Unit
            </Link>
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
                to={`/units/create?course=${course.id}&curriculum=${section.curriculum.id}`}
                className={buttonVariants({ variant: 'outline', size: 'sm' })}
              >
                <Plus aria-hidden="true" /> Add Unit
              </Link>
            </CardHeader>
            <CardContent>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b text-left text-muted-foreground">
                      <th className="pb-3 font-medium">Unit</th>
                      <th className="pb-3 font-medium">Core</th>
                      <th className="pb-3 font-medium">Module</th>
                      <th className="pb-3 font-medium">Hours</th>
                      <th className="pb-3 font-medium">Credit</th>
                      <th className="pb-3 font-medium">Status</th>
                      <th className="pb-3 text-right font-medium">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {section.rows.map((unit) => (
                      <tr key={unit.id}>
                        <td className="py-3">
                          <Link
                            to={`/units/${unit.id}`}
                            className="font-medium hover:underline"
                          >
                            {unit.name}
                          </Link>
                          <span className="ml-1.5 font-mono text-xs text-muted-foreground">
                            {unit.code}
                          </span>
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
                              to={`/units/${unit.id}/edit`}
                              className={buttonVariants({
                                variant: 'ghost',
                                size: 'sm',
                              })}
                              title="Edit unit"
                              aria-label={`Edit ${unit.code} in ${unit.curriculum?.name}`}
                            >
                              <Pencil aria-hidden="true" />
                            </Link>
                            <Button
                              variant="ghost"
                              size="sm"
                              disabled={busyId === unit.id}
                              title={
                                unit.is_active
                                  ? 'Deactivate unit'
                                  : 'Activate unit'
                              }
                              aria-label={`${unit.is_active ? 'Deactivate' : 'Activate'} ${unit.code} in ${unit.curriculum?.name}`}
                              onClick={() => handleToggleActive(unit)}
                            >
                              <Power aria-hidden="true" />
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              disabled={busyId === unit.id}
                              title="Remove unit"
                              aria-label={`Remove ${unit.code} from ${unit.curriculum?.name}`}
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
            </CardContent>
          </Card>
        ))
      )}
    </div>
  )
}
