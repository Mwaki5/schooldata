import { ArrowLeft, CheckCircle2, Undo2 } from 'lucide-react'
import { useCallback, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { FormField } from '@/components/form-field'
import { Button, buttonVariants } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Select } from '@/components/ui/select'
import { StudentPicker } from '@/components/finance/student-picker'
import { ReversalDialog } from '@/components/finance/reversal-dialog'
import api, { loadErrorMessage, validationErrors } from '@/lib/api'
import {
  INVOICE_TYPE_LABELS,
  OTHER_INVOICE_TYPES,
  formatAmount,
  formatKes,
  newIdempotencyKey,
} from '@/lib/finance-constants'
import { StatusPill } from '@/pages/invoices/status-pill'
import { useAuth } from '@/context/AuthContext'

const TABS = [
  { id: 'fee', label: 'Fee Invoice' },
  { id: 'other', label: 'Other Invoice' },
  { id: 'reverse', label: 'Reverse Invoice' },
]

/**
 * Raise invoices against a student, or correct one already on record.
 *
 * The ledger is write-once, so this page never edits. It offers three
 * tabs: a structured fee invoice priced by the backend from a fee
 * structure, a free-form "other" invoice the desk amounts by hand, and
 * a reversal tab that corrects a mistake by writing a linked negative
 * row. Every figure shown as read-only (the fee amount, the active
 * session, the receipt/invoice number) is decided server-side; the
 * client sends only ids and text.
 */
export default function InvoiceForm() {
  const { can } = useAuth()
  const [tab, setTab] = useState('fee')

  const visibleTabs = TABS.filter(
    (candidate) => candidate.id !== 'reverse' || can('invoices.reverse'),
  )

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold">New invoice</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Invoices are immutable. To correct one, reverse it — the original
            stays on the ledger.
          </p>
        </div>

        <Link
          to="/invoices"
          className={buttonVariants({ variant: 'outline', size: 'sm' })}
        >
          <ArrowLeft aria-hidden="true" className="size-4" />
          Back to ledger
        </Link>
      </div>

      <div
        role="tablist"
        aria-label="Invoice actions"
        className="flex flex-wrap gap-1 border-b"
      >
        {visibleTabs.map((candidate) => (
          <button
            key={candidate.id}
            type="button"
            role="tab"
            aria-selected={tab === candidate.id}
            onClick={() => setTab(candidate.id)}
            className={
              '-mb-px border-b-2 px-4 py-2.5 text-sm font-medium transition-colors ' +
              (tab === candidate.id
                ? 'border-primary text-foreground'
                : 'border-transparent text-muted-foreground hover:text-foreground')
            }
          >
            {candidate.label}
          </button>
        ))}
      </div>

      {tab === 'fee' && <FeeInvoiceTab />}
      {tab === 'other' && <OtherInvoiceTab />}
      {tab === 'reverse' && <ReverseInvoiceTab />}
    </div>
  )
}

