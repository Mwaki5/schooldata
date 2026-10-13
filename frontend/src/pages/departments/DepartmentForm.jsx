import { ArrowLeft } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import PageLoader from '@/components/page-loader'
import { FormField } from '@/components/form-field'
import { Button, buttonVariants } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { SearchableSelect } from '@/components/ui/searchable-select'
import api, { validationErrors } from '@/lib/api'
import { toast } from 'sonner'

export default function DepartmentForm() {
  const { departmentId } = useParams()
  const isEdit = Boolean(departmentId)
  const navigate = useNavigate()

  const [department, setDepartment] = useState(null)
  const [hod, setHod] = useState(null)
  const [loading, setLoading] = useState(isEdit)
  const [loadError, setLoadError] = useState(null)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)

  // Server-side HOD search: only one page of matches is fetched at a time,
  // so this stays fast even with hundreds of thousands of users.
  const searchUsers = useCallback(async (search, signal) => {
    const { data } = await api.get('/users', {
      params: search ? { search } : {},
      signal,
    })

    return data.users
  }, [])

  // Load the department being edited.
  useEffect(() => {
    if (!isEdit) {
      return undefined
    }

    let active = true

    api
      .get(`/departments/${departmentId}`)
      .then(({ data }) => {
        if (active) {
          setDepartment(data.department)
          setHod(
            data.department.hod_id
              ? {
                  id: data.department.hod_id,
                  name: data.department.hod_name ?? '',
                }
              : null,
          )
        }
      })
      .catch(() => {
        if (active) {
          setLoadError(
            'Unable to load the department. It may have been deleted.',
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
  }, [isEdit, departmentId])

  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const form = new FormData(event.currentTarget)
    const payload = {
      name: form.get('name'),
      hod_id: hod?.id ?? null,
      is_active: form.get('is_active') === '1',
    }

    try {
      if (isEdit) {
        await api.put(`/departments/${departmentId}`, payload)
        toast.success('Department updated')
      } else {
        await api.post('/departments', payload)
        toast.success('Department created')
      }

      navigate('/departments')
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to save the department.',
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
        <Link
          to="/departments"
          className={buttonVariants({ variant: 'outline' })}
        >
          <ArrowLeft aria-hidden="true" />
          Back to departments
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <Link
        to="/departments"
        className={buttonVariants({ variant: 'ghost' })}
      >
        <ArrowLeft aria-hidden="true" />
        Back to departments
      </Link>

      <Card className="py-8">
        <CardHeader>
          <CardTitle>
            {isEdit ? 'Edit Department' : 'Create Department'}
          </CardTitle>
          <CardDescription>
            {isEdit
              ? 'Update the department details below.'
              : 'Add a new academic department.'}
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-5" noValidate>
            <FormField
              id="name"
              label="Department Name"
              name="name"
              required
              defaultValue={department?.name ?? ''}
              placeholder="e.g. Sciences"
              autoComplete="off"
              error={errors.name}
            />

            <FormField id="hod_id" label="HOD" error={errors.hod_id}>
              <SearchableSelect
                id="hod_id"
                value={hod?.id ?? null}
                selectedLabel={hod?.name ?? ''}
                onChange={(nextId, option) =>
                  setHod(
                    nextId ? { id: nextId, name: option?.name ?? '' } : null,
                  )
                }
                fetchOptions={searchUsers}
                placeholder="-- Choose a head of department --"
                searchPlaceholder="Type a name or email to search…"
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
                  defaultChecked={department?.is_active ?? true}
                />
                Department is active
              </label>
            </div>

            <div className="flex items-center gap-3">
              <Button type="submit" disabled={submitting}>
                {submitting
                  ? 'Saving…'
                  : isEdit
                    ? 'Save Changes'
                    : 'Create Department'}
              </Button>
              <Link
                to="/departments"
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
