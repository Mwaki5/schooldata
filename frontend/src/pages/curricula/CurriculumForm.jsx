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
import api, { loadErrorMessage, validationErrors } from '@/lib/api'

export default function CurriculumForm() {
  const { curriculumId } = useParams()
  const isEdit = Boolean(curriculumId)
  const navigate = useNavigate()

  const [curriculum, setCurriculum] = useState(null)
  const [examBody, setExamBody] = useState(null) // { id, name }
  const [loading, setLoading] = useState(isEdit)
  const [loadError, setLoadError] = useState(null)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const [formKey, setFormKey] = useState(0) // remount to clear name after create

  // Load the form state on mount.
  useEffect(() => {
    if (!isEdit) {
      return undefined
    }

    let active = true

    api
      .get(`/curricula/${curriculumId}`)
      .then(({ data }) => {
        if (active) {
          const cd = data.curriculum
          setCurriculum(cd)
          setExamBody(
            cd.exam_body_id
              ? { id: cd.exam_body_id, name: cd.exam_body_name ?? '' }
              : null,
          )
        }
      })
      .catch((error) => {
        if (active) {
          setLoadError(
            loadErrorMessage(
              error,
              'Curriculum',
              'Unable to load the curriculum. It may have been deleted.',
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
  }, [isEdit, curriculumId])

  // On create: keep the exam body selection; after submit only clear the
  // curriculum name. The exam body stays selected so the user can add several
  // versions in a row. The form remounts (formKey increment) to reset
  // uncontrolled inputs while keeping the SearchableSelect value.
  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const formData = new FormData(event.currentTarget)
    const payload = {
      exam_body_id: examBody?.id ?? null,
      name: String(formData.get('name') ?? '').trim(),
      is_active: formData.get('is_active') === '1',
    }

    try {
      if (isEdit) {
        await api.put(`/curricula/${curriculumId}`, payload)
        toast.success('Curriculum updated')
        navigate('/curricula')
      } else {
        await api.post('/curricula', payload)
        toast.success('Curriculum created')
        setFormKey((k) => k + 1) // remount → name clears, exam body stays selected
      }
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to save the curriculum.',
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
        <Link to="/curricula" className={buttonVariants({ variant: 'outline' })}>
          <ArrowLeft aria-hidden="true" /> Back to curriculum
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <Link to="/curricula" className={buttonVariants({ variant: 'ghost' })}>
        <ArrowLeft aria-hidden="true" /> Back to curriculum
      </Link>

      <Card className="py-8">
        <CardHeader>
          <CardTitle>
            {isEdit ? 'Edit Curriculum' : 'Create Curriculum'}
          </CardTitle>
          <CardDescription>
            {isEdit
              ? 'Update the curriculum version details below.'
              : 'Register a new curriculum version for an exam body.'}
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form
            onSubmit={handleSubmit}
            className="space-y-5"
            noValidate
            key={formKey}
          >
            <div className="grid gap-5">
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
                    setExamBody(
                      nextId ? { id: nextId, name: option?.name ?? '' } : null,
                    )
                  }
                  fetchOptions={(query, signal) =>
                    api
                      .get('/exam-bodies', {
                        params: { per_page: 100, ...(query ? { search: query } : {}) },
                        signal,
                      })
                      .then(({ data }) =>
                        data.exam_bodies.map((b) => ({ id: b.id, name: b.name })),
                      )
                  }
                  placeholder="-- Choose an exam body --"
                  searchPlaceholder="Type to find an exam body…"
                />
              </FormField>

              <FormField
                id="name"
                label="Curriculum Name"
                name="name"
                required
                defaultValue={curriculum?.name ?? ''}
                placeholder="e.g. 2024 Curriculum"
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
                    defaultChecked={curriculum?.is_active ?? true}
                  />
                  Curriculum is currently active
                </label>
              </div>
            </div>

            <div className="flex items-center gap-3">
              <Button type="submit" disabled={submitting}>
                {submitting
                  ? 'Saving…'
                  : isEdit
                    ? 'Save Changes'
                    : 'Create Curriculum'}
              </Button>
              <Link to="/curricula" className={buttonVariants({ variant: 'outline' })}>
                Cancel
              </Link>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
