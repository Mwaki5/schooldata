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
import { Select } from '@/components/ui/select'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import api, { loadErrorMessage, validationErrors } from '@/lib/api'

/** Course options everywhere show "CODE — Name", matching StudentForm. */
function courseLabel(course) {
  if (!course) {
    return ''
  }

  return course.code ? `${course.code} — ${course.name}` : course.name
}

function unitLabel(unit) {
  if (!unit) {
    return ''
  }

  return `${unit.code} — ${unit.name}`
}

/**
 * Assign one catalogue unit to one course + curriculum pair, with the
 * teaching load (module, hours, credit) and the unit's role there
 * (basic / core / common, active). The identity is fixed once the link
 * exists; editing only touches the delivery fields.
 */
export default function CourseUnitForm() {
  const { courseUnitId } = useParams()
  const isEdit = Boolean(courseUnitId)
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()

  const [link, setLink] = useState(null)
  const [unit, setUnit] = useState(null) // { id, name }
  const [course, setCourse] = useState(null) // { id, name }
  const [curriculum, setCurriculum] = useState(null) // { id, name }
  const [loading, setLoading] = useState(isEdit)
  const [loadError, setLoadError] = useState(null)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)

  // Load the form state on mount: on edit the link with its unit, course
  // and curriculum; on create whatever was preselected from a unit's or
  // a course's own page (?unit=…&course=…&curriculum=…).
  useEffect(() => {
    let active = true

    if (isEdit) {
      api
        .get(`/course-units/${courseUnitId}`)
        .then(({ data }) => {
          if (active) {
            const loaded = data.course_unit
            setLink(loaded)
            setUnit(
              loaded.unit ? { id: loaded.unit.id, name: unitLabel(loaded.unit) } : null,
            )
            setCourse(
              loaded.course
                ? { id: loaded.course.id, name: courseLabel(loaded.course) }
                : null,
            )
            setCurriculum(
              loaded.curriculum
                ? { id: loaded.curriculum.id, name: loaded.curriculum.name }
                : null,
            )
          }
        })
        .catch((error) => {
          if (active) {
            setLoadError(
              loadErrorMessage(
                error,
                'Course unit',
                'Unable to load the course unit. It may have been unlinked.',
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

    const preselectedUnitId = searchParams.get('unit')
    const preselectedCourseId = searchParams.get('course')
    const preselectedCurriculumId = searchParams.get('curriculum')

    const lookups = []

    if (preselectedUnitId) {
      lookups.push(
        api
          .get(`/units/${preselectedUnitId}`)
          .then(({ data }) => {
            if (active) {
              setUnit({ id: data.unit.id, name: unitLabel(data.unit) })
            }
          })
          .catch(() => {}),
      )
    }

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
  }, [isEdit, courseUnitId, searchParams])

  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const formData = new FormData(event.currentTarget)
    const numberOrNull = (value) =>
      value === '' || value == null ? null : Number(value)

    const payload = {
      // The identity (unit, course, curriculum) is only chosen when the
      // link is created; updates carry the delivery fields alone.
      ...(isEdit
        ? {}
        : {
            unit_id: unit?.id ?? null,
            course_id: course?.id ?? null,
            curriculum_id: curriculum?.id ?? null,
          }),
      modules_taught: numberOrNull(formData.get('modules_taught')),
      taught_hours: numberOrNull(formData.get('taught_hours')),
      credit_factor: numberOrNull(formData.get('credit_factor')),
      unit_type: String(formData.get('unit_type') ?? 'core'),
      is_active: formData.get('is_active') === '1',
    }

    try {
      if (isEdit) {
        await api.put(`/course-units/${courseUnitId}`, payload)
        toast.success(`Course unit for "${unit?.name ?? 'unit'}" updated`)
      } else {
        await api.post('/course-units', payload)
        toast.success(`"${unit?.name ?? 'Unit'}" assigned to the course`)
      }

      navigate('/course-units')
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to save the course unit.',
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
      <div className="mx-auto max-w-4xl space-y-6">
        <div
          role="alert"
          className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
        >
          {loadError}
        </div>
        <Link
          to="/course-units"
          className={buttonVariants({ variant: 'outline' })}
        >
          <ArrowLeft aria-hidden="true" /> Back to course units
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <Link
        to="/course-units"
        className={buttonVariants({ variant: 'ghost' })}
      >
        <ArrowLeft aria-hidden="true" /> Back to course units
      </Link>

      <Card className="py-8">
        <CardHeader>
          <CardTitle>
            {isEdit ? 'Edit Course Unit' : 'Link Unit to Course'}
          </CardTitle>
          <CardDescription>
            {isEdit
              ? 'Adjust how this unit is taught in the course. The unit, course and curriculum themselves are fixed once linked.'
              : 'Choose a catalogue unit and a course + curriculum to teach it under, with its teaching load, credit factor and role.'}
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-5" noValidate>
            <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
              {isEdit ? (
                <div className="space-y-2">
                  <span className="text-xs font-medium text-muted-foreground uppercase">
                    Unit
                  </span>
                  <p className="truncate rounded-xl border bg-muted/40 px-3 py-2 text-sm">
                    {unit?.name ?? '—'}
                  </p>
                </div>
              ) : (
                <FormField
                  id="unit_id"
                  label="Unit"
                  required
                  error={errors.unit_id}
                >
                  <SearchableSelect
                    id="unit_select"
                    value={unit?.id ?? null}
                    selectedLabel={unit?.name ?? '-- Choose a unit --'}
                    onChange={(nextId, option) =>
                      setUnit(
                        nextId ? { id: nextId, name: option?.name ?? '' } : null,
                      )
                    }
                    fetchOptions={(query, signal) =>
                      api
                        .get('/units', {
                          params: {
                            per_page: 100,
                            ...(query ? { search: query } : {}),
                          },
                          signal,
                        })
                        .then(({ data }) =>
                          data.units.map((u) => ({ id: u.id, name: unitLabel(u) })),
                        )
                    }
                    placeholder="-- Choose a unit --"
                    searchPlaceholder="Type a unit code or name…"
                  />
                </FormField>
              )}

              {isEdit ? (
                <>
                  <div className="space-y-2">
                    <span className="text-xs font-medium text-muted-foreground uppercase">
                      Course
                    </span>
                    <p className="truncate rounded-xl border bg-muted/40 px-3 py-2 text-sm">
                      {course?.name ?? '—'}
                    </p>
                  </div>
                  <div className="space-y-2">
                    <span className="text-xs font-medium text-muted-foreground uppercase">
                      Curriculum
                    </span>
                    <p className="truncate rounded-xl border bg-muted/40 px-3 py-2 text-sm">
                      {curriculum?.name ?? '—'}
                    </p>
                  </div>
                </>
              ) : (
                <>
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
                        setCourse(
                          nextId
                            ? { id: nextId, name: option?.name ?? '' }
                            : null,
                        )
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
                          nextId
                            ? { id: nextId, name: option?.name ?? '' }
                            : null,
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
                </>
              )}

              <FormField
                id="modules_taught"
                label="Module"
                name="modules_taught"
                type="number"
                min={0}
                max={32767}
                defaultValue={link?.modules_taught ?? ''}
                placeholder="e.g. 2"
                error={errors.modules_taught}
              />

              <FormField
                id="taught_hours"
                label="Allocated Hours"
                name="taught_hours"
                type="number"
                min={0}
                max={32767}
                defaultValue={link?.taught_hours ?? ''}
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
                defaultValue={link?.credit_factor ?? ''}
                placeholder="e.g. 2.5"
                error={errors.credit_factor}
              />

              <FormField
                id="unit_type"
                label="Unit Type"
                required
                error={errors.unit_type}
              >
                <Select
                  id="unit_type"
                  name="unit_type"
                  defaultValue={link?.unit_type ?? 'core'}
                >
                  <option value="basic">Basic Unit</option>
                  <option value="core">Core Unit</option>
                  <option value="common">Common Unit</option>
                </Select>
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
                    defaultChecked={link ? link.is_active : true}
                  />
                  Unit is currently offered in this course
                </label>
                {errors.is_active && (
                  <p className="text-xs text-destructive">{errors.is_active}</p>
                )}
              </div>
            </div>

            <div className="flex items-center gap-3">
              <Button type="submit" disabled={submitting}>
                {submitting
                  ? 'Saving…'
                  : isEdit
                    ? 'Save Changes'
                    : 'Link Unit'}
              </Button>
              <Link
                to="/course-units"
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
