import { ArrowLeft, Link2, Pencil, Power, Trash2 } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
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

export default function UnitDetails() {
  const { unitId } = useParams()
  const navigate = useNavigate()

  const [unit, setUnit] = useState(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [busyId, setBusyId] = useState(null) // unit or assignment being changed

  // No synchronous setState: the effect's fetch reports through promise
  // callbacks, and `loading` starts as `true` for the initial render.
  const load = useCallback(() => {
    api
      .get(`/units/${unitId}`)
      .then(({ data }) => {
        setUnit(data.unit)
        setLoadError(null)
      })
      .catch((error) => {
        setLoadError(
          loadErrorMessage(
            error,
            'Unit',
            'Unable to load the unit. It may have been deleted.',
          ),
        )
      })
      .finally(() => {
        setLoading(false)
      })
  }, [unitId])

  useEffect(() => {
    load()
  }, [load])

  /** Reload from an event handler, with the spinner up. */
  const reload = () => {
    setLoading(true)
    load()
  }

  const handleToggleUnit = async () => {
    setBusyId('unit')

    try {
      await api.put(`/units/${unit.id}`, {
        code: unit.code,
        name: unit.name,
        description: unit.description,
        is_active: !unit.is_active,
      })

      toast.success(
        `Unit ${unit.is_active ? 'deactivated' : 'activated'}`,
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

  const handleDeleteUnit = async () => {
    if (
      !window.confirm(
        `Delete the unit "${unit.name}"? It can only be deleted once it is no longer assigned to any course.`,
      )
    ) {
      return
    }

    setBusyId('unit')

    try {
      await api.delete(`/units/${unit.id}`)

      toast.success(`Deleted unit "${unit.name}"`)
      navigate('/units')
    } catch (error) {
      // The backend refuses while assignments exist and explains why.
      toast.error(
        error.response?.data?.message ?? 'Unable to delete the unit.',
      )
    } finally {
      setBusyId(null)
    }
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
        `${unit.code} ${assignment.is_active ? 'deactivated in' : 'activated in'} ${assignment.course_name}`,
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
        `Remove ${unit.code} from ${assignment.course_name} (${assignment.curriculum_name})? The unit itself is kept.`,
      )
    ) {
      return
    }

    setBusyId(assignment.id)

    try {
      await api.delete(`/course-units/${assignment.id}`)

      toast.success(`Removed ${unit.code} from ${assignment.course_name}`)
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
        <Link to="/units" className={buttonVariants({ variant: 'outline' })}>
          <ArrowLeft aria-hidden="true" /> Back to units
        </Link>
      </div>
    )
  }

  const assignments = unit.assignments ?? []

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <Link to="/units" className={buttonVariants({ variant: 'ghost' })}>
        <ArrowLeft aria-hidden="true" /> Back to units
      </Link>

      {/* Master unit card: catalogue-level facts only. */}
      <Card className="py-8">
        <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-4">
          <div className="space-y-1">
            <div className="flex flex-wrap items-center gap-3">
              <CardTitle>{unit.name}</CardTitle>
              <span className="rounded-md bg-muted px-2 py-0.5 font-mono text-sm font-medium text-muted-foreground">
                {unit.code}
              </span>
              <StatusBadge active={unit.is_active} />
            </div>
            <CardDescription>
              {unit.description || 'No description provided.'}
            </CardDescription>
            <p className="text-xs text-muted-foreground">
              {unit.assignments_count}{' '}
              {unit.assignments_count === 1 ? 'assignment' : 'assignments'} across
              courses and curricula
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Link
              to={`/units/${unit.id}/assign`}
              className={buttonVariants()}
            >
              <Link2 aria-hidden="true" /> Assign to Course
            </Link>
            <Link
              to={`/units/${unit.id}/edit`}
              className={buttonVariants({ variant: 'outline' })}
            >
              <Pencil aria-hidden="true" /> Edit Unit
            </Link>
            <Button
              variant="outline"
              disabled={busyId === 'unit'}
              onClick={handleToggleUnit}
            >
              <Power aria-hidden="true" />
              {unit.is_active ? 'Deactivate' : 'Activate'}
            </Button>
            <Button
              variant="destructive"
              disabled={busyId === 'unit'}
              onClick={handleDeleteUnit}
            >
              <Trash2 aria-hidden="true" /> Delete
            </Button>
          </div>
        </CardHeader>
      </Card>

      {/* Assignment-specific facts live only here, never on the master. */}
      <Card>
        <CardHeader>
          <CardTitle>Assignments</CardTitle>
          <CardDescription>
            Where this unit is taught. Every assignment carries its own core
            flag, time offered, hours and credit factor.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {assignments.length === 0 ? (
            <div className="space-y-3">
              <p className="text-sm text-muted-foreground">
                This unit is not assigned to any course yet.
              </p>
              <Link
                to={`/units/${unit.id}/assign`}
                className={buttonVariants({ variant: 'outline' })}
              >
                <Link2 aria-hidden="true" /> Assign to Course
              </Link>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <th className="pb-3 font-medium">Course</th>
                    <th className="pb-3 font-medium">Curriculum</th>
                    <th className="pb-3 font-medium">Core</th>
                    <th className="pb-3 font-medium">Time Offered</th>
                    <th className="pb-3 font-medium">Hours</th>
                    <th className="pb-3 font-medium">Credit</th>
                    <th className="pb-3 font-medium">Status</th>
                    <th className="pb-3 text-right font-medium">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {assignments.map((assignment) => (
                    <tr key={assignment.id}>
                      <td className="max-w-56 truncate py-3">
                        {assignment.course_code && (
                          <span className="mr-1.5 text-xs text-muted-foreground">
                            {assignment.course_code}
                          </span>
                        )}
                        {assignment.course_name}
                      </td>
                      <td className="max-w-40 truncate py-3 text-muted-foreground">
                        {assignment.curriculum_name}
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
                            aria-label={`Edit ${unit.code} in ${assignment.course_name}`}
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
                            aria-label={`${assignment.is_active ? 'Deactivate' : 'Activate'} ${unit.code} in ${assignment.course_name}`}
                            onClick={() => handleToggleAssignment(assignment)}
                          >
                            <Power aria-hidden="true" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            disabled={busyId === assignment.id}
                            title="Remove assignment"
                            aria-label={`Remove ${unit.code} from ${assignment.course_name}`}
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
          )}
        </CardContent>
      </Card>
    </div>
  )
}
