import { ArrowLeft, Pencil, Power, Trash2 } from 'lucide-react'
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
  const [busy, setBusy] = useState(false)

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

  const handleToggleActive = async () => {
    setBusy(true)

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
        `Unit ${unit.is_active ? 'deactivated' : 'activated'}`,
      )
      reload()
    } catch (error) {
      toast.error(
        error.response?.data?.message ?? 'Unable to update the unit.',
      )
    } finally {
      setBusy(false)
    }
  }

  const handleDelete = async () => {
    if (
      !window.confirm(
        `Delete the unit "${unit.name}" (${unit.code})? Its code becomes available again inside this course and curriculum.`,
      )
    ) {
      return
    }

    setBusy(true)

    try {
      await api.delete(`/units/${unit.id}`)

      toast.success(`Deleted unit "${unit.name}"`)
      navigate('/units')
    } catch (error) {
      toast.error(
        error.response?.data?.message ?? 'Unable to delete the unit.',
      )
    } finally {
      setBusy(false)
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

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <Link to="/units" className={buttonVariants({ variant: 'ghost' })}>
        <ArrowLeft aria-hidden="true" /> Back to units
      </Link>

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
              {unit.course?.code && (
                <span className="mr-1.5 font-mono">{unit.course.code}</span>
              )}
              {unit.course?.name ?? 'Unknown course'} ·{' '}
              {unit.curriculum?.name ?? 'Unknown curriculum'}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Link
              to={`/units/${unit.id}/edit`}
              className={buttonVariants({ variant: 'outline' })}
            >
              <Pencil aria-hidden="true" /> Edit Unit
            </Link>
            <Button
              variant="outline"
              disabled={busy}
              onClick={handleToggleActive}
            >
              <Power aria-hidden="true" />
              {unit.is_active ? 'Deactivate' : 'Activate'}
            </Button>
            <Button variant="destructive" disabled={busy} onClick={handleDelete}>
              <Trash2 aria-hidden="true" /> Delete
            </Button>
          </div>
        </CardHeader>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Teaching Load</CardTitle>
          <CardDescription>
            How this unit is delivered inside its course and curriculum.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <dl className="grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <dt className="text-muted-foreground">Core Unit</dt>
              <dd className="mt-0.5 font-medium">
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
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Module</dt>
              <dd className="mt-0.5 font-medium">
                {unit.modules_taught ?? '—'}
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Training Hours</dt>
              <dd className="mt-0.5 font-medium">
                {unit.taught_hours ?? '—'}
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Credit Factor</dt>
              <dd className="mt-0.5 font-medium">
                {unit.credit_factor ?? '—'}
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Status</dt>
              <dd className="mt-0.5 font-medium">
                <StatusBadge active={unit.is_active} />
              </dd>
            </div>
          </dl>
        </CardContent>
      </Card>
    </div>
  )
}
