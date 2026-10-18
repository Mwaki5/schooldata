import { ArrowLeft, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import PageLoader from '@/components/page-loader'
import { FormField } from '@/components/form-field'
import { Button, buttonVariants } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import api, { loadErrorMessage, validationErrors } from '@/lib/api'

/**
 * Edit one assignment of a unit: its teaching load, which is independent
 * from every other delivery of the same unit. The unit, course and
 * curriculum are context (read-only here) — correcting them is a
 * deliberate re-assignment, not an edit of these values.
 */
export default function CourseUnitForm() {
  const { courseUnitId } = useParams()
  const navigate = useNavigate()

  const [assignment, setAssignment] = useState(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const [deleting, setDeleting] = useState(false)

  useEffect(() => {
    let active = true

    api
      .get(`/course-units/${courseUnitId}`)
      .then(({ data }) => {
        if (active) {
          setAssignment(data.course_unit)
        }
      })
      .catch((error) => {
        if (active) {
          setLoadError(
            loadErrorMessage(
              error,
              'Assignment',
              'Unable to load the assignment. It may have been removed.',
            ),
          )
        }
      })
      .finally(() => {
        if (active) {
          setLoading(false)
        }
      })

    return () => {
      active = false
    }
  }, [courseUnitId])

  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const formData = new FormData(event.currentTarget)
    const optionalNumber = (name) => {
      const value = String(formData.get(name) ?? '').trim()
      return value === '' ? null : Number(value)
    }

    try {
      await api.put(`/course-units/${courseUnitId}`, {
        course_id: assignment.course_id,
        curriculum_id: assignment.curriculum_id,
        core: formData.get('core') === '1',
        time_offered:
          String(formData.get('time_offered') ?? '').trim() || null,
        taught_hours: optionalNumber('taught_hours'),
        credit_factor: optionalNumber('credit_factor'),
        is_active: formData.get('is_active') === '1',
      })

      toast.success(`Assignment in ${assignment.course_name} updated`)
      navigate(`/units/${assignment.unit_id}`)
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to save the assignment.',
        )
      }
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async () => {
    if (
      !window.confirm(
        `Remove ${assignment.unit_name} from ${assignment.course_name}? The unit itself is kept.`,
      )
    ) {
      return
    }

    setDeleting(true)

    try {
      await api.delete(`/course-units/${courseUnitId}`)

      toast.success(
        `Removed ${assignment.unit_name} from ${assignment.course_name}`,
      )
      navigate(`/units/${assignment.unit_id}`)
    } catch (error) {
      toast.error(
        error.response?.data?.message ?? 'Unable to remove the assignment.',
      )
      setDeleting(false)
    }
  }

  if (loading) {
    return <PageLoader />
  }

  if (loadError) {
    return (
      <div className="mx-auto max-w-5xl space-y-6">
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
    <div className="mx-auto max-w-5xl space-y-6">
      <Link
        to={`/units/${assignment.unit_id}`}
        className={buttonVariants({ variant: 'ghost' })}
      >
        <ArrowLeft aria-hidden="true" /> Back to {assignment.unit_name}
      </Link>

      <Card className="py-8">
        <CardHeader>
          <CardTitle>Edit Assignment</CardTitle>
          <CardDescription>
            <Link
              to={`/units/${assignment.unit_id}`}
              className="font-medium hover:underline"
            >
              {assignment.unit_name}
            </Link>{' '}
            delivered under{' '}
            <span className="font-medium">{assignment.course_name}</span>
            {assignment.course_code && (
              <span className="text-muted-foreground">
                {' '}
                ({assignment.course_code})
              </span>
            )}{' '}
            — curriculum <span className="font-medium">{assignment.curriculum_name}</span>.
            Changes here never affect the unit's other assignments.
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-5" noValidate>
            <div className="grid gap-5 lg:grid-cols-3">
              <FormField
                id="time_offered"
                label="Time Offered"
                name="time_offered"
                defaultValue={assignment.time_offered ?? ''}
                maxLength={100}
                placeholder="e.g. Year 1 Term 1 (optional)"
                error={errors.time_offered}
              />

              <FormField
                id="taught_hours"
                label="Taught Hours"
                name="taught_hours"
                defaultValue={assignment.taught_hours ?? ''}
                type="number"
                min={0}
                max={32767}
                placeholder="e.g. 60 (optional)"
                error={errors.taught_hours}
              />

              <FormField
                id="credit_factor"
                label="Credit Factor"
                name="credit_factor"
                defaultValue={assignment.credit_factor ?? ''}
                type="number"
                min={0}
                step={0.01}
                placeholder="e.g. 2.5 (optional)"
                error={errors.credit_factor}
              />

              <div className="space-y-2">
                <Label htmlFor="core">Core Unit</Label>
                <label
                  htmlFor="core"
                  className="flex w-fit cursor-pointer items-center gap-2 text-sm text-muted-foreground"
                >
                  <Checkbox
                    id="core"
                    name="core"
                    value="1"
                    defaultChecked={assignment.core}
                  />
                  Required part of the course
                </label>
              </div>

              <div className="space-y-2">
                <Label htmlFor="is_active">Is Active</Label>
                <label
                  htmlFor="is_active"
                  className="flex w-fit cursor-pointer items-center gap-2 text-sm text-muted-foreground"
                >
                  <Checkbox
                    id="is_active"
                    name="is_active"
                    value="1"
                    defaultChecked={assignment.is_active}
                  />
                  This delivery is currently offered
                </label>
              </div>
            </div>

            <div className="flex items-center justify-between gap-3 border-t pt-5">
              <Button
                type="button"
                variant="destructive"
                disabled={deleting}
                onClick={handleDelete}
              >
                <Trash2 aria-hidden="true" />
                {deleting ? 'Removing…' : 'Remove Assignment'}
              </Button>
              <div className="flex items-center gap-3">
                <Button type="submit" disabled={submitting}>
                  {submitting ? 'Saving…' : 'Save Changes'}
                </Button>
                <Link
                  to={`/units/${assignment.unit_id}`}
                  className={buttonVariants({ variant: 'outline' })}
                >
                  Cancel
                </Link>
              </div>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
