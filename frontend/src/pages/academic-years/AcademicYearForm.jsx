import { ArrowLeft } from 'lucide-react'
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

export default function AcademicYearForm() {
  const { yearId } = useParams()
  const isEdit = Boolean(yearId)
  const navigate = useNavigate()

  const [year, setYear] = useState(null)
  const [loading, setLoading] = useState(isEdit)
  const [loadError, setLoadError] = useState(null)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const [formKey, setFormKey] = useState(0) // remount to clear fields after create

  useEffect(() => {
    if (!isEdit) {
      return undefined
    }

    let active = true

    api
      .get(`/academic-years/${yearId}`)
      .then(({ data }) => {
        if (active) {
          setYear(data.academic_year)
        }
      })
      .catch((error) => {
        if (active) {
          setLoadError(
            loadErrorMessage(
              error,
              'Academic year',
              'Unable to load the academic year. It may have been deleted.',
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
  }, [isEdit, yearId])

  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const form = new FormData(event.currentTarget)
    const payload = {
      name: String(form.get('name') ?? '').trim(),
      start_date: form.get('start_date') || null,
      end_date: form.get('end_date') || null,
      description: String(form.get('description') ?? '').trim() || null,
      is_active: form.get('is_active') === '1',
    }

    try {
      if (isEdit) {
        await api.put(`/academic-years/${yearId}`, payload)
        toast.success('Academic year updated')
        navigate('/academic-years')
      } else {
        await api.post('/academic-years', payload)
        toast.success('Academic year created')
        setFormKey((k) => k + 1) // remount → fields clear
      }
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to save the academic year.',
        )
      }
    } finally {
      setSubmitting(false)
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
        <Link to="/academic-years" className={buttonVariants({ variant: 'outline' })}>
          <ArrowLeft aria-hidden="true" /> Back to academic years
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <Link to="/academic-years" className={buttonVariants({ variant: 'ghost' })}>
        <ArrowLeft aria-hidden="true" /> Back to academic years
      </Link>

      <Card className="py-8">
        <CardHeader>
          <CardTitle>
            {isEdit ? 'Edit Academic Year' : 'Create Academic Year'}
          </CardTitle>
          <CardDescription>
            {isEdit
              ? 'Update the academic year details below.'
              : 'Register a new academic year.'}
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-5" noValidate key={formKey}>
            {/* One input per row until lg; then two columns (fewer than 6 inputs). */}
            <div className="grid gap-5 lg:grid-cols-2">
              <FormField
                id="name"
                label="Name"
                name="name"
                required
                defaultValue={year?.name ?? ''}
                placeholder="e.g. 2026/2027"
                autoComplete="off"
                error={errors.name}
              />

              <FormField
                id="start_date"
                label="Start Date"
                name="start_date"
                type="date"
                defaultValue={year?.start_date ?? ''}
                error={errors.start_date}
              />

              <FormField
                id="end_date"
                label="End Date"
                name="end_date"
                type="date"
                defaultValue={year?.end_date ?? ''}
                error={errors.end_date}
              />

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
                    defaultChecked={year?.is_active ?? false}
                  />
                  Academic year is currently active
                </label>
                <p className="text-xs text-muted-foreground">
                  Only one academic year can be active at a time — activating
                  this one retires the others.
                </p>
              </div>
            </div>

            <FormField
              id="description"
              label="Description"
              name="description"
              multiline
              rows={3}
              defaultValue={year?.description ?? ''}
              placeholder="Notes about this academic year"
              error={errors.description}
            />

            <div className="flex items-center gap-3">
              <Button type="submit" disabled={submitting}>
                {submitting
                  ? 'Saving…'
                  : isEdit
                  ? 'Save Changes'
                  : 'Create Academic Year'}
              </Button>
              <Link to="/academic-years" className={buttonVariants({ variant: 'outline' })}>
                Cancel
              </Link>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
