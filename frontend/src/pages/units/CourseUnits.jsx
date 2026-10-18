import { ArrowLeft, BookOpen, Link2, Pencil, Power, Trash2 } from 'lucide-react'
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
 * The course-side twin of a unit's details page: every unit assigned to
 * one course, grouped per curriculum — which is where a delivery really
 * happens — with each assignment editable in place of its own teaching
 * load. The master unit data lives on the unit itself and is never
 * changed from here.
 */
export default function CourseUnits() {
  const { courseId } = useParams()

  const [course, setCourse] = useState(null)
  const [assignments, setAssignments] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [busyId, setBusyId] = useState(null) // assignment being changed

  // No synchronous setState: the effect's fetch reports through promise
  // callbacks, and `loading` starts as `true` for the initial render.
  const load = useCallback(() => {
    Promise.all([api.get(`/courses/${courseId}`), api.get(`/courses/${courseId}/units`)])
      .then(([courseResponse, unitsResponse]) => {
        setCourse(courseResponse.data.course)
        setAssignments(unitsResponse.data.assignments ?? [])
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

  const handleToggleAssignment = async (assignment) => {
    setBusyId(assignment.id)

    try {
      await api.put(`/course-units/${assignment.id}`, {
        course_id: assignment.course_id,
        curriculum_id: assignment.curriculum_id,
        core: assignment.core,
        time_offered: assignment.time_offered,
        taught_hours: assignment.taught_hours,
        credit_factor: assignment.credit_factor,
        is_active: !assignment.is_active,
      })

      toast.success(
        `${assignment.unit_code} ${assignment.is_active ? 'deactivated in' : 'activated in'} ${assignment.curriculum_name}`,
      )
      reload()
    } catch (error) {
      toast.error(
        error.response?.data?.message ?? 'Unable to update the assignment.',
      )
    } finally {
      setBusyId(null)
    }
  }

  const handleRemoveAssignment = async (assignment) => {
    if (
      !window.confirm(
        `Remove ${assignment.unit_code} from this course under ${assignment.curriculum_name}? The unit itself is kept.`,
      )
    ) {
      return
    }

    setBusyId(assignment.id)

    try {
      await api.delete(`/course-units/${assignment.id}`)

      toast.success(
        `Removed ${assignment.unit_code} from ${assignment.curriculum_name}`,
      )
      reload()
    } catch (error) {
      toast.error(
        error.response?.data?.message ?? 'Unable to remove the assignment.',
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

  for (const assignment of assignments) {
    const existing = byCurriculum.find(
      (section) => section.curriculum.id === assignment.curriculum_id,
    )

    if (existing) {
      existing.rows.push(assignment)
    } else {
      byCurriculum.push({
        curriculum: {
          id: assignment.curriculum_id,
          name: assignment.curriculum_name,
        },
        rows: [assignment],
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
              assignment carries its own teaching load.
            </CardDescription>
            <p className="text-xs text-muted-foreground">
              {assignments.length}{' '}
              {assignments.length === 1 ? 'assignment' : 'assignments'} across{' '}
              {byCurriculum.length}{' '}
              {byCurriculum.length === 1 ? 'curriculum' : 'curricula'}
            </p>
          </div>
          <Link
            to={`/units/assign?course=${course.id}`}
            className={buttonVariants()}
          >
            <Link2 aria-hidden="true" /> Assign Unit
          </Link>
        </CardHeader>
      </Card>

      {assignments.length === 0 ? (
        <Card>
          <CardContent className="space-y-3 py-8">
            <p className="text-sm text-muted-foreground">
              No units are assigned to this course yet.
            </p>
            <Link
              to={`/units/assign?course=${course.id}`}
              className={buttonVariants({ variant: 'outline' })}
            >
              <Link2 aria-hidden="true" /> Assign Unit
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
                to={`/units/assign?course=${course.id}&curriculum=${section.curriculum.id}`}
                className={buttonVariants({ variant: 'outline', size: 'sm' })}
              >
                <Link2 aria-hidden="true" /> Assign Unit
              </Link>
            </CardHeader>
            <CardContent>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b text-left text-muted-foreground">
                      <th className="pb-3 font-medium">Unit</th>
                      <th className="pb-3 font-medium">Core</th>
                      <th className="pb-3 font-medium">Time Offered</th>
                      <th className="pb-3 font-medium">Hours</th>
                      <th className="pb-3 font-medium">Credit</th>
                      <th className="pb-3 font-medium">Status</th>
                      <th className="pb-3 text-right font-medium">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {section.rows.map((assignment) => (
                      <tr key={assignment.id}>
                        <td className="py-3">
                          <Link
                            to={`/units/${assignment.unit_id}`}
                            className="font-medium hover:underline"
                          >
                            {assignment.unit_name}
                          </Link>
                          <span className="ml-1.5 font-mono text-xs text-muted-foreground">
                            {assignment.unit_code}
                          </span>
                        </td>
                        <td className="py-3">
                          <span
                            className={cn(
                              'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                              assignment.core
                                ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400'
                                : 'bg-muted text-muted-foreground',
                            )}
                          >
                            {assignment.core ? 'Core' : 'Elective'}
                          </span>
                        </td>
                        <td className="py-3 text-muted-foreground">
                          {assignment.time_offered ?? '—'}
                        </td>
                        <td className="py-3 text-muted-foreground">
                          {assignment.taught_hours ?? '—'}
                        </td>
                        <td className="py-3 text-muted-foreground">
                          {assignment.credit_factor ?? '—'}
                        </td>
                        <td className="py-3">
                          <StatusBadge active={assignment.is_active} />
                        </td>
                        <td className="py-3">
                          <div className="flex items-center justify-end gap-1">
                            <Link
                              to={`/course-units/${assignment.id}/edit`}
                              className={buttonVariants({
                                variant: 'ghost',
                                size: 'sm',
                              })}
                              title="Edit assignment"
                              aria-label={`Edit ${assignment.unit_code} in ${assignment.curriculum_name}`}
                            >
                              <Pencil aria-hidden="true" />
                            </Link>
                            <Button
                              variant="ghost"
                              size="sm"
                              disabled={busyId === assignment.id}
                              title={
                                assignment.is_active
                                  ? 'Deactivate assignment'
                                  : 'Activate assignment'
                              }
                              aria-label={`${assignment.is_active ? 'Deactivate' : 'Activate'} ${assignment.unit_code} in ${assignment.curriculum_name}`}
                              onClick={() => handleToggleAssignment(assignment)}
                            >
                              <Power aria-hidden="true" />
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              disabled={busyId === assignment.id}
                              title="Remove assignment"
                              aria-label={`Remove ${assignment.unit_code} from ${assignment.curriculum_name}`}
                              onClick={() => handleRemoveAssignment(assignment)}
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
