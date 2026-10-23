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
import { Select } from '@/components/ui/select'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import api, { loadErrorMessage, validationErrors } from '@/lib/api'

// Mirrors the backend's TermEventName enum — the closed vocabulary of
// calendar events a session can carry.
const EVENT_NAMES = [
  'Session Registration',
  'Graduation',
  'Midterm Exam',
  'End Term Exams',
  'School Break',
]

export default function TermEventForm() {
  const { eventId } = useParams()
  const isEdit = Boolean(eventId)
  const navigate = useNavigate()

  const [event, setEvent] = useState(null)
  const [academicSession, setAcademicSession] = useState(null) // { id, name }
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
      .get(`/term-events/${eventId}`)
      .then(({ data }) => {
        if (active) {
          const ev = data.term_event
          setEvent(ev)
          setAcademicSession(
            ev.academic_session_id
              ? {
                  id: ev.academic_session_id,
                  name: ev.academic_session_name ?? '',
                }
              : null,
          )
        }
      })
      .catch((error) => {
        if (active) {
          setLoadError(
            loadErrorMessage(
              error,
              'Term event',
              'Unable to load the term event. It may have been deleted.',
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
  }, [isEdit, eventId])

  const handleSubmit = async (formEvent) => {
    formEvent.preventDefault()

    setErrors({})
    setSubmitting(true)

    const form = new FormData(formEvent.currentTarget)
    const payload = {
      academic_session_id: academicSession?.id ?? null,
      name: String(form.get('name') ?? '').trim(),
      start_date: form.get('start_date') || null,
      end_date: form.get('end_date') || null,
      is_active: form.get('is_active') === '1',
    }

    try {
      if (isEdit) {
        await api.put(`/term-events/${eventId}`, payload)
        toast.success('Term event updated')
        navigate('/term-events')
      } else {
        await api.post('/term-events', payload)
        toast.success('Term event created')
        setFormKey((k) => k + 1) // remount → fields clear, session stays selected
      }
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to save the term event.',
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
        <Link to="/term-events" className={buttonVariants({ variant: 'outline' })}>
          <ArrowLeft aria-hidden="true" /> Back to term events
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <Link to="/term-events" className={buttonVariants({ variant: 'ghost' })}>
        <ArrowLeft aria-hidden="true" /> Back to term events
      </Link>

      <Card className="py-8">
        <CardHeader>
          <CardTitle>
            {isEdit ? 'Edit Term Event' : 'Create Term Event'}
          </CardTitle>
          <CardDescription>
            {isEdit
              ? 'Update the term event details below.'
              : 'Add a new event to an academic session.'}
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-5" noValidate key={formKey}>
            {/* One input per row until lg; then two columns (fewer than 6 inputs). */}
            <div className="grid gap-5 lg:grid-cols-2">
              <FormField
                id="academic_session_id"
                label="Academic Session"
                name="academic_session"
                required
                error={errors.academic_session_id}
              >
                <SearchableSelect
                  id="academic_session_select"
                  value={academicSession?.id ?? null}
                  selectedLabel={
                    academicSession?.name ?? '-- Choose an academic session --'
                  }
                  onChange={(nextId, option) =>
                    setAcademicSession(
                      nextId ? { id: nextId, name: option?.name ?? '' } : null,
                    )
                  }
                  fetchOptions={(query, signal) =>
                    api
                      .get('/academic-sessions/options', {
                        params: {
                          is_active: 1,
                          ...(query ? { search: query } : {}),
                        },
                        signal,
                      })
                      .then(({ data }) =>
                        data.academic_sessions.map((s) => ({
                          id: s.id,
                          name: s.name,
                        })),
                      )
                  }
                  placeholder="-- Choose an academic session --"
                  searchPlaceholder="Type a session to search…"
                />
              </FormField>

              <FormField
                id="name"
                label="Event"
                name="name"
                required
                error={errors.name}
              >
                <Select
                  id="event-name-select"
                  name="name"
                  defaultValue={event?.name ?? ''}
                >
                  <option value="" disabled>
                    -- Choose an event --
                  </option>
                  {EVENT_NAMES.map((name) => (
                    <option key={name} value={name}>
                      {name}
                    </option>
                  ))}
                </Select>
              </FormField>

              <FormField
                id="start_date"
                label="Start Date"
                name="start_date"
                type="date"
                required
                defaultValue={event?.start_date ?? ''}
                error={errors.start_date}
              />

              <FormField
                id="end_date"
                label="End Date"
                name="end_date"
                type="date"
                required
                defaultValue={event?.end_date ?? ''}
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
                    defaultChecked={event?.is_active ?? false}
                  />
                  Term event is currently active
                </label>
                <p className="text-xs text-muted-foreground">
                  Several events may be active at once — unlike years and
                  terms, activating one does not retire the others.
                </p>
              </div>
            </div>

            <div className="flex items-center gap-3">
              <Button type="submit" disabled={submitting}>
                {submitting
                  ? 'Saving…'
                  : isEdit
                  ? 'Save Changes'
                  : 'Create Term Event'}
              </Button>
              <Link to="/term-events" className={buttonVariants({ variant: 'outline' })}>
                Cancel
              </Link>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
