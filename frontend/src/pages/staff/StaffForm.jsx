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
    const payload = {
      employment_no: String(form.get('employment_no') ?? '')
        .trim()
        .toUpperCase(),
      name: form.get('name'),
      email: form.get('email')?.trim() || null,
      phone: form.get('phone')?.trim() || null,
      position: form.get('position')?.trim() || null,
      department_id: department?.id ?? null,
      is_active: form.get('is_active') === '1',
    }

    try {
      if (isEdit) {
        await api.put(`/staff/${staffId}`, payload)
        toast.success('Staff member updated')
      } else {
        await api.post('/staff', payload)
        toast.success('Staff member created')
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
    <div className="mx-auto max-w-2xl space-y-6">
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
          <form onSubmit={handleSubmit} className="space-y-5" noValidate>
            <div className="grid gap-5 sm:grid-cols-2">
              <FormField
                id="employment_no"
                label="Employee No"
                name="employment_no"
                required
                defaultValue={staff?.employment_no ?? ''}
                placeholder="e.g. EMP-0001"
                autoComplete="off"
                maxLength={30}
                inputClassName="uppercase"
                error={errors.employment_no}
              />

              <FormField
                id="name"
                label="Full Name"
                name="name"
                required
                defaultValue={staff?.name ?? ''}
                placeholder="e.g. Jane Doe"
                autoComplete="off"
                maxLength={150}
                error={errors.name}
              />

              <FormField
                id="email"
                label="Email"
                name="email"
                type="email"
                defaultValue={staff?.email ?? ''}
                placeholder="jane.doe@school.test"
                autoComplete="off"
                error={errors.email}
              />

              <FormField
                id="phone"
                label="Phone"
                name="phone"
                defaultValue={staff?.phone ?? ''}
                placeholder="e.g. +254 700 000 001"
                autoComplete="off"
                maxLength={30}
                error={errors.phone}
              />

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
                      nextId ? { id: nextId, name: option?.name ?? '' } : null,
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
                  defaultChecked={staff?.is_active ?? true}
                />
                Staff member is active
              </label>
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
