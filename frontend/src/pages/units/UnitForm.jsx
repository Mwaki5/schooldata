import { ArrowLeft } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { toast } from 'sonner'
import PageLoader from '@/components/page-loader'
import { FormField } from '@/components/form-field'
import { Button, buttonVariants } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import api, { loadErrorMessage, validationErrors } from '@/lib/api'

/** Course options everywhere show "CODE — Name", matching StudentForm. */
function courseLabel(course) {
  if (!course) {
    return ''
  }

  return course.code ? `${course.code} — ${course.name}` : course.name
}

export default function UnitForm() {
  const { unitId } = useParams()
  const isEdit = Boolean(unitId)
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()

  const [unit, setUnit] = useState(null)
  const [course, setCourse] = useState(null) // { id, name }
  const [curriculum, setCurriculum] = useState(null) // { id, name }
  const [loading, setLoading] = useState(isEdit)
  const [loadError, setLoadError] = useState(null)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const [formKey, setFormKey] = useState(0) // remount to clear fields after create

  // Load the form state on mount: the unit itself on edit, and the
  // course / curriculum preselected from a course's own page
  // (/units/create?course=…&curriculum=…) on create.
  useEffect(() => {
    let active = true

    if (isEdit) {
      api
        .get(`/units/${unitId}`)
        .then(({ data }) => {
          if (active) {
            const loaded = data.unit
            setUnit(loaded)
            setCourse(
              loaded.course_id
                ? {
                    id: loaded.course_id,
                    name: courseLabel(loaded.course),
                  }
                : null,
            )
            setCurriculum(
              loaded.curriculum_id
                ? { id: loaded.curriculum_id, name: loaded.curriculum?.name ?? '' }
                : null,
            )
          }
        })
        .catch((error) => {
          if (active) {
            setLoadError(
              loadErrorMessage(
                error,
                'Unit',
                'Unable to load the unit. It may have been deleted.',
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
    }

    const preselectedCourseId = searchParams.get('course')
    const preselectedCurriculumId = searchParams.get('curriculum')

    const lookups = []

    if (preselectedCourseId) {
      lookups.push(
        api
          .get(`/courses/${preselectedCourseId}`)
          .then(({ data }) => {
            if (active) {
              setCourse({
                id: data.course.id,
                name: courseLabel(data.course),
              })
            }
          })
          .catch(() => {}),
      )
    }

    if (preselectedCurriculumId) {
      lookups.push(
        api
          .get(`/curricula/${preselectedCurriculumId}`)
          .then(({ data }) => {
            if (active) {
              setCurriculum({ id: data.curriculum.id, name: data.curriculum.name })
            }
          })
          .catch(() => {}),
      )
    }

    Promise.all(lookups).finally(() => {
      if (active) {
        setLoading(false)
      }
    })

    return () => {
      active = false
    }
  }, [isEdit, unitId, searchParams])

  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const formData = new FormData(event.currentTarget)
    const numberOrNull = (value) =>
      value === '' || value == null ? null : Number(value)

    const payload = {
      course_id: course?.id ?? null,
      curriculum_id: curriculum?.id ?? null,
      code: String(formData.get('code') ?? '').trim(),
      name: String(formData.get('name') ?? '').trim(),
      description: String(formData.get('description') ?? '').trim() || null,
      modules_taught: numberOrNull(formData.get('modules_taught')),
      taught_hours: numberOrNull(formData.get('taught_hours')),
      credit_factor: numberOrNull(formData.get('credit_factor')),
      core: formData.get('core') === '1',
      is_active: formData.get('is_active') === '1',
    }

    try {
      if (isEdit) {
        await api.put(`/units/${unitId}`, payload)
        toast.success('Unit updated')
        navigate(`/units/${unitId}`)
      } else {
        await api.post('/units', payload)

        toast.success(`"${payload.name}" added to the course`)
        setFormKey((k) => k + 1) // remount → scalars clear, pickers stay selected
      }
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to save the unit.',
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
        <Link to="/units" className={buttonVariants({ variant: 'outline' })}>
          <ArrowLeft aria-hidden="true" /> Back to units
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <Link to="/units" className={buttonVariants({ variant: 'ghost' })}>
        <ArrowLeft aria-hidden="true" /> Back to units
      </Link>

      <Card className="py-8">
        <CardHeader>
          <CardTitle>{isEdit ? 'Edit Unit' : 'Add Unit to Course'}</CardTitle>
          <CardDescription>
            {isEdit
              ? 'Update the unit below. Its code must stay unique within its course and curriculum.'
              : 'Offer a unit under one course and curriculum. The same code may be reused by other courses.'}
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-5" noValidate key={formKey}>
            {/* Small screens: one input per row; md and up: two per row;
                lg and up: three per row. */}
            <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
              <FormField
                id="course_id"
                label="Course"
                required
                error={errors.course_id}
              >
                <SearchableSelect
                  id="course_select"
                  value={course?.id ?? null}
                  selectedLabel={course?.name ?? '-- Choose a course --'}
                  onChange={(nextId, option) =>
                    setCourse(nextId ? { id: nextId, name: option?.name ?? '' } : null)
                  }
                  fetchOptions={(query, signal) =>
                    api
                      .get('/courses', {
                        params: {
                          per_page: 100,
                          ...(query ? { search: query } : {}),
                        },
                        signal,
                      })
                      .then(({ data }) =>
                        data.courses.map((c) => ({
                          id: c.id,
                          name: courseLabel(c),
                        })),
                      )
                  }
                  placeholder="-- Choose a course --"
                  searchPlaceholder="Type a course code or name…"
                />
              </FormField>

              <FormField
                id="curriculum_id"
                label="Curriculum"
                required
                error={errors.curriculum_id}
              >
                <SearchableSelect
                  id="curriculum_select"
                  value={curriculum?.id ?? null}
                  selectedLabel={
                    curriculum?.name ?? '-- Choose a curriculum --'
                  }
                  onChange={(nextId, option) =>
                    setCurriculum(
                      nextId ? { id: nextId, name: option?.name ?? '' } : null,
                    )
                  }
                  fetchOptions={(query, signal) =>
                    api
                      .get('/curricula', {
                        params: {
                          per_page: 100,
                          ...(query ? { search: query } : {}),
                        },
                        signal,
                      })
                      .then(({ data }) =>
                        data.curricula.map((c) => ({
                          id: c.id,
                          name: c.name,
                        })),
                      )
                  }
                  placeholder="-- Choose a curriculum --"
                  searchPlaceholder="Type to find a curriculum…"
                />
              </FormField>

              <FormField
                id="code"
                label="Code"
                name="code"
                required
                defaultValue={unit?.code ?? ''}
                placeholder="e.g. BCS101"
                autoComplete="off"
                maxLength={50}
                error={errors.code}
              />

              <FormField
                id="name"
                label="Name"
                name="name"
                required
                defaultValue={unit?.name ?? ''}
                placeholder="e.g. Basic Computer Skills"
                autoComplete="off"
                maxLength={255}
                error={errors.name}
              />

              <FormField
                id="modules_taught"
                label="Modules Taught"
                name="modules_taught"
                type="number"
                min={0}
                max={32767}
                defaultValue={unit?.modules_taught ?? ''}
                placeholder="e.g. 8"
                error={errors.modules_taught}
              />

              <FormField
                id="taught_hours"
                label="Training Hours"
                name="taught_hours"
                type="number"
                min={0}
                max={32767}
                defaultValue={unit?.taught_hours ?? ''}
                placeholder="e.g. 45"
                error={errors.taught_hours}
              />

              <FormField
                id="credit_factor"
                label="Credit Factor"
                name="credit_factor"
                type="number"
                min={0}
                step="0.01"
                defaultValue={unit?.credit_factor ?? ''}
                placeholder="e.g. 2.5"
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
                    defaultChecked={unit?.core ?? true}
                  />
                  Required for the qualification
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
                    defaultChecked={unit?.is_active ?? true}
                  />
                  Unit is currently offered
                </label>
              </div>
            </div>

            <FormField
              id="description"
              label="Description"
              name="description"
              multiline
              rows={3}
              defaultValue={unit?.description ?? ''}
              placeholder="What the unit covers (optional)"
              error={errors.description}
            />

            <div className="flex items-center gap-3">
              <Button type="submit" disabled={submitting}>
                {submitting
                  ? 'Saving…'
                  : isEdit
                    ? 'Save Changes'
                    : 'Add Unit'}
              </Button>
              <Link to="/units" className={buttonVariants({ variant: 'outline' })}>
                Cancel
              </Link>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
