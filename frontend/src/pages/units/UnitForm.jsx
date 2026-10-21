import { ArrowLeft, ArrowRight, Check } from 'lucide-react'
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
import { cn } from '@/lib/utils'

/** Course options everywhere show "CODE — Name", matching StudentForm. */
function courseLabel(course) {
  if (!course) {
    return ''
  }

  return course.code ? `${course.code} — ${course.name}` : course.name
}

/** The numbered step circles from the Open Session wizard. */
function StepMarker({ number, label, active, done }) {
  return (
    <div className="flex items-center gap-2">
      <span
        className={cn(
          'flex size-7 items-center justify-center rounded-full border text-xs font-semibold',
          active && 'border-primary bg-primary text-primary-foreground',
          done && 'border-emerald-500 bg-emerald-500 text-white',
          !active && !done && 'border-input text-muted-foreground',
        )}
      >
        {done ? <Check aria-hidden="true" className="size-3.5" /> : number}
      </span>
      <span
        className={cn(
          'text-sm font-medium',
          active || done ? 'text-foreground' : 'text-muted-foreground',
        )}
      >
        {label}
      </span>
    </div>
  )
}

/**
 * The unit wizard, in two steps like the Open Session flow: step one
 * creates (or edits) the catalogue unit itself — code, name,
 * description — and step two assigns it to a course + curriculum with
 * its teaching load, credit factor and role.
 */
export default function UnitForm() {
  const { unitId } = useParams()
  const isEdit = Boolean(unitId)
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()

  const [step, setStep] = useState(1)
  const [unit, setUnit] = useState(null)
  // The unit's existing course link, when the wizard edits one.
  const [courseUnit, setCourseUnit] = useState(null)
  const [course, setCourse] = useState(null) // { id, name }
  const [curriculum, setCurriculum] = useState(null) // { id, name }
  const [loading, setLoading] = useState(isEdit)
  const [loadError, setLoadError] = useState(null)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)

  // Load the form state on mount: the unit with its course links on
  // edit, and the course / curriculum preselected from a course's own
  // page (/units/create?course=…&curriculum=…) on create.
  useEffect(() => {
    let active = true

    if (isEdit) {
      api
        .get(`/units/${unitId}`)
        .then(({ data }) => {
          if (active) {
            const loaded = data.unit
            setUnit(loaded)

            const link = loaded.course_units?.[0] ?? null
            setCourseUnit(link)

            if (link) {
              setCourse(
                link.course
                  ? { id: link.course.id, name: courseLabel(link.course) }
                  : null,
              )
              setCurriculum(
                link.curriculum
                  ? { id: link.curriculum.id, name: link.curriculum.name }
                  : null,
              )
            }
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

  /** Step one: save the unit's own details, then move on. */
  const handleUnitSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const formData = new FormData(event.currentTarget)
    const payload = {
      code: String(formData.get('code') ?? '').trim(),
      name: String(formData.get('name') ?? '').trim(),
      description: String(formData.get('description') ?? '').trim() || null,
    }

    try {
      if (isEdit) {
        const { data } = await api.put(`/units/${unitId}`, payload)
        setUnit(data.unit)
        toast.success('Unit details saved')
      } else {
        const { data } = await api.post('/units', payload)
        setUnit(data.unit)
      }

      setErrors({})
      setStep(2)
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

  /** Step two: save the course-unit assignment, then open the unit. */
  const handleCourseUnitSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const formData = new FormData(event.currentTarget)
    const numberOrNull = (value) =>
      value === '' || value == null ? null : Number(value)

    const payload = {
      // The identity is fixed once the link exists; a fresh link still
      // picks its unit, course and curriculum here.
      ...(courseUnit
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
      if (courseUnit) {
        await api.put(`/course-units/${courseUnit.id}`, payload)
        toast.success(`Course unit for "${unit.name}" updated`)
      } else {
        await api.post('/course-units', payload)
        toast.success(`"${unit.name}" assigned to the course`)
      }

      navigate(`/units/${unit.id}`)
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

      <div className="flex flex-wrap items-center gap-4 rounded-2xl border bg-card px-5 py-4">
        <StepMarker
          number={1}
          label="Unit"
          active={step === 1}
          done={step > 1}
        />
        <span className="text-muted-foreground">/</span>
        <StepMarker
          number={2}
          label="Course Unit"
          active={step === 2}
          done={false}
        />
      </div>

      {step === 1 && (
        <Card className="py-8">
          <CardHeader>
            <CardTitle>
              {isEdit ? '1. Unit details' : '1. Create the unit'}
            </CardTitle>
            <CardDescription>
              {isEdit
                ? 'The unit’s own identity in the catalogue. Its deliveries under courses live on the next step.'
                : 'The unit’s own identity in the catalogue: one code, one name. On the next step you assign it to a course.'}
            </CardDescription>
          </CardHeader>

          <CardContent>
            <form
              onSubmit={handleUnitSubmit}
              className="space-y-5"
              noValidate
            >
              <div className="grid gap-5 md:grid-cols-2">
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
                  {submitting ? 'Saving…' : 'Continue'}
                  {!submitting && <ArrowRight aria-hidden="true" />}
                </Button>
                <Link
                  to="/units"
                  className={buttonVariants({ variant: 'outline' })}
                >
                  Cancel
                </Link>
              </div>
            </form>
          </CardContent>
        </Card>
      )}

      {step === 2 && unit && (
        <Card className="py-8">
          <CardHeader>
            <CardTitle>2. Course unit</CardTitle>
            <CardDescription>
              {courseUnit
                ? 'Where this unit is taught: adjust its teaching load, credit factor and role inside the course.'
                : `Assign "${unit.name}" to a course and curriculum with its teaching load, credit factor and role.`}
            </CardDescription>
            {(unit.course_units_count ?? 0) > 1 && (
              <p className="text-xs text-muted-foreground">
                This unit is linked to {unit.course_units_count} courses.
                Here you edit the selected one; manage every link under
                Course Units.
              </p>
            )}
          </CardHeader>

          <CardContent>
            <form
              onSubmit={handleCourseUnitSubmit}
              className="space-y-5"
              noValidate
            >
              <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                {courseUnit ? (
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
                  defaultValue={courseUnit?.modules_taught ?? ''}
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
                  defaultValue={courseUnit?.taught_hours ?? ''}
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
                  defaultValue={courseUnit?.credit_factor ?? ''}
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
                    defaultValue={courseUnit?.unit_type ?? 'core'}
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
                      defaultChecked={
                        courseUnit ? courseUnit.is_active : true
                      }
                    />
                    Unit is currently offered in this course
                  </label>
                </div>
              </div>

              <div className="flex items-center gap-3">
                <Button
                  type="submit"
                  disabled={submitting}
                >
                  {submitting
                    ? 'Saving…'
                    : courseUnit
                      ? 'Save Changes'
                      : 'Assign Unit'}
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  disabled={submitting}
                  onClick={() => {
                    setErrors({})
                    setStep(1)
                  }}
                >
                  <ArrowLeft aria-hidden="true" /> Back
                </Button>
                <Link
                  to="/units"
                  className={buttonVariants({ variant: 'ghost' })}
                >
                  Cancel
                </Link>
              </div>
            </form>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
