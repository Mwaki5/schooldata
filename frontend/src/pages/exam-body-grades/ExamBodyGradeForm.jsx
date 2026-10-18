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

export default function ExamBodyGradeForm() {
  const { gradeId } = useParams()
  const isEdit = Boolean(gradeId)
  const navigate = useNavigate()

  const [grade, setGrade] = useState(null)
  const [examBody, setExamBody] = useState(null) // { id, name }
  const [loading, setLoading] = useState(isEdit)
  const [loadError, setLoadError] = useState(null)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const [formKey, setFormKey] = useState(0) // remount to clear fields after create

  // Load the form state on mount.
  useEffect(() => {
    if (!isEdit) {
      return undefined
    }

    let active = true

    api
      .get(`/exam-body-grades/${gradeId}`)
      .then(({ data }) => {
        if (active) {
          const gd = data.exam_body_grade
          setGrade(gd)
          setExamBody(gd.exam_body_id
            ? { id: gd.exam_body_id, name: gd.exam_body_name ?? '' }
            : null)
        }
      })
      .catch((error) => {
        if (active) {
          setLoadError(
            loadErrorMessage(
              error,
              'Grade band',
              'Unable to load the grade band. It may have been deleted.',
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
  }, [isEdit, gradeId])

  // On create: keep the exam body selection so a whole scale can be entered
  // in one sitting; the form remounts (formKey increment) to clear the
  // uncontrolled fields while the SearchableSelect value stays.
  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const formData = new FormData(event.currentTarget)
    const sequenceValue = String(formData.get('sequence') ?? '').trim()
    const payload = {
      exam_body_id: examBody?.id ?? null,
      grade: String(formData.get('grade') ?? '').trim(),
      grade_start: Number(formData.get('grade_start')),
      grade_end: Number(formData.get('grade_end')),
      sequence: sequenceValue === '' ? null : Number(sequenceValue),
      remark: formData.get('remark')?.trim() || null,
      is_active: formData.get('is_active') === '1',
    }

    try {
      if (isEdit) {
        await api.put(`/exam-body-grades/${gradeId}`, payload)
        toast.success('Grade band updated')
        navigate('/exam-body-grades')
      } else {
        await api.post('/exam-body-grades', payload)
        toast.success('Grade band created')
        setFormKey((k) => k + 1)
      }
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to save the grade band.',
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
          to="/exam-body-grades"
          className={buttonVariants({ variant: 'outline' })}
        >
          <ArrowLeft aria-hidden="true" /> Back to grade bands
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <Link
        to="/exam-body-grades"
        className={buttonVariants({ variant: 'ghost' })}
      >
        <ArrowLeft aria-hidden="true" /> Back to grade bands
      </Link>

      <Card className="py-8">
        <CardHeader>
          <CardTitle>
            {isEdit ? 'Edit Grade Band' : 'Create Grade Band'}
          </CardTitle>
          <CardDescription>
            {isEdit
              ? 'Update the grade band details below.'
              : 'Add a grade band to an exam body’s grading scale.'}
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-5" noValidate key={formKey}>
            {/* Small screens: one input per row; sm and up: two per row. */}
            <div className="grid gap-5 lg:grid-cols-2">
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
                id="grade"
                label="Grade"
                name="grade"
                required
                defaultValue={grade?.grade ?? ''}
                placeholder="e.g. A"
                autoComplete="off"
                maxLength={50}
                error={errors.grade}
              />

              <FormField
                id="grade_start"
                label="Score From"
                name="grade_start"
                required
                defaultValue={grade?.grade_start ?? ''}
                type="number"
                min={0}
                max={100}
                step={0.01}
                placeholder="e.g. 70"
                error={errors.grade_start}
              />

              <FormField
                id="grade_end"
                label="Score To"
                name="grade_end"
                required
                defaultValue={grade?.grade_end ?? ''}
                type="number"
                min={0}
                max={100}
                step={0.01}
                placeholder="e.g. 100"
                error={errors.grade_end}
              />

              <FormField
                id="sequence"
                label="Sequence"
                name="sequence"
                defaultValue={grade?.sequence ?? ''}
                type="number"
                min={0}
                max={32767}
                placeholder="Order within the scale (optional)"
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
                    defaultChecked={grade?.is_active ?? true}
                  />
                  Grade is currently awarded
                </label>
              </div>
            </div>

            <FormField
              id="remark"
              label="Remark"
              name="remark"
              multiline
              rows={3}
              defaultValue={grade?.remark ?? ''}
              placeholder="What this band means (optional)"
              error={errors.remark}
            />

            <div className="flex items-center gap-3">
              <Button type="submit" disabled={submitting}>
                {submitting
                  ? 'Saving…'
                  : isEdit
                  ? 'Save Changes'
                  : 'Create Grade Band'}
              </Button>
              <Link
                to="/exam-body-grades"
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
