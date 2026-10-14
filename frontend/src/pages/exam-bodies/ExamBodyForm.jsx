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
import api, { validationErrors } from '@/lib/api'

export default function ExamBodyForm() {
  const { examBodyId } = useParams()
  const isEdit = Boolean(examBodyId)
  const navigate = useNavigate()

  const [examBody, setExamBody] = useState(null)
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
      .get(`/exam-bodies/${examBodyId}`)
      .then(({ data }) => {
        if (active) {
          setExamBody(data.exam_body)
        }
      })
      .catch(() => {
        if (active) {
          setLoadError(
            'Unable to load the exam body. It may have been deleted.',
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
  }, [isEdit, examBodyId])

  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const form = new FormData(event.currentTarget)
    const payload = {
      name: form.get('name'),
      code: String(form.get('code') ?? '').trim().toUpperCase(),
      website: form.get('website')?.trim() || null,
      email: form.get('email')?.trim() || null,
      phone: form.get('phone')?.trim() || null,
      address: form.get('address')?.trim() || null,
      description: form.get('description')?.trim() || null,
      is_active: form.get('is_active') === '1',
    }

    try {
      if (isEdit) {
        await api.put(`/exam-bodies/${examBodyId}`, payload)
        toast.success('Exam body updated')
      } else {
        await api.post('/exam-bodies', payload)
        toast.success('Exam body created')
      }

      navigate('/exam-bodies')
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to save the exam body.',
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
          to="/exam-bodies"
          className={buttonVariants({ variant: 'outline' })}
        >
          <ArrowLeft aria-hidden="true" />
          Back to exam bodies
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <Link
        to="/exam-bodies"
        className={buttonVariants({ variant: 'ghost' })}
      >
        <ArrowLeft aria-hidden="true" />
        Back to exam bodies
      </Link>

      <Card className="py-8">
        <CardHeader>
          <CardTitle>
            {isEdit ? 'Edit Exam Body' : 'Create Exam Body'}
          </CardTitle>
          <CardDescription>
            {isEdit
              ? 'Update the examination body details below.'
              : 'Register a new examination authority.'}
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-5" noValidate>
            <div className="grid gap-5 lg:grid-cols-3">
              <FormField
                id="code"
                label="Code"
                name="code"
                required
                defaultValue={examBody?.code ?? ''}
                placeholder="e.g. KNEC"
                autoComplete="off"
                maxLength={30}
                error={errors.code}
              />

              <FormField
                id="name"
                label="Name"
                name="name"
                required
                defaultValue={examBody?.name ?? ''}
                placeholder="e.g. Kenya National Examinations Council"
                autoComplete="off"
                error={errors.name}
              />

              <FormField
                id="website"
                label="Website"
                name="website"
                type="url"
                defaultValue={examBody?.website ?? ''}
                placeholder="https://www.example.ac.ke"
                autoComplete="off"
                error={errors.website}
              />

              <FormField
                id="email"
                label="Email"
                name="email"
                type="email"
                defaultValue={examBody?.email ?? ''}
                placeholder="info@example.ac.ke"
                autoComplete="off"
                error={errors.email}
              />

              <FormField
                id="phone"
                label="Phone"
                name="phone"
                defaultValue={examBody?.phone ?? ''}
                placeholder="e.g. +254 20 123 4567"
                autoComplete="off"
                maxLength={30}
                error={errors.phone}
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
                    defaultChecked={examBody?.is_active ?? true}
                  />
                  Exam body is currently active
                </label>
              </div>
            </div>

            <FormField
              id="address"
              label="Address"
              name="address"
              multiline
              rows={3}
              defaultValue={examBody?.address ?? ''}
              placeholder="Physical or postal address"
              error={errors.address}
            />

            <FormField
              id="description"
              label="Description"
              name="description"
              multiline
              rows={3}
              defaultValue={examBody?.description ?? ''}
              placeholder="Brief description of the authority"
              error={errors.description}
            />

            <div className="flex items-center gap-3">
              <Button type="submit" disabled={submitting}>
                {submitting
                  ? 'Saving…'
                  : isEdit
                    ? 'Save Changes'
                    : 'Create Exam Body'}
              </Button>
              <Link
                to="/exam-bodies"
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
