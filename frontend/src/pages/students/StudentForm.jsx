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
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import api, { loadErrorMessage, validationErrors } from '@/lib/api'

const GENDERS = [
  { value: 'female', label: 'Female' },
  { value: 'male', label: 'Male' },
  { value: 'other', label: 'Other' },
]

const STATUSES = [
  { value: 'active', label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
]

const ENROLMENT_STATUSES = [
  { value: 'ENROLLED', label: 'Enrolled' },
  { value: 'COMPLETED', label: 'Completed' },
  { value: 'DROPPED', label: 'Dropped' },
]

/** Today in the local timezone as `YYYY-MM-DD` (create prefill). */
function today() {
  const now = new Date()
  const pad = (part) => String(part).padStart(2, '0')

  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
}

function SectionTitle({ children }) {
  return (
    <p className="border-b pb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
      {children}
    </p>
  )
}

/** `2004-05-17T00:00:00.000000Z` or `2004-05-17` → `YYYY-MM-DD`. */
function toDateInputValue(value) {
  return String(value ?? '').slice(0, 10)
}

export default function StudentForm() {
  const { studentId } = useParams()
  const isEdit = Boolean(studentId)
  const navigate = useNavigate()

  const [student, setStudent] = useState(null)
  const [course, setCourse] = useState(null) // { id, name }
  const [curriculum, setCurriculum] = useState(null) // { id, name }
  const [certificationLevel, setCertificationLevel] = useState(null) // { id, name }
  const [academicSession, setAcademicSession] = useState(null) // { id, name }
  const [loading, setLoading] = useState(isEdit)
  const [loadError, setLoadError] = useState(null)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)

  // Load the form state on mount.
  useEffect(() => {
    if (!isEdit) {
      return undefined
    }

    let active = true

    api
      .get(`/students/${studentId}`)
      .then(({ data }) => {
        if (active) {
          const enrolment = data.student.enrolment

          setStudent(data.student)
          setCourse(
            enrolment?.course_id
              ? { id: enrolment.course_id, name: enrolment.course_label ?? '' }
              : null,
          )
          setCurriculum(
            enrolment?.curriculum_id
              ? {
                  id: enrolment.curriculum_id,
                  name: enrolment.curriculum_label ?? '',
                }
              : null,
          )
          setCertificationLevel(
            enrolment?.certification_level_id
              ? {
                  id: enrolment.certification_level_id,
                  name: enrolment.certification_level_label ?? '',
                }
              : null,
          )
          setAcademicSession(
            enrolment?.academic_session_id
              ? {
                  id: enrolment.academic_session_id,
                  name: enrolment.academic_session_label ?? '',
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
              'Student',
              'Unable to load the student. It may have been deleted.',
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
  }, [isEdit, studentId])

  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const form = new FormData(event.currentTarget)
    const value = (name) => String(form.get(name) ?? '').trim()

    const payload = {
      admission_no: value('admission_no'),
      first_name: value('first_name'),
      middle_name: value('middle_name') || null,
      last_name: value('last_name'),
      email: value('email') || null,
      gender: form.get('gender') || 'other',
      status: form.get('status') || 'active',
      phone: value('phone') || null,
      national_id: value('national_id') || null,
      date_of_birth: form.get('date_of_birth') || null,
      county: value('county') || null,
      place_of_residence: value('place_of_residence') || null,
      religion: value('religion') || null,
      address: value('address') || null,
      profile_picture: value('profile_picture') || null,
      alternative_phone: value('alternative_phone') || null,
      is_pwd: form.get('is_pwd') === 'on',
      disability_type: value('disability_type') || null,
      disability_description: value('disability_description') || null,
      sponsored: form.get('sponsored') === 'on',
      sponsored_by: value('sponsored_by') || null,
      index_no: value('index_no') || null,
      exam_year: value('exam_year') || null,
      previous_school: value('previous_school') || null,
      // The backend splits this segment into course_enrolments inside
      // the same transaction as the profile and account writes.
      enrolment: {
        course_id: course?.id ?? null,
        curriculum_id: curriculum?.id ?? null,
        certification_level_id: certificationLevel?.id ?? null,
        academic_session_id: academicSession?.id ?? null,
        enrolment_date: value('enrolment_date') || null,
        status: form.get('enrolment_status') || 'ENROLLED',
        remarks: value('remarks') || null,
      },
    }

    try {
      let response

      if (isEdit) {
        response = (await api.put(`/students/${studentId}`, payload)).data
        toast.success('Student updated')
      } else {
        response = (await api.post('/students', payload)).data
        toast.success('Student created')
      }

      if (response.temporary_password) {
        toast.success('Login credentials', {
          description: `Login id: ${response.student.login_id} · Temporary password: ${response.temporary_password} · Share these securely with the student.`,
          duration: 20000,
        })
      }

      navigate('/students')
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to save the student.',
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
      <div className="space-y-6">
        <div
          role="alert"
          className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
        >
          {loadError}
        </div>
        <Link to="/students" className={buttonVariants({ variant: 'outline' })}>
          <ArrowLeft aria-hidden="true" />
          Back to students
        </Link>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <Link to="/students" className={buttonVariants({ variant: 'ghost' })}>
        <ArrowLeft aria-hidden="true" />
        Back to students
      </Link>

      <Card className="py-8">
        <CardHeader>
          <CardTitle>
            {isEdit ? 'Edit Student' : 'Create Student'}
          </CardTitle>
          <CardDescription>
            {isEdit
              ? 'Update the student profile below.'
              : 'Register a new student. The admission number doubles as the login id, and a temporary password is issued once on creation.'}
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-8" noValidate>
            <div className="space-y-5">
              <SectionTitle>Account</SectionTitle>
              <div className="grid gap-5 grid-cols-1 sm:grid-cols-1 lg:grid-cols-3">
                <FormField
                  id="admission_no"
                  label="Admission No"
                  name="admission_no"
                  required
                  defaultValue={student?.login_id ?? ''}
                  placeholder="e.g. ADM-0001"
                  autoComplete="off"
                  maxLength={100}
                  inputClassName="uppercase"
                  error={errors.admission_no}
                />

                <FormField
                  id="email"
                  label="Email"
                  name="email"
                  type="email"
                  defaultValue={student?.email ?? ''}
                  placeholder="e.g. amina@school.test"
                  autoComplete="off"
                  maxLength={255}
                  error={errors.email}
                />
              </div>
            </div>

            <div className="space-y-5">
              <SectionTitle>Identity</SectionTitle>
              <div className="grid gap-5 grid-cols-1 sm:grid-cols-1 lg:grid-cols-3">
                <FormField
                  id="first_name"
                  label="First Name"
                  name="first_name"
                  required
                  defaultValue={student?.first_name ?? ''}
                  placeholder="e.g. Amina"
                  autoComplete="off"
                  maxLength={100}
                  error={errors.first_name}
                />

                <FormField
                  id="middle_name"
                  label="Middle Name"
                  name="middle_name"
                  defaultValue={student?.middle_name ?? ''}
                  placeholder="e.g. Grace"
                  autoComplete="off"
                  maxLength={100}
                  error={errors.middle_name}
                />

                <FormField
                  id="last_name"
                  label="Last Name"
                  name="last_name"
                  required
                  defaultValue={student?.last_name ?? ''}
                  placeholder="e.g. Wanjiku"
                  autoComplete="off"
                  maxLength={100}
                  error={errors.last_name}
                />

                <FormField
                  id="gender"
                  label="Gender"
                  error={errors.gender}
                >
                  <Select
                    id="gender"
                    name="gender"
                    defaultValue={student?.gender ?? 'other'}
                  >
                    {GENDERS.map((gender) => (
                      <option key={gender.value} value={gender.value}>
                        {gender.label}
                      </option>
                    ))}
                  </Select>
                </FormField>

                <FormField
                  id="date_of_birth"
                  label="Date of Birth"
                  name="date_of_birth"
                  type="date"
                  defaultValue={toDateInputValue(student?.date_of_birth)}
                  error={errors.date_of_birth}
                />

                <FormField
                  id="status"
                  label="Status"
                  error={errors.status}
                >
                  <Select
                    id="status"
                    name="status"
                    defaultValue={student?.status ?? 'active'}
                  >
                    {STATUSES.map((status) => (
                      <option key={status.value} value={status.value}>
                        {status.label}
                      </option>
                    ))}
                  </Select>
                </FormField>
              </div>
            </div>

            <div className="space-y-5">
              <SectionTitle>Contact</SectionTitle>
              <div className="grid gap-5 grid-cols-1 sm:grid-cols-1 lg:grid-cols-3">
                <FormField
                  id="phone"
                  label="Phone"
                  name="phone"
                  defaultValue={student?.phone ?? ''}
                  placeholder="e.g. +254 700 000 001"
                  autoComplete="off"
                  maxLength={50}
                  error={errors.phone}
                />

                <FormField
                  id="alternative_phone"
                  label="Alternative Phone"
                  name="alternative_phone"
                  defaultValue={student?.alternative_phone ?? ''}
                  placeholder="e.g. +254 711 000 111"
                  autoComplete="off"
                  maxLength={50}
                  error={errors.alternative_phone}
                />

                <FormField
                  id="address"
                  label="Address"
                  name="address"
                  multiline
                  defaultValue={student?.address ?? ''}
                  placeholder="P.O. Box 123, Nairobi"
                  maxLength={5000}
                  error={errors.address}
                />

                <FormField
                  id="county"
                  label="County"
                  name="county"
                  defaultValue={student?.county ?? ''}
                  placeholder="e.g. Nairobi"
                  autoComplete="off"
                  maxLength={100}
                  error={errors.county}
                />

                <FormField
                  id="place_of_residence"
                  label="Place of Residence"
                  name="place_of_residence"
                  defaultValue={student?.place_of_residence ?? ''}
                  placeholder="e.g. Nairobi"
                  autoComplete="address-level2"
                  maxLength={100}
                  error={errors.place_of_residence}
                />

                <FormField
                  id="religion"
                  label="Religion"
                  name="religion"
                  defaultValue={student?.religion ?? ''}
                  placeholder="e.g. Christian"
                  autoComplete="off"
                  maxLength={100}
                  error={errors.religion}
                />

                <FormField
                  id="national_id"
                  label="National ID"
                  name="national_id"
                  defaultValue={student?.national_id ?? ''}
                  placeholder="e.g. 12345678"
                  autoComplete="off"
                  maxLength={50}
                  error={errors.national_id}
                />
              </div>
            </div>

            <div className="space-y-5">
              <SectionTitle>Sponsorship &amp; Previous School</SectionTitle>

              <div className="space-y-2">
                <div className="flex items-center gap-2">
                  <Checkbox
                    id="sponsored"
                    name="sponsored"
                    defaultChecked={student?.sponsored ?? false}
                  />
                  <Label htmlFor="sponsored" className="cursor-pointer">
                    Sponsored
                  </Label>
                </div>
                {errors.sponsored && (
                  <p
                    role="alert"
                    className="text-sm font-medium text-destructive"
                  >
                    {errors.sponsored}
                  </p>
                )}
              </div>

              <div className="grid gap-5 grid-cols-1 sm:grid-cols-1 lg:grid-cols-3">
                <FormField
                  id="sponsored_by"
                  label="Sponsored By"
                  name="sponsored_by"
                  defaultValue={student?.sponsored_by ?? ''}
                  placeholder="e.g. County Bursary Fund"
                  autoComplete="off"
                  maxLength={150}
                  error={errors.sponsored_by}
                />

                <FormField
                  id="index_no"
                  label="KCPE/KCSE Index No"
                  name="index_no"
                  defaultValue={student?.index_no ?? ''}
                  placeholder="e.g. 6300123456"
                  autoComplete="off"
                  maxLength={50}
                  error={errors.index_no}
                />

                <FormField
                  id="exam_year"
                  label="KCPE/KCSE Year"
                  name="exam_year"
                  defaultValue={student?.exam_year ?? ''}
                  placeholder="e.g. 2025"
                  autoComplete="off"
                  maxLength={4}
                  error={errors.exam_year}
                />

                <FormField
                  id="previous_school"
                  label="Name of Previous School"
                  name="previous_school"
                  defaultValue={student?.previous_school ?? ''}
                  placeholder="e.g. Central Primary School"
                  autoComplete="off"
                  maxLength={150}
                  error={errors.previous_school}
                />
              </div>
            </div>

            <div className="space-y-5">
              <SectionTitle>Enrolment</SectionTitle>

              <div className="grid gap-5 grid-cols-1 sm:grid-cols-1 lg:grid-cols-3">
                <FormField
                  id="course_id"
                  label="Course"
                  required
                  error={errors['enrolment.course_id']}
                >
                  <SearchableSelect
                    id="course_select"
                    value={course?.id ?? null}
                    selectedLabel={course?.name ?? ''}
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
                            name: `${c.code} — ${c.name}`,
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
                  error={errors['enrolment.curriculum_id']}
                >
                  <SearchableSelect
                    id="curriculum_select"
                    value={curriculum?.id ?? null}
                    selectedLabel={curriculum?.name ?? ''}
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
                  id="certification_level_id"
                  label="Certification Level"
                  required
                  error={errors['enrolment.certification_level_id']}
                >
                  <SearchableSelect
                    id="certification_level_select"
                    value={certificationLevel?.id ?? null}
                    selectedLabel={certificationLevel?.name ?? ''}
                    onChange={(nextId, option) =>
                      setCertificationLevel(
                        nextId ? { id: nextId, name: option?.name ?? '' } : null,
                      )
                    }
                    fetchOptions={(query, signal) =>
                      api
                        .get('/certification-levels', {
                          params: {
                            per_page: 100,
                            ...(query ? { search: query } : {}),
                          },
                          signal,
                        })
                        .then(({ data }) =>
                          data.certification_levels.map((l) => ({
                            id: l.id,
                            name: l.name,
                          })),
                        )
                    }
                    placeholder="-- Choose a certification level --"
                    searchPlaceholder="Type to find a level…"
                  />
                </FormField>

                <FormField
                  id="academic_session_id"
                  label="Academic Session"
                  error={errors['enrolment.academic_session_id']}
                >
                  <SearchableSelect
                    id="academic_session_select"
                    value={academicSession?.id ?? null}
                    selectedLabel={academicSession?.name ?? ''}
                    onChange={(nextId, option) =>
                      setAcademicSession(
                        nextId ? { id: nextId, name: option?.name ?? '' } : null,
                      )
                    }
                    fetchOptions={(query, signal) =>
                      api
                        .get('/academic-sessions/options', {
                          params: {
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
                    placeholder="-- No academic session --"
                    searchPlaceholder="Type to find a session…"
                  />
                </FormField>

                <FormField
                  id="enrolment_date"
                  label="Enrolment Date"
                  name="enrolment_date"
                  type="date"
                  required
                  defaultValue={
                    isEdit
                      ? toDateInputValue(student?.enrolment?.enrolment_date)
                      : today()
                  }
                  error={errors['enrolment.enrolment_date']}
                />

                <FormField
                  id="enrolment_status"
                  label="Enrolment Status"
                  error={errors['enrolment.status']}
                >
                  <Select
                    id="enrolment_status"
                    name="enrolment_status"
                    defaultValue={student?.enrolment?.status ?? 'ENROLLED'}
                  >
                    {ENROLMENT_STATUSES.map((status) => (
                      <option key={status.value} value={status.value}>
                        {status.label}
                      </option>
                    ))}
                  </Select>
                </FormField>

                <FormField
                  id="remarks"
                  label="Remarks"
                  name="remarks"
                  multiline
                  defaultValue={student?.enrolment?.remarks ?? ''}
                  placeholder="Any notes about this enrolment…"
                  maxLength={5000}
                  error={errors['enrolment.remarks']}
                />
              </div>
            </div>

            <div className="space-y-5">
              <SectionTitle>Accessibility</SectionTitle>
              <div className="grid gap-5 grid-cols-1 sm:grid-cols-1 lg:grid-cols-3">
                <FormField
                  id="disability_type"
                  label="Disability Type"
                  name="disability_type"
                  defaultValue={student?.disability_type ?? ''}
                  placeholder="e.g. Visual impairment"
                  autoComplete="off"
                  maxLength={100}
                  error={errors.disability_type}
                />

                <FormField
                  id="disability_description"
                  label="Disability Description"
                  name="disability_description"
                  multiline
                  defaultValue={student?.disability_description ?? ''}
                  placeholder="Any additional details…"
                  maxLength={5000}
                  error={errors.disability_description}
                />
              </div>

              <div className="space-y-2">
                <div className="flex items-center gap-2">
                  <Checkbox
                    id="is_pwd"
                    name="is_pwd"
                    defaultChecked={student?.is_pwd ?? false}
                  />
                  <Label htmlFor="is_pwd" className="cursor-pointer">
                    Person with Disability (PWD)
                  </Label>
                </div>
                {errors.is_pwd && (
                  <p
                    role="alert"
                    className="text-sm font-medium text-destructive"
                  >
                    {errors.is_pwd}
                  </p>
                )}
              </div>
            </div>

            <div className="space-y-5">
              <SectionTitle>Picture</SectionTitle>
              <div className="grid gap-5 grid-cols-1 sm:grid-cols-1 lg:grid-cols-3">
                <FormField
                  id="profile_picture"
                  label="Profile Picture URL"
                  name="profile_picture"
                  type="url"
                  defaultValue={student?.profile_picture ?? ''}
                  placeholder="https://…/photo.jpg"
                  autoComplete="off"
                  error={errors.profile_picture}
                />
              </div>
            </div>

            <div className="flex items-center gap-3">
              <Button type="submit" disabled={submitting}>
                {submitting
                  ? 'Saving…'
                  : isEdit
                    ? 'Save Changes'
                    : 'Create Student'}
              </Button>
              <Link
                to="/students"
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