/** The structured fee invoice: priced by the backend from the structure. */
function FeeInvoiceTab() {
  const navigate = useNavigate()
  const [student, setStudent] = useState(null)
  const [session, setSession] = useState(null)
  const [duplicateMode, setDuplicateMode] = useState('warn')
  const [structures, setStructures] = useState([])
  const [structuresLoading, setStructuresLoading] = useState(false)
  const [feeStructureId, setFeeStructureId] = useState('')
  const [description, setDescription] = useState('')
  const [confirmDuplicate, setConfirmDuplicate] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState(null)
  const [success, setSuccess] = useState(null)

  const selectedStructure = structures.find(
    (structure) => String(structure.id) === String(feeStructureId),
  )

  const handleStudentChange = useCallback((next) => {
    setStudent(next)
    setFeeStructureId('')
    setConfirmDuplicate(false)
    setSuccess(null)
    setErrors({})
    setFormError(null)

    if (!next) {
      setStructures([])
      setSession(null)

      return
    }

    setStructuresLoading(true)

    api
      .get('/invoices/fee-structures', { params: { student_id: next.id } })
      .then(({ data }) => {
        setStructures(data.fee_structures ?? [])
        setSession(data.academic_session ?? null)
        setDuplicateMode(data.duplicate_fee_invoice ?? 'warn')
      })
      .catch((error) => {
        setStructures([])
        setSession(null)
        setFormError(
          loadErrorMessage(
            error,
            'Fee structures',
            'Unable to load fee structures for this student.',
          ),
        )
      })
      .finally(() => setStructuresLoading(false))
  }, [])

  const alreadyInvoiced = Boolean(selectedStructure?.already_invoiced)
  const blockedByDuplicate = alreadyInvoiced && duplicateMode === 'block'

  const handleSubmit = async (event) => {
    event.preventDefault()

    if (!student || !selectedStructure) {
      return
    }

    // Under `warn`, a duplicate must be explicitly confirmed before the
    // request goes out; under `block` the submit is disabled outright.
    if (alreadyInvoiced && duplicateMode === 'warn' && !confirmDuplicate) {
      setErrors({
        fee_structure_id:
          'This structure is already invoiced for this student this session. Tick the confirmation to raise it again.',
      })

      return
    }

    setSubmitting(true)
    setErrors({})
    setFormError(null)

    try {
      const { data } = await api.post('/invoices/fee', {
        student_id: student.id,
        fee_structure_id: selectedStructure.id,
        description: description.trim() || undefined,
        ...(alreadyInvoiced && duplicateMode === 'warn'
          ? { confirm_duplicate: true }
          : {}),
        idempotency_key: newIdempotencyKey(),
      })

      toast.success(`Invoice ${data.invoice.invoice_number} raised`)
      setSuccess(data.invoice)

      // The form clears after a successful write.
      setStudent(null)
      setStructures([])
      setSession(null)
      setFeeStructureId('')
      setDescription('')
      setConfirmDuplicate(false)
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        setFormError(
          loadErrorMessage(
            error,
            'Invoice',
            'Unable to raise the invoice. Please try again.',
          ),
        )
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Fee invoice</CardTitle>
        <CardDescription>
          The amount is computed by the server from the fee structure's live
          items — you cannot type one here.
        </CardDescription>
      </CardHeader>

      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-6">
          {success && (
            <SuccessNotice
              title={`Invoice ${success.invoice_number} raised`}
              body={`${formatKes(success.amount)} against ${success.student_name}.`}
              onDismiss={() => setSuccess(null)}
              onView={() => navigate(`/invoices/${success.id}`)}
            />
          )}

          <FormField
            id="fee-student"
            label="Student"
            required
            error={errors.student_id}
          >
            <StudentPicker
              id="fee-student"
              endpoint="/invoices/students"
              value={student?.id ?? null}
              student={student}
              onChange={handleStudentChange}
            />
          </FormField>

          {session && (
            <div className="rounded-lg bg-muted px-3 py-2 text-sm">
              <span className="text-muted-foreground">Active session: </span>
              <span className="font-medium">{session.name}</span>
              <span className="text-muted-foreground"> (set by the server)</span>
            </div>
          )}

          <FormField
            id="fee-structure"
            label="Fee structure"
            required
            error={errors.fee_structure_id}
          >
            <Select
              id="fee-structure"
              value={feeStructureId}
              onChange={(event) => {
                setFeeStructureId(event.target.value)
                setConfirmDuplicate(false)
                setErrors({})
              }}
              disabled={!student || structuresLoading}
              aria-invalid={errors.fee_structure_id ? 'true' : undefined}
            >
              <option value="">
                {structuresLoading
                  ? 'Loading structures…'
                  : !student
                    ? 'Choose a student first'
                    : structures.length === 0
                      ? 'No applicable structures'
                      : '— Choose a fee structure —'}
              </option>
              {structures.map((structure) => (
                <option key={structure.id} value={structure.id}>
                  {structure.name} — {formatKes(structure.total)}
                  {structure.already_invoiced ? ' (already invoiced)' : ''}
                </option>
              ))}
            </Select>
          </FormField>

          {alreadyInvoiced && (
            <div
              role="status"
              className={
                blockedByDuplicate
                  ? 'rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive'
                  : 'rounded-lg bg-amber-500/10 px-3 py-2 text-sm text-amber-600 dark:text-amber-400'
              }
            >
              {blockedByDuplicate
                ? 'This student already has an active invoice for this structure this session, and duplicates are blocked. Reverse the existing invoice first if this one should replace it.'
                : 'This student already has an active invoice for this structure this session.'}
            </div>
          )}

          {alreadyInvoiced && duplicateMode === 'warn' && (
            <label className="flex items-start gap-2 text-sm">
              <input
                type="checkbox"
                checked={confirmDuplicate}
                onChange={(event) => setConfirmDuplicate(event.target.checked)}
                className="mt-0.5 size-4"
              />
              <span>
                I understand this raises a second invoice for the same
                structure.
              </span>
            </label>
          )}

          <FormField
            id="fee-amount"
            label="Amount"
            error={errors.amount}
          >
            <Input
              id="fee-amount"
              readOnly
              disabled={!selectedStructure}
              value={
                selectedStructure ? formatAmount(selectedStructure.total) : ''
              }
              className="text-right tabular-nums"
            />
            <p className="text-xs text-muted-foreground">
              Computed by the server from the structure&apos;s live items.
            </p>
          </FormField>

          <FormField
            id="fee-description"
            label="Description"
            value={description}
            onChange={(event) => setDescription(event.target.value)}
            error={errors.description}
            placeholder="Term 1 tuition (optional)"
          />

          {formError && (
            <p role="alert" className="text-sm font-medium text-destructive">
              {formError}
            </p>
          )}

          <div className="flex justify-end">
            <Button
              type="submit"
              disabled={
                submitting ||
                !student ||
                !selectedStructure ||
                blockedByDuplicate
              }
            >
              {submitting ? 'Raising…' : 'Raise invoice'}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}

/** The free-form "other" invoice: penalty, hostel, exam fee, transport. */
function OtherInvoiceTab() {
  const navigate = useNavigate()
  const [student, setStudent] = useState(null)
  const [invoiceType, setInvoiceType] = useState('PENALTY')
  const [description, setDescription] = useState('')
  const [amount, setAmount] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState(null)
  const [success, setSuccess] = useState(null)

  const handleSubmit = async (event) => {
    event.preventDefault()

    setSubmitting(true)
    setErrors({})
    setFormError(null)

    try {
      const { data } = await api.post('/invoices/other', {
        student_id: student.id,
        invoice_type: invoiceType,
        description: description.trim(),
        amount: Number(amount),
        idempotency_key: newIdempotencyKey(),
      })

      toast.success(`Invoice ${data.invoice.invoice_number} raised`)
      setSuccess(data.invoice)

      setStudent(null)
      setInvoiceType('PENALTY')
      setDescription('')
      setAmount('')
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        setFormError(
          loadErrorMessage(
            error,
            'Invoice',
            'Unable to raise the invoice. Please try again.',
          ),
        )
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Other invoice</CardTitle>
        <CardDescription>
          A free-form charge the desk amounts by hand — a penalty, a hostel
          bed, an exam fee.
        </CardDescription>
      </CardHeader>

      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-6">
          {success && (
            <SuccessNotice
              title={`Invoice ${success.invoice_number} raised`}
              body={`${formatKes(success.amount)} against ${success.student_name}.`}
              onDismiss={() => setSuccess(null)}
              onView={() => navigate(`/invoices/${success.id}`)}
            />
          )}

          <FormField
            id="other-student"
            label="Student"
            required
            error={errors.student_id}
          >
            <StudentPicker
              id="other-student"
              endpoint="/invoices/students"
              value={student?.id ?? null}
              student={student}
              onChange={(next) => {
                setStudent(next)
                setSuccess(null)
              }}
            />
          </FormField>

          <FormField
            id="other-type"
            label="Invoice type"
            required
            error={errors.invoice_type}
          >
            <Select
              id="other-type"
              value={invoiceType}
              onChange={(event) => setInvoiceType(event.target.value)}
            >
              {OTHER_INVOICE_TYPES.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </Select>
          </FormField>

          <FormField
            id="other-amount"
            label="Amount"
            required
            type="number"
            min="0.01"
            step="0.01"
            value={amount}
            onChange={(event) => setAmount(event.target.value)}
            error={errors.amount}
            inputClassName="text-right tabular-nums"
            placeholder="0.00"
          />

          <FormField
            id="other-description"
            label="Description"
            required
            multiline
            rows={2}
            value={description}
            onChange={(event) => setDescription(event.target.value)}
            error={errors.description}
            placeholder="Late registration penalty"
          />

          {formError && (
            <p role="alert" className="text-sm font-medium text-destructive">
              {formError}
            </p>
          )}

          <div className="flex justify-end">
            <Button type="submit" disabled={submitting || !student}>
              {submitting ? 'Raising…' : 'Raise invoice'}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}

/** The reversal tab: correct a mistake by writing a linked negative row. */
function ReverseInvoiceTab() {
  const [student, setStudent] = useState(null)
  const [invoices, setInvoices] = useState([])
  const [loading, setLoading] = useState(false)
  const [loadError, setLoadError] = useState(null)
  const [target, setTarget] = useState(null)
  const [preview, setPreview] = useState(null)
  const [previewError, setPreviewError] = useState(null)
  const [reason, setReason] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState(null)

  const refreshInvoices = useCallback((studentId) => {
    setLoading(true)
    setLoadError(null)

    api
      .get(`/students/${studentId}/invoices`)
      .then(({ data }) => setInvoices(data.invoices ?? []))
      .catch((error) => {
        setInvoices([])
        setLoadError(
          loadErrorMessage(
            error,
            'Invoices',
            'Unable to load this student’s invoices.',
          ),
        )
      })
      .finally(() => setLoading(false))
  }, [])

  const handleStudentChange = useCallback(
    (next) => {
      setStudent(next)
      setInvoices([])
      setLoadError(null)

      if (next) {
        refreshInvoices(next.id)
      }
    },
    [refreshInvoices],
  )

  const openDialog = async (invoice) => {
    setTarget(invoice)
    setPreview(null)
    setPreviewError(null)
    setReason('')
    setSubmitError(null)

    try {
      const { data } = await api.get(`/invoices/${invoice.id}/reversal-preview`)

      setPreview(data)
    } catch (error) {
      setPreviewError(
        loadErrorMessage(
          error,
          'Effect preview',
          'Unable to load the effect of this reversal.',
        ),
      )
    }
  }

  const confirmReversal = async () => {
    setSubmitting(true)
    setSubmitError(null)

    try {
      const { data } = await api.post(`/invoices/${target.id}/reverse`, {
        reason: reason.trim(),
        idempotency_key: newIdempotencyKey(),
      })

      toast.success(`Reversed by ${data.invoice.invoice_number}`)
      setTarget(null)

      // Keep the student, refresh their table.
      if (student) {
        refreshInvoices(student.id)
      }
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (fieldErrors.reason) {
        setSubmitError(fieldErrors.reason)
      } else {
        setSubmitError(
          loadErrorMessage(
            error,
            'Reversal',
            'Unable to reverse this invoice. It may already have been reversed.',
          ),
        )
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Reverse invoice</CardTitle>
        <CardDescription>
          A reversal writes a new linked row with a negative amount. The
          original is never edited or deleted, and the reversal cannot itself
          be reversed.
        </CardDescription>
      </CardHeader>

      <CardContent className="space-y-6">
        <FormField id="reverse-student" label="Student" required>
          <StudentPicker
            id="reverse-student"
            endpoint="/invoices/students"
            value={student?.id ?? null}
            student={student}
            onChange={handleStudentChange}
          />
        </FormField>

        {loadError && (
          <p role="alert" className="text-sm font-medium text-destructive">
            {loadError}
          </p>
        )}

        {student && !loadError && invoices.length === 0 && !loading && (
          <p className="py-6 text-center text-sm text-muted-foreground">
            This student has no invoices on the ledger.
          </p>
        )}

        {invoices.length > 0 && (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b text-left text-muted-foreground">
                  <th scope="col" className="pb-3 font-medium whitespace-nowrap">
                    Invoice #
                  </th>
                  <th scope="col" className="pb-3 font-medium whitespace-nowrap">
                    Type
                  </th>
                  <th scope="col" className="pb-3 font-medium">
                    Description
                  </th>
                  <th
                    scope="col"
                    className="pb-3 text-right font-medium whitespace-nowrap"
                  >
                    Amount
                  </th>
                  <th
                    scope="col"
                    className="pb-3 text-right font-medium whitespace-nowrap"
                  >
                    Outstanding
                  </th>
                  <th scope="col" className="pb-3 font-medium whitespace-nowrap">
                    Status
                  </th>
                  <th scope="col" className="pb-3 text-right font-medium">
                    <span className="sr-only">Action</span>
                  </th>
                </tr>
              </thead>

              <tbody className="divide-y">
                {invoices.map((invoice) => {
                  // A reversal row, or an original already reversed, is
                  // final — nothing further may be written against it.
                  const reversible =
                    invoice.entry_kind === 'INVOICE' &&
                    invoice.status !== 'reversed'

                  return (
                    <tr key={invoice.id} className="hover:bg-muted/40">
                      <td className="py-3 font-medium whitespace-nowrap">
                        {invoice.invoice_number}
                      </td>
                      <td className="py-3 whitespace-nowrap">
                        {INVOICE_TYPE_LABELS[invoice.invoice_type] ??
                          invoice.invoice_type}
                      </td>
                      <td className="max-w-xs truncate py-3 text-muted-foreground">
                        {invoice.description ?? '—'}
                      </td>
                      <td className="py-3 text-right tabular-nums whitespace-nowrap">
                        {formatAmount(invoice.amount)}
                      </td>
                      <td className="py-3 text-right tabular-nums whitespace-nowrap">
                        {formatAmount(invoice.outstanding)}
                      </td>
                      <td className="py-3 whitespace-nowrap">
                        <StatusPill status={invoice.status} />
                      </td>
                      <td className="py-3 text-right whitespace-nowrap">
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          disabled={!reversible}
                          onClick={() => openDialog(invoice)}
                          title={
                            reversible
                              ? 'Reverse this invoice'
                              : 'Reversal rows and reversed invoices cannot be reversed again'
                          }
                        >
                          <Undo2 aria-hidden="true" className="size-4" />
                          Reverse
                        </Button>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </CardContent>

      {target && (
        <ReversalDialog
          title={`Reverse ${target.invoice_number}`}
          preview={preview}
          previewError={previewError}
          reason={reason}
          onReasonChange={setReason}
          submitting={submitting}
          submitError={submitError}
          onCancel={() => setTarget(null)}
          onConfirm={confirmReversal}
        />
      )}
    </Card>
  )
}

/** The banner a successful write leaves behind, naming the document. */
function SuccessNotice({ title, body, onDismiss, onView }) {
  return (
    <div
      role="status"
      className="flex items-start justify-between gap-4 rounded-lg bg-emerald-500/10 px-3 py-2 text-sm text-emerald-600 dark:text-emerald-400"
    >
      <div className="flex items-start gap-2">
        <CheckCircle2 aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
        <div>
          <p className="font-medium">{title}</p>
          {body && <p className="text-muted-foreground">{body}</p>}
        </div>
      </div>

      <div className="flex shrink-0 items-center gap-2">
        {onView && (
          <button
            type="button"
            onClick={onView}
            className="font-medium underline-offset-4 hover:underline"
          >
            View
          </button>
        )}
        <button
          type="button"
          onClick={onDismiss}
          aria-label="Dismiss"
          className="text-muted-foreground hover:text-foreground"
        >
          ×
        </button>
      </div>
    </div>
  )
}
