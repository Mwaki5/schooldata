import { ArrowLeft, Pencil, Plus, Trash2 } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
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
 * One catalogue unit: its own code, name and description, plus every
 * course link it has (course + curriculum + teaching load). Deleting
 * here deletes the unit itself; managing a single link happens on the
 * link's own edit page.
 */
export default function UnitDetails() {
  const { unitId } = useParams()
  const navigate = useNavigate()

  const [unit, setUnit] = useState(null)
  const [deleting, setDeleting] = useState(false)
  const [loadError, setLoadError] = useState(null)
  const [loading, setLoading] = useState(true)

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
      .finally(() => setLoading(false))
  }, [unitId])

  useEffect(() => load(), [load])

  const handleDelete = async () => {
    if (
      !window.confirm(
        `Delete the unit "${unit.name}" (${unit.code})? Its course links are removed and its code becomes available again.`,
      )
    ) {
      return
    }

    setDeleting(true)

    try {
      await api.delete(`/units/${unit.id}`)
      toast.success(`Deleted unit "${unit.name}"`)
      navigate('/units')
    } catch (error) {
      toast.error(
        error.response?.data?.message ??
          'Unable to delete the unit. Please try again.',
      )
      setDeleting(false)
    }
  }

  if (loading) {
    return <PageLoader />
  }

  if (loadError) {
    return (
      <div className="mx-auto max-w-3xl space-y-6">
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

  const links = unit.course_units ?? []

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <Link to="/units" className={buttonVariants({ variant: 'ghost' })}>
        <ArrowLeft aria-hidden="true" /> Back to units
      </Link>

      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="space-y-1">
          <p className="font-mono text-sm text-muted-foreground">{unit.code}</p>
          <h1 className="text-2xl font-semibold tracking-tight">
            {unit.name}
          </h1>
          <p className="text-sm text-muted-foreground">
            {unit.description || 'No description provided.'}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Link
            to={`/course-units/create?unit=${unit.id}`}
            className={buttonVariants({ variant: 'outline' })}
          >
            <Plus aria-hidden="true" /> Link to Course
          </Link>
          <Link
            to={`/units/${unit.id}/edit`}
            className={buttonVariants({ variant: 'outline' })}
          >
            <Pencil aria-hidden="true" /> Edit
          </Link>
          <Button
            variant="destructive"
            disabled={deleting}
            onClick={handleDelete}
          >
            <Trash2 aria-hidden="true" />
            {deleting ? 'Deleting…' : 'Delete'}
          </Button>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Course Links</CardTitle>
          <CardDescription>
            Every course and curriculum this unit is taught under, with its
            teaching load and role.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {links.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              Not linked to any course yet. Use “Link to Course” to assign it
              to a course and curriculum.
            </p>
          ) : (
            <ul className="space-y-3">
              {links.map((link) => (
                <li
                  key={link.id}
                  className="flex flex-wrap items-center justify-between gap-3 rounded-xl border px-4 py-3 text-sm"
                >
                  <div className="space-y-1">
                    <p className="font-medium">
                      {link.course?.name ?? '—'}
                      {link.curriculum && (
                        <span className="ml-2 font-normal text-muted-foreground">
                          {link.curriculum.name}
                        </span>
                      )}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {link.modules_taught} module
                      {link.modules_taught === 1 ? '' : 's'} ·{' '}
                      {link.taught_hours} allocated hours · Credit factor{' '}
                      {link.credit_factor}
                    </p>
                  </div>
                  <div className="flex items-center gap-2">
                    <UnitTypeBadge type={link.unit_type} />
                    <StatusBadge active={link.is_active} />
                    <Link
                      to={`/course-units/${link.id}/edit`}
                      className={buttonVariants({
                        variant: 'ghost',
                        size: 'sm',
                      })}
                    >
                      <Pencil aria-hidden="true" /> Edit
                    </Link>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
