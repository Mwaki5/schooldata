import { ArrowLeft } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import PageLoader from '@/components/page-loader'
import { FormField } from '@/components/form-field'
import { Button, buttonVariants } from '@/components/ui/button'
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

/**
 * Assign a fee structure to one course + curriculum pair, choosing how
 * the charge repeats: once per academic session, or once per year. A
 * student's enrolment carries the pair, so exactly one assignment may
 * exist per pair. All four fields stay editable so a mis-pick can be
 * corrected in place.
 */
export default function CourseFeeAssignmentForm() {
  const { courseFeeAssignmentId } = useParams()
  const isEdit = Boolean(courseFeeAssignmentId)
  const navigate = useNavigate()

  const [course, setCourse] = useState(null) // { id, name }
  const [curriculum, setCurriculum] = useState(null) // { id, name }
  const [feeStructure, setFeeStructure] = useState(null) // { id, name }
  const [span, setSpan] = useState('session')
  const [loading, setLoading] = useState(isEdit)
  const [loadError, setLoadError] = useState(null)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)

  // On edit the assignment arrives with its course, curriculum and fee
  // structure nested, so every picker can preselect immediately.
  useEffect(() => {
    if (!isEdit) {
      return undefined
    }

    let active = true

    api
      .get(`/course-fee-assignments/${courseFeeAssignmentId}`)
      .then(({ data }) => {
        if (active) {
          const loaded = data.course_fee_assignment

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
          setFeeStructure(
            loaded.fee_structure
              ? {
                  id: loaded.fee_structure.id,
                  name: loaded.fee_structure.name,
                }
              : null,
          )
          setSpan(loaded.span ?? 'session')
        }
      })
      .catch((error) => {
        if (active) {
          setLoadError(
            loadErrorMessage(
              error,
              'Fee assignment',
              'Unable to load the fee assignment. It may have been deleted.',
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
  }, [isEdit, courseFeeAssignmentId])

  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const payload = {
      course_id: course?.id ?? null,
      curriculum_id: curriculum?.id ?? null,
      fee_structure_id: feeStructure?.id ?? null,
      span,
    }

    try {
      if (isEdit) {
        await api.put(`/course-fee-assignments/${courseFeeAssignmentId}`, payload)
        toast.success(`Fee assignment for "${course?.name ?? 'course'}" updated`)
      } else {
        await api.post('/course-fee-assignments', payload)
        toast.success(`Fee assigned to "${course?.name ?? 'course'}"`)
      }

      navigate('/course-fee-assignments')
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to save the fee assignment.',
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
          to="/course-fee-assignments"
          className={buttonVariants({ variant: 'outline' })}
        >
          <ArrowLeft aria-hidden="true" /> Back to fee assignments
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <Link
        to="/course-fee-assignments"
        className={buttonVariants({ variant: 'ghost' })}
      >
        <ArrowLeft aria-hidden="true" /> Back to fee assignments
      </Link>

      <Card className="py-8">
        <CardHeader>
          <CardTitle>
            {isEdit ? 'Edit Fee Assignment' : 'Assign Fee to Course'}
          </CardTitle>
          <CardDescription>
            Choose the course + curriculum pair and the fee structure it
            charges. The pair may charge under only one assignment, and the
            span sets whether the structure&rsquo;s total repeats per session or
            per year.
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-5" noValidate>
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
                    setCourse(
                      nextId ? { id: nextId, name: option?.name ?? '' } : null,
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
                  selectedLabel={curriculum?.name ?? '-- Choose a curriculum --'}
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

              <FormField
                id="fee_structure_id"
                label="Fee Structure"
                required
                error={errors.fee_structure_id}
              >
                <SearchableSelect
                  id="fee_structure_select"
                  value={feeStructure?.id ?? null}
                  selectedLabel={feeStructure?.name ?? '-- Choose a fee structure --'}
                  onChange={(nextId, option) =>
                    setFeeStructure(
                      nextId
                        ? { id: nextId, name: option?.name ?? '' }
                        : null,
                    )
                  }
                  fetchOptions={(query, signal) =>
                    api
                      .get('/fee-structures', {
                        params: {
                          per_page: 100,
                          ...(query ? { search: query } : {}),
                        },
                        signal,
                      })
                      .then(({ data }) =>
                        data.fee_structures.map((f) => ({
                          id: f.id,
                          name: f.name,
                        })),
                      )
                  }
                  placeholder="-- Choose a fee structure --"
                  searchPlaceholder="Type to find a fee structure…"
                />
              </FormField>

              <FormField
                id="span"
                label="Charged"
                required
                error={errors.span}
              >
                <Select
                  id="span"
                  name="span"
                  value={span}
                  onChange={(event) => setSpan(event.target.value)}
                >
                  <option value="session">Per Session</option>
                  <option value="year">Per Year</option>
                </Select>
              </FormField>
            </div>

            <div className="flex items-center gap-3">
              <Button type="submit" disabled={submitting}>
                {submitting
                  ? 'Saving…'
                  : isEdit
                    ? 'Save Changes'
                    : 'Assign Fee'}
              </Button>
              <Link
                to="/course-fee-assignments"
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
