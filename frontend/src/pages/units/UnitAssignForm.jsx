import { ArrowLeft, ArrowRight, Check, Link2, X } from 'lucide-react'
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
import { cn } from '@/lib/utils'

const STEPS = ['Unit', 'Courses, Curricula & Details', 'Review']

/** Server options for the pickers: "CODE — Name" when a code exists. */
const mapOptions = (entries) =>
  (entries ?? []).map((entry) => ({
    id: entry.id,
    name: entry.code ? `${entry.code} — ${entry.name}` : entry.name,
  }))

const fetchCourses = (query, signal) =>
  api
    .get('/courses', {
      params: { per_page: 20, ...(query ? { search: query } : {}) },
      signal,
    })
    .then(({ data }) => mapOptions(data.courses))

const fetchCurricula = (query, signal) =>
  api
    .get('/curricula', {
      params: { per_page: 20, ...(query ? { search: query } : {}) },
      signal,
    })
    .then(({ data }) => mapOptions(data.curricula))

/** One removable selection chip in the pickers below. */
function SelectionChip({ label, onRemove }) {
  return (
    <span className="inline-flex items-center gap-1.5 rounded-full border bg-muted/50 py-1 pl-2.5 pr-1 text-sm">
      {label}
      <button
        type="button"
        onClick={onRemove}
        aria-label={`Remove ${label}`}
        className="rounded-full p-0.5 text-muted-foreground hover:bg-accent hover:text-foreground"
      >
        <X className="size-3.5" aria-hidden="true" />
      </button>
    </span>
  )
}

/**
 * Assign an existing master unit to one or more course × curriculum
 * combinations. Every combination becomes its own assignment row with
 * its own teaching load (identical at creation time, independently
 * editable afterwards); the unit's code travels with the master.
 *
 * Targets and teaching load share one step: pick courses and curricula
 * through the searchable selects, and the review step shows exactly what
 * will be created.
 *
 * Reached with a unit preselected (`/units/:unitId/assign`) or without
 * (`/units/assign`), where step 1 lets the user pick one.
 */
