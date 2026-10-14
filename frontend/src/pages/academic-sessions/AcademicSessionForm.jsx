import { ArrowLeft } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import PageLoader from '@/components/page-loader'
import { FormField } from '@/components/form-field'
import { Button, buttonVariants } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { SearchableSelect } from '@/components/ui/searchable-select'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import api, { validationErrors } from '@/lib/api'

export default function AcademicSessionForm() {
  const { sessionId } = useParams()
  const isEdit = Boolean(sessionId)
  const navigate = useNavigate()

  const [session, setSession] = useState(null)
  const [academicYear, setAcademicYear] = useState(null) // { id, name }
  const [loading, setLoading] = useState(isEdit)
  const [loadError, setLoadError] = useState(null)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const [formKey, setFormKey] = useState(0) // remount to clear fields after create

  // Load the form state on mount.
  useEffect(() => {
    if (!isEdit) {
      return undefined
    }

    let active = true

    api
      .get(`/academic-sessions/${sessionId}`)
      .then(({ data }) => {
        if (active) {
          const sd = data.academic_session
          setSession(sd)
          setAcademicYear(
            sd.academic_year_id
              ? {
                  id: sd.academic_year_id,
                  name: sd.academic_year_name ?? '',
                }
              : null,
          )
        }
      })
      .catch(() => {
        if (active) {
          setLoadError(
            'Unable to load the academic session. It may have been deleted.',
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
  }, [isEdit, sessionId])

  // On create: keep the academic year selection; after submit only clear the
  // other fields. The form remounts (formKey increment) to reset uncontrolled
  // inputs while keeping the SearchableSelect value.
  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const form = new FormData(event.currentTarget)
    const payload = {
      academic_year_id: academicYear?.id ?? null,
      name: String(form.get('name') ?? '').trim(),
      start_date: form.get('start_date') || null,
      end_date: form.get('end_date') || null,
      description: String(form.get('description') ?? '').trim() || null,
      is_active: form.get('is_active') === '1',
    }

    try {
      if (isEdit) {
        await api.put(`/academic-sessions/${sessionId}`, payload)
        toast.success('Academic session updated')
        navigate('/academic-sessions')
      } else {
        await api.post('/academic-sessions', payload)
        toast.success('Academic session created')
        setFormKey((k) => k + 1) // remount → fields clear, year stays selected
      }
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to save the academic session.',
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
        <Link
          to="/academic-sessions"
          className={buttonVariants({ variant: 'outline' })}
        >
          <ArrowLeft aria-hidden="true" /> Back to academic sessions
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <Link
        to="/academic-sessions"
        className={buttonVariants({ variant: 'ghost' })}
      >
        <ArrowLeft aria-hidden="true" /> Back to academic sessions
      </Link>

      <Card className="py-8">
        <CardHeader>
          <CardTitle>
            {isEdit ? 'Edit Academic Session' : 'Create Academic Session'}
          </CardTitle>
          <CardDescription>
            {isEdit
              ? 'Update the academic session details below.'
              : 'Register a new term or session under an academic year.'}
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form
            onSubmit={handleSubmit}
            className="space-y-5"
            noValidate
            key={formKey}
          >
            {/* One input per row until lg; then two columns (fewer than 6 inputs). */}
            <div className="grid gap-5 lg:grid-cols-2">
              <FormField
                id="academic_year_id"
                label="Academic Year"
                name="academic_year"
                required
                error={errors.academic_year_id}
              >
                <SearchableSelect
                  id="academic_year_select"
                  value={academicYear?.id ?? null}
                  selectedLabel={
                    academicYear?.name ?? '-- Choose an academic year --'
                  }
                  onChange={(nextId, option) =>
                    setAcademicYear(
                      nextId ? { id: nextId, name: option?.name ?? '' } : null,
                    )
                  }
                  fetchOptions={(query, signal) =>
                    api
                      .get('/academic-years', {
                        params: { per_page: 100, ...(query ? { search: query } : {}) },
                        signal,
                      })
                      .then(({ data }) =>
                        data.academic_years.map((y) => ({
                          id: y.id,
                          name: y.name,
                        })),
                      )
                  }
                  placeholder="-- Choose an academic year --"
                  searchPlaceholder="Type an academic year to search…"
                />
              </FormField>

              <FormField
                id="name"
                label="Name"
                name="name"
                required
                defaultValue={session?.name ?? ''}
                placeholder="e.g. Term 1 2026/2027"
                autoComplete="off"
                error={errors.name}
              />

              <FormField
                id="start_date"
                label="Start Date"
                name="start_date"
                type="date"
                defaultValue={session?.start_date ?? ''}
                error={errors.start_date}
              />

              <FormField
                id="end_date"
                label="End Date"
                name="end_date"
                type="date"
                defaultValue={session?.end_date ?? ''}
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
                    defaultChecked={session?.is_active ?? false}
                  />
                  Academic session is currently active
                </label>
              </div>
            </div>

            <FormField
              id="description"
              label="Description"
              name="description"
              multiline
              rows={3}
              defaultValue={session?.description ?? ''}
              placeholder="Notes about this academic session"
              error={errors.description}
            />

            <div className="flex items-center gap-3">
              <Button type="submit" disabled={submitting}>
                {submitting
                  ? 'Saving…'
                  : isEdit
                    ? 'Save Changes'
                    : 'Create Academic Session'}
              </Button>
              <Link
                to="/academic-sessions"
                className={buttonVariants({ variant: 'outline' })}
              >
                Cancel
              </Link>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
