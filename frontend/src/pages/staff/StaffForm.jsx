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
import api, { validationErrors } from '@/lib/api'

const EMPLOYMENT_TYPES = [
  { value: 'full_time', label: 'Full-time' },
  { value: 'part_time', label: 'Part-time' },
  { value: 'contract', label: 'Contract' },
  { value: 'intern', label: 'Intern' },
  { value: 'volunteer', label: 'Volunteer' },
]

const EMPLOYMENT_STATUSES = [
  { value: 'active', label: 'Active' },
  { value: 'probation', label: 'Probation' },
  { value: 'suspended', label: 'Suspended' },
  { value: 'inactive', label: 'Inactive' },
  { value: 'terminated', label: 'Terminated' },
  { value: 'retired', label: 'Retired' },
]

const STAFF_ROLES = [
  { value: 'trainer', label: 'Trainer' },
  { value: 'admin', label: 'Admin' },
  { value: 'finance', label: 'Finance' },
]

const GENDERS = [
  { value: 'female', label: 'Female' },
  { value: 'male', label: 'Male' },
  { value: 'other', label: 'Other' },
]

function SectionTitle({ children }) {
  return (
    <p className="border-b pb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
      {children}
    </p>
  )
}

export default function StaffForm() {
  const { staffId } = useParams()
  const isEdit = Boolean(staffId)
  const navigate = useNavigate()

  const [staff, setStaff] = useState(null)
  const [department, setDepartment] = useState(null)
  const [loading, setLoading] = useState(isEdit)
  const [loadError, setLoadError] = useState(null)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    if (!isEdit) {
      return undefined
    }

    let active = true

    api
      .get(`/staff/${staffId}`)
      .then(({ data }) => {
        if (active) {
          const loaded = data.staff

          setStaff(loaded)
          setDepartment(
            loaded.department_id
              ? {
                  id: loaded.department_id,
                  name: loaded.department_name ?? '',
                }
              : null,
          )
        }
      })
      .catch(() => {
        if (active) {
          setLoadError(
            'Unable to load the staff member. It may have been deleted.',
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
  }, [isEdit, staffId])

  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const form = new FormData(event.currentTarget)
    const salary = form.get('basic_salary')?.trim()

    const payload = {
      employment_no: String(form.get('employment_no') ?? '')
        .trim()
        .toUpperCase(),
      first_name: String(form.get('first_name') ?? '').trim(),
      middle_name: String(form.get('middle_name') ?? '').trim() || null,
      last_name: String(form.get('last_name') ?? '').trim(),
      role: form.get('role'),
      gender: form.get('gender') || 'other',
      date_of_birth: form.get('date_of_birth') || null,
      nationality: form.get('nationality')?.trim() || null,
      county: form.get('county')?.trim() || null,
      place_of_birth: form.get('place_of_birth')?.trim() || null,
      religion: form.get('religion')?.trim() || null,
      address: form.get('address')?.trim() || null,
      city: form.get('city')?.trim() || null,
      postal_code: form.get('postal_code')?.trim() || null,
      profile_picture: form.get('profile_picture')?.trim() || null,
      alternative_phone: form.get('alternative_phone')?.trim() || null,
      is_pwd: form.get('is_pwd') === 'on',
      disability_type: form.get('disability_type')?.trim() || null,
      disability_description:
        form.get('disability_description')?.trim() || null,
      work_email: form.get('work_email')?.trim() || null,
      work_phone: form.get('work_phone')?.trim() || null,
      national_id: form.get('national_id')?.trim() || null,
      kra_pin:
        String(form.get('kra_pin') ?? '')
          .trim()
          .toUpperCase() || null,
      nhif_number: form.get('nhif_number')?.trim() || null,
      nssf_number: form.get('nssf_number')?.trim() || null,
      position: form.get('position')?.trim() || null,
      department_id: department?.id ?? null,
      employment_type: form.get('employment_type') || null,
      employment_status: form.get('employment_status') || 'active',
      date_joined: form.get('date_joined') || null,
      confirmation_date: form.get('confirmation_date') || null,
      contract_end_date: form.get('contract_end_date') || null,
      termination_date: form.get('termination_date') || null,
      termination_reason: form.get('termination_reason')?.trim() || null,
      basic_salary: salary ? Number(salary) : null,
      highest_qualification:
        form.get('highest_qualification')?.trim() || null,
      specialization: form.get('specialization')?.trim() || null,
    }

    try {
      let response

      if (isEdit) {
        response = (await api.put(`/staff/${staffId}`, payload)).data
        toast.success('Staff member updated')
      } else {
        response = (await api.post('/staff', payload)).data
        toast.success('Staff member created')
      }

      if (response.temporary_password) {
        toast.success('Login credentials', {
          description: `Login id: ${response.staff.employment_no} · Temporary password: ${response.temporary_password}. Share these securely with the staff member.`,
          duration: 20000,
        })
      }

      navigate('/staff')
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to save the staff member.',
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
      <div className="mx-auto max-w-2xl space-y-6">
        <div
          role="alert"
          className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
        >
          {loadError}
        </div>
        <Link to="/staff" className={buttonVariants({ variant: 'outline' })}>
          <ArrowLeft aria-hidden="true" />
          Back to staff
        </Link>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <Link to="/staff" className={buttonVariants({ variant: 'ghost' })}>
        <ArrowLeft aria-hidden="true" />
        Back to staff
      </Link>

      <Card className="py-8">
        <CardHeader>
          <CardTitle>
            {isEdit ? 'Edit Staff Member' : 'Create Staff Member'}
          </CardTitle>
          <CardDescription>
            {isEdit
              ? 'Update the staff profile below.'
              : 'Add a new staff profile. The employment number doubles as the login id once an account is provisioned.'}
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-8" noValidate>
            <div className="space-y-5">
              <SectionTitle>Identity</SectionTitle>
              <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <FormField
                  id="employment_no"
                  label="Employee No"
                  name="employment_no"
                  required
                  defaultValue={staff?.employment_no ?? ''}
                  placeholder="e.g. EMP-0001"
                  autoComplete="off"
                  maxLength={50}
                  inputClassName="uppercase"
                  error={errors.employment_no}
                />

                <FormField
                  id="first_name"
                  label="First Name"
                  name="first_name"
                  required
                  defaultValue={staff?.first_name ?? ''}
                  placeholder="e.g. Jane"
                  autoComplete="off"
                  maxLength={100}
                  error={errors.first_name}
                />

                <FormField
                  id="middle_name"
                  label="Middle Name"
                  name="middle_name"
                  defaultValue={staff?.middle_name ?? ''}
                  placeholder="e.g. Ann"
                  autoComplete="off"
                  maxLength={100}
                  error={errors.middle_name}
                />

                <FormField
                  id="last_name"
                  label="Last Name"
                  name="last_name"
                  required
                  defaultValue={staff?.last_name ?? ''}
                  placeholder="e.g. Doe"
                  autoComplete="off"
                  maxLength={100}
                  error={errors.last_name}
                />

                <FormField
                  id="national_id"
                  label="National ID"
                  name="national_id"
                  defaultValue={staff?.national_id ?? ''}
                  placeholder="e.g. 12345678"
                  autoComplete="off"
                  maxLength={50}
                  error={errors.national_id}
                />

                <FormField
                  id="kra_pin"
                  label="KRA PIN"
                  name="kra_pin"
                  defaultValue={staff?.kra_pin ?? ''}
                  placeholder="e.g. A001234567X"
                  autoComplete="off"
                  maxLength={50}
                  inputClassName="uppercase"
                  error={errors.kra_pin}
                />

                <FormField
                  id="nhif_number"
                  label="NHIF Number"
                  name="nhif_number"
                  defaultValue={staff?.nhif_number ?? ''}
                  placeholder="e.g. NHIF-9911"
                  autoComplete="off"
                  maxLength={50}
                  error={errors.nhif_number}
                />

                <FormField
                  id="nssf_number"
                  label="NSSF Number"
                  name="nssf_number"
                  defaultValue={staff?.nssf_number ?? ''}
                  placeholder="e.g. NSSF-8822"
                  autoComplete="off"
                  maxLength={50}
                  error={errors.nssf_number}
                />
              </div>
            </div>

            <div className="space-y-5">
              <SectionTitle>Personal Details</SectionTitle>
              <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <FormField
                  id="gender"
                  label="Gender"
                  error={errors.gender}
                >
                  <Select
                    id="gender"
                    name="gender"
                    defaultValue={staff?.gender ?? 'other'}
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
                  defaultValue={staff?.date_of_birth ?? ''}
                  error={errors.date_of_birth}
                />

                <FormField
                  id="nationality"
                  label="Nationality"
                  name="nationality"
                  defaultValue={staff?.nationality ?? ''}
                  placeholder="e.g. Kenyan"
                  autoComplete="off"
                  maxLength={100}
                  error={errors.nationality}
                />

                <FormField
                  id="place_of_birth"
                  label="Place of Birth"
                  name="place_of_birth"
                  defaultValue={staff?.place_of_birth ?? ''}
                  placeholder="e.g. Nakuru"
                  autoComplete="off"
                  maxLength={100}
                  error={errors.place_of_birth}
                />

                <FormField
                  id="county"
                  label="County"
                  name="county"
                  defaultValue={staff?.county ?? ''}
                  placeholder="e.g. Nairobi"
                  autoComplete="off"
                  maxLength={100}
                  error={errors.county}
                />

                <FormField
                  id="religion"
                  label="Religion"
                  name="religion"
                  defaultValue={staff?.religion ?? ''}
                  placeholder="e.g. Christian"
                  autoComplete="off"
                  maxLength={100}
                  error={errors.religion}
                />

                <FormField
                  id="address"
                  label="Address"
                  name="address"
                  multiline
                  defaultValue={staff?.address ?? ''}
                  placeholder="P.O. Box 123, Nairobi"
                  maxLength={5000}
                  error={errors.address}
                />

                <FormField
                  id="city"
                  label="City"
                  name="city"
                  defaultValue={staff?.city ?? ''}
                  placeholder="e.g. Nairobi"
                  autoComplete="off"
                  maxLength={100}
                  error={errors.city}
                />

                <FormField
                  id="postal_code"
                  label="Postal Code"
                  name="postal_code"
                  defaultValue={staff?.postal_code ?? ''}
                  placeholder="e.g. 00100"
                  autoComplete="off"
                  maxLength={20}
                  error={errors.postal_code}
                />

                <FormField
                  id="alternative_phone"
                  label="Alternative Phone"
                  name="alternative_phone"
                  defaultValue={staff?.alternative_phone ?? ''}
                  placeholder="e.g. +254 711 000 111"
                  autoComplete="off"
                  maxLength={50}
                  error={errors.alternative_phone}
                />

                <FormField
                  id="profile_picture"
                  label="Profile Picture URL"
                  name="profile_picture"
                  type="url"
                  defaultValue={staff?.profile_picture ?? ''}
                  placeholder="https://…/photo.jpg"
                  autoComplete="off"
                  error={errors.profile_picture}
                />

                <FormField
                  id="disability_type"
                  label="Disability Type"
                  name="disability_type"
                  defaultValue={staff?.disability_type ?? ''}
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
                  defaultValue={staff?.disability_description ?? ''}
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
                    defaultChecked={staff?.is_pwd ?? false}
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
              <SectionTitle>Contact</SectionTitle>
              <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <FormField
                  id="work_email"
                  label="Work Email"
                  name="work_email"
                  type="email"
                  defaultValue={staff?.work_email ?? ''}
                  placeholder="jane.doe@school.test"
                  autoComplete="off"
                  error={errors.work_email}
                />

                <FormField
                  id="work_phone"
                  label="Work Phone"
                  name="work_phone"
                  defaultValue={staff?.work_phone ?? ''}
                  placeholder="e.g. +254 700 000 001"
                  autoComplete="off"
                  maxLength={50}
                  error={errors.work_phone}
                />
              </div>
            </div>

            <div className="space-y-5">
              <SectionTitle>Employment</SectionTitle>
              <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <FormField
                  id="role"
                  label="Role"
                  error={errors.role}
                >
                  <Select
                    id="role"
                    name="role"
                    defaultValue={staff?.role ?? 'trainer'}
                  >
                    {STAFF_ROLES.map((role) => (
                      <option key={role.value} value={role.value}>
                        {role.label}
                      </option>
                    ))}
                  </Select>
                </FormField>

                <FormField
                  id="position"
                  label="Position"
                  name="position"
                  defaultValue={staff?.position ?? ''}
                  placeholder="e.g. Senior Lecturer"
                  autoComplete="off"
                  maxLength={100}
                  error={errors.position}
                />

                <FormField
                  id="department_id"
                  label="Department"
                  error={errors.department_id}
                >
                  <SearchableSelect
                    id="department_id"
                    value={department?.id ?? null}
                    selectedLabel={department?.name ?? ''}
                    onChange={(nextId, option) =>
                      setDepartment(
                        nextId
                          ? { id: nextId, name: option?.name ?? '' }
                          : null,
                      )
                    }
                    fetchOptions={(query, signal) =>
                      api
                        .get('/departments', {
                          params: query ? { search: query } : {},
                          signal,
                        })
                        .then(({ data }) =>
                          data.departments.map((d) => ({
                            id: d.id,
                            name: d.name,
                          })),
                        )
                    }
                    placeholder="-- Choose a department --"
                    searchPlaceholder="Type a department name to search…"
                  />
                </FormField>

                <FormField
                  id="employment_type"
                  label="Employment Type"
                  error={errors.employment_type}
                >
                  <Select
                    id="employment_type"
                    name="employment_type"
                    defaultValue={staff?.employment_type ?? ''}
                  >
                    <option value="">-- Choose a type --</option>
                    {EMPLOYMENT_TYPES.map((type) => (
                      <option key={type.value} value={type.value}>
                        {type.label}
                      </option>
                    ))}
                  </Select>
                </FormField>

                <FormField
                  id="employment_status"
                  label="Employment Status"
                  error={errors.employment_status}
                >
                  <Select
                    id="employment_status"
                    name="employment_status"
                    defaultValue={staff?.employment_status ?? 'active'}
                  >
                    {EMPLOYMENT_STATUSES.map((status) => (
                      <option key={status.value} value={status.value}>
                        {status.label}
                      </option>
                    ))}
                  </Select>
                </FormField>

                <FormField
                  id="date_joined"
                  label="Date Joined"
                  name="date_joined"
                  type="date"
                  defaultValue={staff?.date_joined ?? ''}
                  error={errors.date_joined}
                />

                <FormField
                  id="confirmation_date"
                  label="Confirmation Date"
                  name="confirmation_date"
                  type="date"
                  defaultValue={staff?.confirmation_date ?? ''}
                  error={errors.confirmation_date}
                />

                <FormField
                  id="contract_end_date"
                  label="Contract End Date"
                  name="contract_end_date"
                  type="date"
                  defaultValue={staff?.contract_end_date ?? ''}
                  error={errors.contract_end_date}
                />
              </div>
            </div>

            <div className="space-y-5">
              <SectionTitle>Compensation & Qualifications</SectionTitle>
              <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <FormField
                  id="basic_salary"
                  label="Basic Salary"
                  name="basic_salary"
                  type="number"
                  min="0"
                  step="0.01"
                  defaultValue={staff?.basic_salary ?? ''}
                  placeholder="e.g. 85000"
                  autoComplete="off"
                  error={errors.basic_salary}
                />

                <FormField
                  id="highest_qualification"
                  label="Highest Qualification"
                  name="highest_qualification"
                  defaultValue={staff?.highest_qualification ?? ''}
                  placeholder="e.g. Master's Degree"
                  autoComplete="off"
                  maxLength={100}
                  error={errors.highest_qualification}
                />

                <FormField
                  id="specialization"
                  label="Specialization"
                  name="specialization"
                  defaultValue={staff?.specialization ?? ''}
                  placeholder="e.g. Computer Networks"
                  autoComplete="off"
                  maxLength={255}
                  error={errors.specialization}
                />
              </div>
            </div>

            <div className="space-y-5">
              <SectionTitle>Termination</SectionTitle>
              <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <FormField
                  id="termination_date"
                  label="Termination Date"
                  name="termination_date"
                  type="date"
                  defaultValue={staff?.termination_date ?? ''}
                  error={errors.termination_date}
                />

                <FormField
                  id="termination_reason"
                  label="Termination Reason"
                  multiline
                  defaultValue={staff?.termination_reason ?? ''}
                  placeholder="Reason for leaving, if applicable…"
                  error={errors.termination_reason}
                />
              </div>
            </div>

            <div className="flex items-center gap-3">
              <Button type="submit" disabled={submitting}>
                {submitting
                  ? 'Saving…'
                  : isEdit
                    ? 'Save Changes'
                    : 'Create Staff Member'}
              </Button>
              <Link
                to="/staff"
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