export default function UnitAssignForm() {
  const { unitId } = useParams()
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()

  const [step, setStep] = useState(1)
  const [unit, setUnit] = useState(null) // { id, name, code }
  const [loading, setLoading] = useState(Boolean(unitId))
  const [loadError, setLoadError] = useState(null)

  const [selectedCourses, setSelectedCourses] = useState([]) // { id, label }
  const [selectedCurricula, setSelectedCurricula] = useState([]) // { id, label }
  const [existing, setExisting] = useState([])

  const [details, setDetails] = useState({
    time_offered: '',
    taught_hours: '',
    credit_factor: '',
    core: true,
  })

  const [errors, setErrors] = useState({})
  const [batchErrors, setBatchErrors] = useState([])
  const [submitting, setSubmitting] = useState(false)

  // The unit itself loads when the route carries one.
  useEffect(() => {
    // `loading` already starts true when the route carries a unit id.
    if (!unitId) {
      return undefined
    }

    let active = true

    api
      .get(`/units/${unitId}`)
      .then(({ data }) => {
        if (active) {
          setUnit({
            id: data.unit.id,
            name: data.unit.name,
            code: data.unit.code,
          })
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
  }, [unitId])

  // Targets preselected from a course's own page ("Assign Unit" on
  // /courses/:id/units carries ?course=…&curriculum=…): the labels are
  // fetched once so the chips show real names, and the user can still
  // add or remove anything before assigning.
  useEffect(() => {
    const preselectedCourseId = Number(searchParams.get('course'))
    const preselectedCurriculumId = Number(searchParams.get('curriculum'))

    if (!preselectedCourseId && !preselectedCurriculumId) {
      return undefined
    }

    let active = true

    if (preselectedCourseId) {
      api
        .get(`/courses/${preselectedCourseId}`)
        .then(({ data }) => {
          if (!active || !data.course) {
            return
          }

          setSelectedCourses((previous) =>
            previous.some((entry) => entry.id === data.course.id)
              ? previous
              : [
                  ...previous,
                  {
                    id: data.course.id,
                    label: data.course.code
                      ? `${data.course.code} — ${data.course.name}`
                      : data.course.name,
                  },
                ],
          )
        })
        .catch(() => {})
    }

    if (preselectedCurriculumId) {
      api
        .get(`/curricula/${preselectedCurriculumId}`)
        .then(({ data }) => {
          if (!active || !data.curriculum) {
            return
          }

          setSelectedCurricula((previous) =>
            previous.some((entry) => entry.id === data.curriculum.id)
              ? previous
              : [
                  ...previous,
                  { id: data.curriculum.id, label: data.curriculum.name },
                ],
          )
        })
        .catch(() => {})
    }

    return () => {
      active = false
    }
  }, [searchParams])

  // Existing assignments of the chosen unit keep the wizard honest:
  // already-assigned combinations are skipped and shown as such. The
  // list resets through `handleUnitChange` instead of an effect body.
  useEffect(() => {
    if (!unit?.id) {
      return undefined
    }

    let active = true

    api
      .get(`/units/${unit.id}/assignments`)
      .then(({ data }) => {
        if (active) {
          setExisting(data.assignments ?? [])
        }
      })
      .catch(() => {
        if (active) {
          setExisting([])
        }
      })

    return () => {
      active = false
    }
  }, [unit?.id])

  const handleUnitChange = (nextId, option) => {
    setUnit(
      nextId
        ? { id: nextId, name: option?.name ?? '', code: option?.code }
        : null,
    )

    // Switching to a different unit starts the targets over; the first
    // pick keeps whatever was preselected (e.g. from a course's page).
    if (unit) {
      setSelectedCourses([])
      setSelectedCurricula([])
      setExisting([])
    }

    setErrors({})
  }

  const addSelection = (setter) => (value, option) => {
    if (!value) {
      return
    }

    setter((previous) =>
      previous.some((entry) => entry.id === value)
        ? previous
        : [...previous, { id: value, label: option?.name ?? '' }],
    )
  }

  const removeSelection = (setter, id) => {
    setter((previous) => previous.filter((entry) => entry.id !== id))
  }

  const existingKeys = new Set(
    existing.map(
      (assignment) => `${assignment.course_id}:${assignment.curriculum_id}`,
    ),
  )

  const combos = []
  let skippedCount = 0

  for (const course of selectedCourses) {
    for (const curriculum of selectedCurricula) {
      if (existingKeys.has(`${course.id}:${curriculum.id}`)) {
        skippedCount += 1
      } else {
        combos.push({ course, curriculum })
      }
    }
  }

  const goNext = () => {
    setErrors({})

    if (step === 1) {
      if (!unit) {
        setErrors({ unit: 'Choose the unit to assign.' })
        return
      }
      setStep(2)
      return
    }

    if (step === 2) {
      if (
        selectedCourses.length === 0 ||
        selectedCurricula.length === 0
      ) {
        setErrors({
          targets: 'Select at least one course and one curriculum.',
        })
        return
      }
      if (combos.length === 0) {
        setErrors({
          targets:
            'Every selected course + curriculum combination already has this unit. Choose different targets or edit the existing assignments.',
        })
        return
      }
      setStep(3)
    }
  }

  const handleSubmit = async () => {
    setBatchErrors([])
    setSubmitting(true)

    const optionalNumber = (value) => {
      const trimmed = String(value ?? '').trim()
      return trimmed === '' ? null : Number(trimmed)
    }

    try {
      await api.post(`/units/${unit.id}/assignments`, {
        assignments: combos.map(({ course, curriculum }) => ({
          course_id: course.id,
          curriculum_id: curriculum.id,
          core: details.core,
          time_offered: details.time_offered.trim() || null,
          taught_hours: optionalNumber(details.taught_hours),
          credit_factor: optionalNumber(details.credit_factor),
        })),
      })

      toast.success(
        `${combos.length} ${combos.length === 1 ? 'assignment' : 'assignments'} created for "${unit.name}"`,
      )
      navigate(`/units/${unit.id}`)
    } catch (error) {
      const fieldErrors = validationErrors(error)
      const messages = Object.values(fieldErrors)

      if (messages.length > 0) {
        setBatchErrors(messages)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to create the assignments.',
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
        <CardHeader className="space-y-4">
          <div>
            <CardTitle>Assign Unit to Courses</CardTitle>
            <CardDescription>
              {unit
                ? `Assigning "${unit.name}" — each selected course and curriculum combination becomes its own assignment.`
                : 'Pick a unit, then the courses and curricula it should be delivered under.'}
            </CardDescription>
          </div>

          {/* Step indicator */}
          <ol className="flex flex-wrap gap-2 text-sm">
            {STEPS.map((label, index) => {
              const number = index + 1

              return (
                <li
                  key={label}
                  className={cn(
                    'flex items-center gap-1.5 rounded-full px-3 py-1 font-medium',
                    number === step
                      ? 'bg-primary text-primary-foreground'
                      : number < step
                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                        : 'bg-muted text-muted-foreground',
                  )}
                >
                  {number < step ? (
                    <Check className="size-3.5" aria-hidden="true" />
                  ) : (
                    <span>{number}</span>
                  )}
                  {label}
                </li>
              )
            })}
          </ol>
        </CardHeader>

        <CardContent className="space-y-5">
          {/* Step 1 — the master unit. */}
          {step === 1 && (
            <div className="space-y-4">
              {unit ? (
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border px-4 py-3 text-sm">
                  <span>
                    Selected unit:{' '}
                    <span className="font-medium">{unit.name}</span>
                    {unit.code && (
                      <span className="ml-1.5 font-mono text-muted-foreground">
                        ({unit.code})
                      </span>
                    )}
                  </span>
                  {!unitId && (
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => handleUnitChange(null, null)}
                    >
                      Change unit
                    </Button>
                  )}
                </div>
              ) : (
                <FormField
                  id="unit_id"
                  label="Unit"
                  required
                  error={errors.unit}
                >
                  <SearchableSelect
                    id="unit_select"
                    value={unit?.id ?? null}
                    selectedLabel={unit?.name ?? '-- Choose a unit --'}
                    onChange={handleUnitChange}
                    fetchOptions={(query, signal) =>
                      api
                        .get('/units', {
                          params: {
                            per_page: 20,
                            ...(query ? { search: query } : {}),
                          },
                          signal,
                        })
                        .then(({ data }) =>
                          (data.units ?? []).map((entry) => ({
                            id: entry.id,
                            name: entry.name,
                            code: entry.code,
                          })),
                        )
                    }
                    placeholder="-- Choose a unit --"
                    searchPlaceholder="Type to find a unit…"
                  />
                </FormField>
              )}
              <p className="text-sm text-muted-foreground">
                The unit stays a single catalogue entry — assigning it never
                duplicates it.
              </p>
            </div>
          )}

          {/* Step 2 — targets and teaching load, one section. */}
          {step === 2 && (
            <div className="space-y-5">
              <div className="grid gap-5 lg:grid-cols-2">
                <div className="space-y-3 rounded-lg border p-4">
                  <div className="flex items-center justify-between">
                    <Label htmlFor="course_select">Courses</Label>
                    <span className="text-xs text-muted-foreground">
                      {selectedCourses.length} selected
                    </span>
                  </div>
                  <SearchableSelect
                    id="course_select"
                    value={null}
                    onChange={addSelection(setSelectedCourses)}
                    fetchOptions={fetchCourses}
                    placeholder="-- Add a course --"
                    searchPlaceholder="Search by name or code…"
                  />
                  {selectedCourses.length > 0 && (
                    <div className="flex flex-wrap gap-1.5">
                      {selectedCourses.map((course) => (
                        <SelectionChip
                          key={course.id}
                          label={course.label}
                          onRemove={() =>
                            removeSelection(setSelectedCourses, course.id)
                          }
                        />
                      ))}
                    </div>
                  )}
                </div>

                <div className="space-y-3 rounded-lg border p-4">
                  <div className="flex items-center justify-between">
                    <Label htmlFor="curriculum_select">Curricula</Label>
                    <span className="text-xs text-muted-foreground">
                      {selectedCurricula.length} selected
                    </span>
                  </div>
                  <SearchableSelect
                    id="curriculum_select"
                    value={null}
                    onChange={addSelection(setSelectedCurricula)}
                    fetchOptions={fetchCurricula}
                    placeholder="-- Add a curriculum --"
                    searchPlaceholder="Search by name…"
                  />
                  {selectedCurricula.length > 0 && (
                    <div className="flex flex-wrap gap-1.5">
                      {selectedCurricula.map((curriculum) => (
                        <SelectionChip
                          key={curriculum.id}
                          label={curriculum.label}
                          onRemove={() =>
                            removeSelection(setSelectedCurricula, curriculum.id)
                          }
                        />
                      ))}
                    </div>
                  )}
                </div>
              </div>

              <p className="text-sm text-muted-foreground">
                {combos.length} new{' '}
                {combos.length === 1 ? 'combination' : 'combinations'} will be
                created
                {skippedCount > 0 &&
                  ` — ${skippedCount} already ${skippedCount === 1 ? 'has' : 'have'} this unit and will be skipped`}
                .
              </p>

              {errors.targets && (
                <p className="text-sm font-medium text-destructive">
                  {errors.targets}
                </p>
              )}

              <div className="space-y-4 border-t pt-5">
                <div>
                  <h3 className="text-sm font-medium">Teaching Load</h3>
                  <p className="text-sm text-muted-foreground">
                    Optional values that apply to all {combos.length}{' '}
                    {combos.length === 1 ? 'assignment' : 'assignments'} about
                    to be created. Each one can be edited independently
                    afterwards — for example when the same unit needs different
                    hours under another curriculum. The unit's code (
                    {unit?.code}) travels with every assignment.
                  </p>
                </div>
                <div className="grid gap-5 lg:grid-cols-3">
                  <FormField
                    id="time_offered"
                    label="Time Offered"
                    value={details.time_offered}
                    onChange={(event) =>
                      setDetails({
                        ...details,
                        time_offered: event.target.value,
                      })
                    }
                    maxLength={100}
                    placeholder="e.g. Year 1 Term 1 (optional)"
                  />
                  <FormField
                    id="taught_hours"
                    label="Taught Hours"
                    type="number"
                    min={0}
                    max={32767}
                    value={details.taught_hours}
                    onChange={(event) =>
                      setDetails({ ...details, taught_hours: event.target.value })
                    }
                    placeholder="e.g. 60 (optional)"
                  />
                  <FormField
                    id="credit_factor"
                    label="Credit Factor"
                    type="number"
                    min={0}
                    step={0.01}
                    value={details.credit_factor}
                    onChange={(event) =>
                      setDetails({
                        ...details,
                        credit_factor: event.target.value,
                      })
                    }
                    placeholder="e.g. 2.5 (optional)"
                  />
                </div>
                <label
                  htmlFor="core"
                  className="flex w-fit cursor-pointer items-center gap-2 text-sm text-muted-foreground"
                >
                  <Checkbox
                    id="core"
                    checked={details.core}
                    onChange={(event) =>
                      setDetails({ ...details, core: event.target.checked })
                    }
                  />
                  Core unit — required part of the course (uncheck for
                  elective)
                </label>
              </div>
            </div>
          )}

          {/* Step 3 — review and confirm. */}
          {step === 3 && (
            <div className="space-y-4">
              {batchErrors.length > 0 && (
                <div
                  role="alert"
                  className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
                >
                  <p>The assignments could not be created:</p>
                  <ul className="mt-1 list-inside list-disc font-normal">
                    {batchErrors.map((message) => (
                      <li key={message}>{message}</li>
                    ))}
                  </ul>
                </div>
              )}

              <p className="text-sm">
                About to assign{' '}
                <span className="font-medium">{unit?.name}</span> to{' '}
                <span className="font-medium">
                  {combos.length} {combos.length === 1 ? 'combination' : 'combinations'}
                </span>
                {details.time_offered !== '' &&
                  ` · offered in ${details.time_offered}`}
                {details.taught_hours !== '' &&
                  ` · ${details.taught_hours} hours`}
                {details.credit_factor !== '' &&
                  ` · credit factor ${details.credit_factor}`}
                {` · ${details.core ? 'core' : 'elective'} unit`}
                .
              </p>

              <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                      <th className="px-3 py-2 font-medium">Course</th>
                      <th className="px-3 py-2 font-medium">Curriculum</th>
                      <th className="px-3 py-2 font-medium">Core</th>
                      <th className="px-3 py-2 font-medium">Time Offered</th>
                      <th className="px-3 py-2 font-medium">Hours</th>
                      <th className="px-3 py-2 font-medium">Credit</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {combos.map(({ course, curriculum }) => (
                      <tr key={`${course.id}-${curriculum.id}`}>
                        <td className="px-3 py-2">{course.label}</td>
                        <td className="px-3 py-2">{curriculum.label}</td>
                        <td className="px-3 py-2 text-muted-foreground">
                          {details.core ? 'Core' : 'Elective'}
                        </td>
                        <td className="px-3 py-2 text-muted-foreground">
                          {details.time_offered || '—'}
                        </td>
                        <td className="px-3 py-2 text-muted-foreground">
                          {details.taught_hours || '—'}
                        </td>
                        <td className="px-3 py-2 text-muted-foreground">
                          {details.credit_factor || '—'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* Navigation */}
          <div className="flex items-center justify-between gap-3 border-t pt-5">
            <div>
              {step > 1 ? (
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => {
                    setErrors({})
                    setStep(step - 1)
                  }}
                >
                  <ArrowLeft aria-hidden="true" /> Back
                </Button>
              ) : (
                <Link
                  to="/units"
                  className={buttonVariants({ variant: 'outline' })}
                >
                  Cancel
                </Link>
              )}
            </div>

            {step < 3 ? (
              <Button type="button" onClick={goNext}>
                Next <ArrowRight aria-hidden="true" />
              </Button>
            ) : (
              <Button
                type="button"
                onClick={handleSubmit}
                disabled={submitting}
              >
                <Link2 aria-hidden="true" />
                {submitting
                  ? 'Assigning…'
                  : `Assign to ${combos.length} ${combos.length === 1 ? 'Combination' : 'Combinations'}`}
              </Button>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
