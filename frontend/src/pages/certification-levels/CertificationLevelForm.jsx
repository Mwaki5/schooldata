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
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import api, { loadErrorMessage, validationErrors } from '@/lib/api'

export default function CertificationLevelForm() {
  const { levelId } = useParams()
  const isEdit = Boolean(levelId)
  const navigate = useNavigate()

  const [level, setLevel] = useState(null)
  const [examBody, setExamBody] = useState(null) // { id, name }
  const [loading, setLoading] = useState(isEdit)
  const [loadError, setLoadError] = useState(null)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const [formKey, setFormKey] = useState(0) // remount to clear level fields after create

  // Load the form state on mount.
  useEffect(() => {
    if (!isEdit) {
      return undefined
    }

    let active = true

    api
      .get(`/certification-levels/${levelId}`)
      .then(({ data }) => {
        if (active) {
          const ld = data.certification_level
          setLevel(ld)
          setExamBody(ld.exam_body_id
            ? { id: ld.exam_body_id, name: ld.exam_body_name ?? '' }
            : null)
        }
      })
      .catch((error) => {
        if (active) {
          setLoadError(
            loadErrorMessage(
              error,
              'Certification level',
              'Unable to load the certification level. It may have been deleted.',
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
  }, [isEdit, levelId])

  // On create: keep the exam body selection; after submit only clear the
  // level‑specific fields. The exam body stays selected so the user can add
  // several levels in a row. The form remounts (formKey increment) to reset
  // uncontrolled inputs while keeping the SearchableSelect value.
  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const formData = new FormData(event.currentTarget)
    const payload = {
      exam_body_id: examBody?.id ?? null,
      name: formData.get('name'),
      code: String(formData.get('code') ?? '').trim().toUpperCase(),
      description: formData.get('description')?.trim() || null,
      sequence: Number(formData.get('sequence')) || 1,
      is_active: formData.get('is_active') === '1',
    }

    try {
      if (isEdit) {
        await api.put(`/certification-levels/${levelId}`, payload)
        toast.success('Certification level updated')
        navigate('/certification-levels')
      } else {
        await api.post('/certification-levels', payload)
        toast.success('Certification level created')
        setFormKey((k) => k + 1) // remount → fields clear, exam body stays selected
      }
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to save the certification level.',
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
      <div className="mx-auto max-w-3xl space-y-6">
        <div
          role="alert"
          className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
        >
          {loadError}
        </div>
        <Link
          to="/certification-levels"
          className={buttonVariants({ variant: 'outline' })}
        >
          <ArrowLeft aria-hidden="true" /> Back to certification levels
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <Link
        to="/certification-levels"
        className={buttonVariants({ variant: 'ghost' })}
      >
        <ArrowLeft aria-hidden="true" /> Back to certification levels
      </Link>

      <Card className="py-8">
        <CardHeader>
          <CardTitle>
            {isEdit ? 'Edit Certification Level' : 'Create Certification Level'}
          </CardTitle>
          <CardDescription>
            {isEdit
              ? 'Update the qualification level details below.'
              : 'Register a new qualification level.'}
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-5" noValidate key={formKey}>
            {/* Small screens: one input per row; sm and up: two per row. */}
            <div className="grid gap-5 lg:grid-cols-2">
              {/* Exam Body with SearchableSelect */}
              <FormField
                id="exam_body_id"
                label="Exam Body"
                name="exam_body"
                required
                error={errors.exam_body_id}
              >
                <SearchableSelect
                  id="exam_body_select"
                  value={examBody?.id ?? null}
                  selectedLabel={examBody?.name ?? '-- Choose an exam body --'}
                  onChange={(nextId, option) =>
                    setExamBody(nextId ? { id: nextId, name: option?.name ?? '' } : null)
                  }
                  fetchOptions={(query, signal) =>
                    api
                      .get('/exam-bodies', {
                        params: { per_page: 100, ...(query ? { search: query } : {}) },
                        signal,
                      })
                      .then(({ data }) =>
                        data.exam_bodies.map((b) => ({ id: b.id, name: b.name }))
                      )
                  }
                  placeholder="-- Choose an exam body --"
                  searchPlaceholder="Type to find an exam body…"
                />
              </FormField>

              <FormField
                id="code"
                label="Code"
                name="code"
                required
                defaultValue={level?.code ?? ''}
                placeholder="e.g. DIPLOMA"
                autoComplete="off"
                maxLength={30}
                error={errors.code}
              />

              <FormField
                id="name"
                label="Name"
                name="name"
                required
                defaultValue={level?.name ?? ''}
                placeholder="e.g. Diploma"
                autoComplete="off"
                error={errors.name}
              />

              <FormField
                id="sequence"
                label="Sequence"
                name="sequence"
                required
                defaultValue={level?.sequence ?? 1}
                type="number"
                min={0}
                max={32767}
                error={errors.sequence}
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
                    defaultChecked={level?.is_active ?? true}
                  />
                  Level is currently active
                </label>
              </div>
            </div>

            <FormField
              id="description"
              label="Description"
              name="description"
              required
              multiline
              rows={3}
              defaultValue={level?.description ?? ''}
              placeholder="Details about the qualification level"
              error={errors.description}
            />

            <div className="flex items-center gap-3">
              <Button type="submit" disabled={submitting}>
                {submitting
                  ? 'Saving…'
                  : isEdit
                  ? 'Save Changes'
                  : 'Create Certification Level'}
              </Button>
              <Link
                to="/certification-levels"
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