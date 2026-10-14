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
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import api, { validationErrors } from '@/lib/api'

export default function CourseForm() {
  const { courseId } = useParams()
  const isEdit = Boolean(courseId)
  const navigate = useNavigate()

  const [course, setCourse] = useState(null)
  const [certificationLevel, setCertificationLevel] = useState(null) // { id, name }
  const [department, setDepartment] = useState(null) // { id, name }
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
      .get(`/courses/${courseId}`)
      .then(({ data }) => {
        if (active) {
          const cd = data.course
          setCourse(cd)
          setCertificationLevel(
            cd.certification_level_id
              ? { id: cd.certification_level_id, name: cd.certification_level_name ?? '' }
              : null,
          )
          setDepartment(
            cd.department_id
              ? { id: cd.department_id, name: cd.department_name ?? '' }
              : null,
          )
        }
      })
      .catch(() => {
        if (active) {
          setLoadError(
            'Unable to load the course. It may have been deleted.',
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
  }, [isEdit, courseId])

  // On create: keep the picker selections; after submit only clear the scalar
  // fields. The pickers stay selected so the user can add several courses in a
  // row. The form remounts (formKey increment) to reset uncontrolled inputs
  // while keeping the SearchableSelect values.
  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const formData = new FormData(event.currentTarget)
    const duration = formData.get('duration_months')
    const payload = {
      code: String(formData.get('code') ?? '').trim().toUpperCase(),
      initials: String(formData.get('initials') ?? '').trim().toUpperCase(),
      name: String(formData.get('name') ?? '').trim(),
      duration_months: duration === '' || duration == null ? null : Number(duration),
      description: String(formData.get('description') ?? '').trim() || null,
      is_active: formData.get('is_active') === '1',
      certification_level_id: certificationLevel?.id ?? null,
      department_id: department?.id ?? null,
    }

    try {
      if (isEdit) {
        await api.put(`/courses/${courseId}`, payload)
        toast.success('Course updated')
        navigate('/courses')
      } else {
        await api.post('/courses', payload)
        toast.success('Course created')
        setFormKey((k) => k + 1) // remount → fields clear, pickers stay selected
      }
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to save the course.',
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
        <Link to="/courses" className={buttonVariants({ variant: 'outline' })}>
          <ArrowLeft aria-hidden="true" /> Back to courses
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <Link to="/courses" className={buttonVariants({ variant: 'ghost' })}>
        <ArrowLeft aria-hidden="true" /> Back to courses
      </Link>

      <Card className="py-8">
        <CardHeader>
          <CardTitle>{isEdit ? 'Edit Course' : 'Create Course'}</CardTitle>
          <CardDescription>
            {isEdit
              ? 'Update the course details below.'
              : 'Register a new course offering.'}
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-5" noValidate key={formKey}>
            {/* Small screens: one input per row; sm and up: two per row. */}
            <div className="grid gap-5 lg:grid-cols-3">
              <FormField
                id="code"
                label="Code"
                name="code"
                required
                defaultValue={course?.code ?? ''}
                placeholder="e.g. ND-IT"
                autoComplete="off"
                maxLength={50}
                error={errors.code}
              />

              <FormField
                id="initials"
                label="Initials"
                name="initials"
                required
                defaultValue={course?.initials ?? ''}
                placeholder="e.g. IT"
                autoComplete="off"
                maxLength={20}
                error={errors.initials}
              />

              <FormField
                id="name"
                label="Name"
                name="name"
                required
                defaultValue={course?.name ?? ''}
                placeholder="e.g. National Diploma in IT"
                autoComplete="off"
                error={errors.name}
              />

              <FormField
                id="duration_months"
                label="Duration (months)"
                name="duration_months"
                type="number"
                min={0}
                max={65535}
                defaultValue={course?.duration_months ?? ''}
                placeholder="e.g. 24"
                error={errors.duration_months}
              />

              {/* Certification Level with SearchableSelect (optional) */}
              <FormField
                id="certification_level_id"
                label="Certification Level"
                name="certification_level"
                error={errors.certification_level_id}
              >
                <SearchableSelect
                  id="certification_level_select"
                  value={certificationLevel?.id ?? null}
                  selectedLabel={
                    certificationLevel?.name ?? '-- Choose a certification level --'
                  }
                  onChange={(nextId, option) =>
                    setCertificationLevel(
                      nextId ? { id: nextId, name: option?.name ?? '' } : null,
                    )
                  }
                  fetchOptions={(query, signal) =>
                    api
                      .get('/certification-levels', {
                        params: { per_page: 100 },
                        signal,
                      })
                      .then(({ data }) =>
                        data.certification_levels
                          .filter(
                            (l) =>
                              !query ||
                              l.name.toLowerCase().includes(query.toLowerCase()),
                          )
                          .map((l) => ({ id: l.id, name: l.name })),
                      )
                  }
                  placeholder="-- Choose a certification level --"
                  searchPlaceholder="Type to find a level…"
                />
              </FormField>

              {/* Department with SearchableSelect (optional) */}
              <FormField
                id="department_id"
                label="Department"
                name="department"
                error={errors.department_id}
              >
                <SearchableSelect
                  id="department_select"
                  value={department?.id ?? null}
                  selectedLabel={department?.name ?? '-- Choose a department --'}
                  onChange={(nextId, option) =>
                    setDepartment(
                      nextId ? { id: nextId, name: option?.name ?? '' } : null,
                    )
                  }
                  fetchOptions={(query, signal) =>
                    api
                      .get('/departments', {
                        params: { per_page: 100 },
                        signal,
                      })
                      .then(({ data }) =>
                        data.departments
                          .filter(
                            (d) =>
                              !query ||
                              d.name.toLowerCase().includes(query.toLowerCase()),
                          )
                          .map((d) => ({ id: d.id, name: d.name })),
                      )
                  }
                  placeholder="-- Choose a department --"
                  searchPlaceholder="Type to find a department…"
                />
              </FormField>

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
                    defaultChecked={course?.is_active ?? true}
                  />
                  Course is currently active
                </label>
              </div>
            </div>

            <FormField
              id="description"
              label="Description"
              name="description"
              multiline
              rows={3}
              defaultValue={course?.description ?? ''}
              placeholder="Details about the course"
              error={errors.description}
            />

            <div className="flex items-center gap-3">
              <Button type="submit" disabled={submitting}>
                {submitting
                  ? 'Saving…'
                  : isEdit
                  ? 'Save Changes'
                  : 'Create Course'}
              </Button>
              <Link to="/courses" className={buttonVariants({ variant: 'outline' })}>
                Cancel
              </Link>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
