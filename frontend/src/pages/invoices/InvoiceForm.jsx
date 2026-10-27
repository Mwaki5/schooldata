import { ArrowLeft, Plus, Trash2 } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import PageLoader from '@/components/page-loader'
import { FormField } from '@/components/form-field'
import { Button, buttonVariants } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { Select } from '@/components/ui/select'
import api, { isNotFound, loadErrorMessage, validationErrors } from '@/lib/api'
import {
  LINE_TYPES,
  STATUS_LABELS,
  amountFormat,
} from '@/pages/invoices/invoice-constants'

const EMPTY_LINE = { type: 'fee', description: '', amount: '' }

function studentLabel(row) {
  return row.login_id ? `${row.name} — ${row.login_id}` : row.name
}

/**
 * Raise an invoice against a student, or correct one already on
 * record. A new invoice picks the billed student and session; an
 * existing one keeps that anchor fixed and edits its status,
 * description and lines instead — a wrong anchor is corrected by
 * voiding the invoice and raising a new one.
 *
 * The lines are controlled state rather than form fields: the running
 * total reads off them on every keystroke, and the row indexes line
 * up with the API's `items.0.type`-style validation errors.
 */
export default function InvoiceForm() {
  const { invoiceId } = useParams()
  const isEdit = Boolean(invoiceId)
  const navigate = useNavigate()

  const [invoice, setInvoice] = useState(null)
  const [loading, setLoading] = useState(isEdit)
  const [loadError, setLoadError] = useState(null)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)

  // Create mode: the chosen student and session.
  const [student, setStudent] = useState(null)
  const [sessionId, setSessionId] = useState(null)
  const [sessionLabel, setSessionLabel] = useState('')

  // Edit mode: the lifecycle state the invoice carries.
  const [status, setStatus] = useState('pending')

  const [description, setDescription] = useState('')
  const [items, setItems] = useState([{ key: 0, ...EMPTY_LINE }])
  const nextKey = useRef(1)

  // Load the invoice being edited, its status and its lines.
  useEffect(() => {
    if (!isEdit) {
      return undefined
    }

    let active = true

    api
      .get(`/invoices/${invoiceId}`)
      .then(({ data }) => {
        if (!active) {
          return
        }

        const loaded = data.invoice

        setInvoice(loaded)
        setStatus(loaded.status)
        setDescription(loaded.description ?? '')
        setItems(
          loaded.items.map((row, index) => ({
            key: index,
            type: row.type,
            description: row.description,
            amount: row.amount,
          })),
        )
        nextKey.current = loaded.items.length
      })
      .catch((error) => {
        if (!active) {
          return
        }

        setLoadError(
          isNotFound(error)
            ? 'Invoice not found. It may have been deleted.'
            : 'Unable to load the invoice. Please try again.',
        )
      })
      .finally(() => {
        if (active) {
          setLoading(false)
        }
      })

    return () => {
      active = false
    }
  }, [isEdit, invoiceId])

  // The session picker serves every session at once — a couple of
  // rows per year — and the active session preselects.
  useEffect(() => {
    if (isEdit) {
      return undefined
    }

    let active = true

    api
      .get('/academic-sessions/options')
      .then(({ data }) => {
        if (!active) {
          return
        }

        const current = (data.academic_sessions ?? []).find(
          (session) => session.is_active,
        )

        if (current) {
          setSessionId(current.id)
          setSessionLabel(current.name)
        }
      })
      .catch((error) => {
        if (active) {
          toast.error(
            loadErrorMessage(
              error,
              'Academic sessions',
              'Unable to load academic sessions.',
            ),
          )
        }
      })

    return () => {
      active = false
    }
  }, [isEdit])

  const updateLine = (key, patch) =>
    setItems((current) =>
      current.map((item) => (item.key === key ? { ...item, ...patch } : item)),
    )

  const addLine = () => {
    const key = nextKey.current
    nextKey.current += 1

    setItems((current) => [...current, { key, ...EMPTY_LINE }])
  }

  const removeLine = (key) =>
    setItems((current) =>
      current.length === 1
        ? current
        : current.filter((item) => item.key !== key),
    )

  const total = items.reduce(
    (sum, item) => sum + (Number.parseFloat(item.amount) || 0),
    0,
  )

  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const payload = {
      description: description.trim() === '' ? null : description.trim(),
      items: items.map((item) => ({
        type: item.type,
        description: item.description.trim(),
        amount: item.amount,
      })),
    }

    if (isEdit) {
      payload.status = status
    } else {
      payload.student_id = student?.id ?? null
      payload.academic_session_id = sessionId
    }

    try {
      const { data } = isEdit
        ? await api.put(`/invoices/${invoiceId}`, payload)
        : await api.post('/invoices', payload)

      toast.success(
        isEdit
          ? `Invoice ${data.invoice.invoice_number} updated`
          : `Invoice ${data.invoice.invoice_number} raised`,
      )
      navigate('/invoices')
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to save the invoice.',
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
          to="/invoices"
          className={buttonVariants({ variant: 'outline' })}
        >
          <ArrowLeft aria-hidden="true" />
          Back to invoices
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <Link to="/invoices" className={buttonVariants({ variant: 'ghost' })}>
        <ArrowLeft aria-hidden="true" />
        Back to invoices
      </Link>

      <Card className="py-8">
        <CardHeader>
          <CardTitle>
            {isEdit
              ? `Edit ${invoice?.invoice_number ?? 'Invoice'}`
              : 'Invoice Student'}
          </CardTitle>
          <CardDescription>
            {isEdit
              ? 'Correct the status, description and lines below. The billed student and session stay fixed.'
              : 'Bill one student: pick who and which session, then add the charges.'}
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-6" noValidate>
            <FormField
              id="student_id"
              label="Student"
              required={!isEdit}
              error={errors.student_id}
            >
              {isEdit ? (
                <div className="flex h-11 items-center gap-2 rounded-xl border border-input bg-muted/40 px-4 text-sm">
                  <span className="font-medium">{invoice?.student_name}</span>
                  {invoice?.student_login_id && (
                    <span className="text-muted-foreground">
                      · {invoice.student_login_id}
                    </span>
                  )}
                </div>
              ) : (
                <SearchableSelect
                  id="student_select"
                  value={student?.id ?? null}
                  selectedLabel={student ? studentLabel(student) : ''}
                  onChange={(_, option) => setStudent(option)}
                  fetchOptions={(query, signal) =>
                    api
                      .get('/invoices/students', {
                        params: query ? { search: query } : {},
                        signal,
                      })
                      .then(({ data }) => data.students)
                  }
                  placeholder="-- Choose a student --"
                  searchPlaceholder="Type a name or admission number…"
                  labelKey="name"
                />
              )}
            </FormField>

            <FormField
              id="academic_session_id"
              label="Academic Session"
              required={!isEdit}
              error={errors.academic_session_id}
            >
              {isEdit ? (
                <div className="flex h-11 items-center rounded-xl border border-input bg-muted/40 px-4 text-sm">
                  {invoice?.academic_session_name ?? '—'}
                </div>
              ) : (
                <SearchableSelect
                  id="academic_session_select"
                  value={sessionId}
                  selectedLabel={sessionLabel || '-- Choose an academic session --'}
                  onChange={(nextId, option) => {
                    setSessionId(nextId)
                    setSessionLabel(option?.name ?? '')
                  }}
                  fetchOptions={(query, signal) =>
                    api
                      .get('/academic-sessions/options', {
                        params: query ? { search: query } : {},
                        signal,
                      })
                      .then(({ data }) =>
                        (data.academic_sessions ?? []).map((row) => ({
                          id: row.id,
                          name: row.name,
                        })),
                      )
                  }
                  placeholder="-- Choose an academic session --"
                  searchPlaceholder="Type a session to search…"
                />
              )}
            </FormField>

            {isEdit && (
              <FormField
                id="status"
                label="Status"
                required
                error={errors.status}
              >
                <Select
                  id="status"
                  value={status}
                  onChange={(event) => setStatus(event.target.value)}
                >
                  {Object.entries(STATUS_LABELS).map(([value, label]) => (
                    <option key={value} value={value}>
                      {label}
                    </option>
                  ))}
                </Select>
              </FormField>
            )}

            <FormField
              id="description"
              label="Description"
              multiline
              value={description}
              onChange={(event) => setDescription(event.target.value)}
              maxLength={500}
              placeholder="Optional note for the whole bill…"
              error={errors.description}
            />

            <FormField
              id="items"
              label="Line Items"
              required
              error={errors.items}
              className="space-y-4"
            >
              <div id="items" className="space-y-3">
                {items.map((item, index) => (
                  <div
                    key={item.key}
                    className="flex flex-wrap items-start gap-3 rounded-xl border p-4"
                  >
                    <FormField
                      id={`item-type-${item.key}`}
                      label="Type"
                      required
                      className="w-40"
                      error={errors[`items.${index}.type`]}
                    >
                      <Select
                        id={`item-type-${item.key}`}
                        value={item.type}
                        onChange={(event) =>
                          updateLine(item.key, { type: event.target.value })
                        }
                      >
                        {LINE_TYPES.map((type) => (
                          <option key={type.value} value={type.value}>
                            {type.label}
                          </option>
                        ))}
                      </Select>
                    </FormField>

                    <FormField
                      id={`item-description-${item.key}`}
                      label="Description"
                      required
                      className="min-w-56 flex-1"
                      error={errors[`items.${index}.description`]}
                      value={item.description}
                      onChange={(event) =>
                        updateLine(item.key, {
                          description: event.target.value,
                        })
                      }
                      maxLength={500}
                      placeholder="e.g. Term 2 tuition"
                      autoComplete="off"
                    />

                    <FormField
                      id={`item-amount-${item.key}`}
                      label="Amount"
                      required
                      className="w-36"
                      error={errors[`items.${index}.amount`]}
                      type="number"
                      min="0"
                      step="0.01"
                      inputMode="decimal"
                      value={item.amount}
                      onChange={(event) =>
                        updateLine(item.key, { amount: event.target.value })
                      }
                      placeholder="0.00"
                    />

                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="mt-7 text-muted-foreground hover:text-destructive"
                      disabled={items.length === 1}
                      onClick={() => removeLine(item.key)}
                      aria-label={`Remove line ${index + 1}`}
                    >
                      <Trash2 aria-hidden="true" />
                    </Button>
                  </div>
                ))}
              </div>

              <div className="flex flex-wrap items-center justify-between gap-3">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={addLine}
                >
                  <Plus aria-hidden="true" />
                  Add Line
                </Button>
                <p className="text-sm text-muted-foreground">
                  Total
                  <span className="ml-3 text-base font-semibold tabular-nums text-foreground">
                    {amountFormat.format(total)}
                  </span>
                </p>
              </div>
            </FormField>

            <div className="flex items-center gap-3">
              <Button type="submit" disabled={submitting}>
                {submitting
                  ? 'Saving…'
                  : isEdit
                    ? 'Save Changes'
                    : 'Raise Invoice'}
              </Button>
              <Link
                to="/invoices"
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
