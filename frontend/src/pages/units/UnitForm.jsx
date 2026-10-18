import { ArrowLeft } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import PageLoader from '@/components/page-loader'
import { FormField } from '@/components/form-field'
import { Button, buttonVariants } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import api, { loadErrorMessage, validationErrors } from '@/lib/api'

const MATCH_SEARCH_DEBOUNCE_MS = 400

export default function UnitForm() {
  const { unitId } = useParams()
  const isEdit = Boolean(unitId)
  const navigate = useNavigate()

  const [unit, setUnit] = useState(null)
  const [code, setCode] = useState('')
  const [name, setName] = useState('')
  const [loading, setLoading] = useState(isEdit)
  const [loadError, setLoadError] = useState(null)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const [matches, setMatches] = useState([])
  const searchSeqRef = useRef(0)

  // Load the form state on mount (edit only: masters are name-first).
  useEffect(() => {
    if (!isEdit) {
      return undefined
    }

    let active = true

    api
      .get(`/units/${unitId}`)
      .then(({ data }) => {
        if (active) {
          setUnit(data.unit)
          setCode(data.unit.code ?? '')
          setName(data.unit.name ?? '')
        }
      })
      .catch((error) => {
        if (active) {
          setLoadError(
            loadErrorMessage(
              error,
              'Unit',
              'Unable to load the unit. It may have been deleted.',
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
  }, [isEdit, unitId])

  // Duplicate prevention on create: while the user types a name, surface
  // units with similar names so a re-created catalogue entry is a
  // deliberate choice. Advisory only — names are not required to be
  // unique, and the server never merges anything.
  useEffect(() => {
    const sequence = ++searchSeqRef.current
    const handle = setTimeout(() => {
      const term = name.trim()

      if (isEdit || term.length < 3) {
        setMatches([])
        return
      }

      api
        .get('/units', { params: { search: term, per_page: 5 } })
        .then(({ data }) => {
          if (searchSeqRef.current === sequence) {
            setMatches(data.units ?? [])
          }
        })
        .catch(() => {
          if (searchSeqRef.current === sequence) {
            setMatches([])
          }
        })
    }, MATCH_SEARCH_DEBOUNCE_MS)

    return () => clearTimeout(handle)
  }, [name, isEdit])

  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const formData = new FormData(event.currentTarget)
    const payload = {
      code: code.trim(),
      name: name.trim(),
      description: formData.get('description')?.trim() || null,
      is_active: formData.get('is_active') === '1',
    }

    try {
      if (isEdit) {
        await api.put(`/units/${unitId}`, payload)
        toast.success('Unit updated')
        navigate('/units')
      } else {
        const { data } = await api.post('/units', payload)

        toast.success(
          `"${data.unit.name}" created — now assign it to courses and curricula.`,
        )
        navigate(`/units/${data.unit.id}/assign`)
      }
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to save the unit.',
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
        <Link to="/units" className={buttonVariants({ variant: 'outline' })}>
          <ArrowLeft aria-hidden="true" /> Back to units
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <Link to="/units" className={buttonVariants({ variant: 'ghost' })}>
        <ArrowLeft aria-hidden="true" /> Back to units
      </Link>

      <Card className="py-8">
        <CardHeader>
          <CardTitle>{isEdit ? 'Edit Unit' : 'Create Unit'}</CardTitle>
          <CardDescription>
            {isEdit
              ? 'Update the unit details below. Its assignments and their teaching loads are unaffected.'
              : 'Add a reusable unit to the catalogue, then assign it to courses and curricula.'}
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-5" noValidate>
            <div className="grid gap-5 lg:grid-cols-3">
              <FormField
                id="code"
                label="Code"
                required
                value={code}
                onChange={(event) => setCode(event.target.value)}
                placeholder="e.g. BCS101"
                autoComplete="off"
                maxLength={50}
                error={errors.code}
              />

              <div className="lg:col-span-2">
                <FormField
                  id="name"
                  label="Name"
                  name="name"
                  required
                  value={name}
                  onChange={(event) => setName(event.target.value)}
                  placeholder="e.g. Basic Computer Skills"
                  autoComplete="off"
                  maxLength={255}
                  error={errors.name}
                />
              </div>
            </div>

            {!isEdit && matches.length > 0 && (
              <div
                className="space-y-1 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm"
                role="status"
              >
                <p className="font-medium">Possible existing matches</p>
                <ul className="list-inside list-disc">
                  {matches.map((match) => (
                    <li key={match.id}>
                      <Link
                        to={`/units/${match.id}`}
                        className="font-medium hover:underline"
                      >
                        {match.name}
                      </Link>{' '}
                      <span className="text-muted-foreground">
                        ({match.code}) — {match.is_active ? 'Active' : 'Inactive'},{' '}
                        {match.assignments_count}{' '}
                        {match.assignments_count === 1
                          ? 'assignment'
                          : 'assignments'}
                      </span>
                    </li>
                  ))}
                </ul>
                <p className="text-muted-foreground">
                  Names do not have to be unique — continue only if this really
                  is a different unit.
                </p>
              </div>
            )}

            <FormField
              id="description"
              label="Description"
              name="description"
              multiline
              rows={3}
              defaultValue={unit?.description ?? ''}
              placeholder="What the unit covers (optional)"
              error={errors.description}
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
                  defaultChecked={unit?.is_active ?? true}
                />
                Unit is currently offered
              </label>
            </div>

            <div className="flex items-center gap-3">
              <Button type="submit" disabled={submitting}>
                {submitting
                  ? 'Saving…'
                  : isEdit
                    ? 'Save Changes'
                    : 'Create Unit'}
              </Button>
              {!isEdit && (
                <span className="text-sm text-muted-foreground">
                  You will assign it to courses in the next step.
                </span>
              )}
              <Link to="/units" className={buttonVariants({ variant: 'outline' })}>
                Cancel
              </Link>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
