import { ArrowLeft } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
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
import api, { loadErrorMessage, validationErrors } from '@/lib/api'

export default function FeeStructureForm() {
  const { feeStructureId } = useParams()
  const isEdit = Boolean(feeStructureId)
  const navigate = useNavigate()

  const [feeStructure, setFeeStructure] = useState(null)
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
      .get(`/fee-structures/${feeStructureId}`)
      .then(({ data }) => {
        if (active) {
          setFeeStructure(data.fee_structure)
        }
      })
      .catch((error) => {
        if (active) {
          setLoadError(
            loadErrorMessage(
              error,
              'Fee structure',
              'Unable to load the fee structure. It may have been deleted.',
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
  }, [isEdit, feeStructureId])

  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const form = new FormData(event.currentTarget)
    const payload = {
      name: form.get('name')?.trim(),
      is_active: form.get('is_active') === '1',
    }

    try {
      if (isEdit) {
        await api.put(`/fee-structures/${feeStructureId}`, payload)
        toast.success('Fee structure updated')
      } else {
        await api.post('/fee-structures', payload)
        toast.success('Fee structure created')
      }

      navigate('/fee-structures')
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to save the fee structure.',
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
        <Link
          to="/fee-structures"
          className={buttonVariants({ variant: 'outline' })}
        >
          <ArrowLeft aria-hidden="true" />
          Back to fee structures
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <Link
        to="/fee-structures"
        className={buttonVariants({ variant: 'ghost' })}
      >
        <ArrowLeft aria-hidden="true" />
        Back to fee structures
      </Link>

      <Card className="py-8">
        <CardHeader>
          <CardTitle>
            {isEdit ? 'Edit Fee Structure' : 'Create Fee Structure'}
          </CardTitle>
          <CardDescription>
            {isEdit
              ? 'Update the fee structure below.'
              : 'Name a new fee plan the school charges against.'}
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-5" noValidate>
            <FormField
              id="name"
              label="Name"
              name="name"
              required
              defaultValue={feeStructure?.name ?? ''}
              placeholder="e.g. Year 1 Semester 1 Fees"
              autoComplete="off"
              maxLength={150}
              error={errors.name}
            />

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
                  defaultChecked={feeStructure?.is_active ?? true}
                />
                Fee structure is currently offered
              </label>
            </div>

            <div className="flex items-center gap-3">
              <Button type="submit" disabled={submitting}>
                {submitting
                  ? 'Saving…'
                  : isEdit
                    ? 'Save Changes'
                    : 'Create Fee Structure'}
              </Button>
              <Link
                to="/fee-structures"
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
