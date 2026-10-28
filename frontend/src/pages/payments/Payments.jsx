import { Printer, Undo2 } from 'lucide-react'
import { useCallback, useState } from 'react'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { FormField } from '@/components/form-field'
import { Input } from '@/components/ui/input'
import { Select } from '@/components/ui/select'
import { StudentPicker } from '@/components/finance/student-picker'
import { ReversalDialog } from '@/components/finance/reversal-dialog'
import api, { loadErrorMessage, validationErrors } from '@/lib/api'
import {
  PAYMENT_METHODS,
  ENTRY_KIND_LABELS,
  formatAmount,
  formatKes,
  newIdempotencyKey,
} from '@/lib/finance-constants'
import { useAuth } from '@/context/AuthContext'

const TABS = [
  { id: 'receive', label: 'Receive Payment' },
  { id: 'reverse', label: 'Reverse Payment' },
  { id: 'refund', label: 'Refund Credit' },
]

/**
 * The cash desk: take money in, reverse a payment by writing a linked
 * negative row, and pay held credit back out.
 *
 * The ledger is write-once, so nothing here edits. A receipt is
 * corrected on the "Reverse Payment" tab, which re-opens the invoices
 * the payment had settled. Settlement is never computed here — the
 * server returns how a payment would apply (oldest-first) before it is
 * taken, and returns the derived balance after it lands.
 */
export default function Payments() {
  const { can } = useAuth()
  const [tab, setTab] = useState('receive')

  const visibleTabs = TABS.filter((candidate) => {
    if (candidate.id === 'receive') {
      return can('payments.receive')
    }

    if (candidate.id === 'reverse') {
      return can('payments.reverse')
    }

    return can('refunds.create')
  })

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">Payments</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Take money in, reverse a receipt, or pay credit back out. Every figure
          below is derived by the server&apos;s ledger.
        </p>
      </div>

      <div
        role="tablist"
        aria-label="Cash desk actions"
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

      {tab === 'receive' && <ReceivePaymentTab />}
      {tab === 'reverse' && <ReversePaymentTab />}
      {tab === 'refund' && <RefundCreditTab />}
    </div>
  )
}

/** Take a payment in: method, reference, amount, notes — with a live preview. */
function ReceivePaymentTab() {
  const { can } = useAuth()
  const [student, setStudent] = useState(null)
  const [ledger, setLedger] = useState(null)
  const [ledgerLoading, setLedgerLoading] = useState(false)
  const [preview, setPreview] = useState(null)
  const [paymentMethod, setPaymentMethod] = useState('CASH')
  const [reference, setReference] = useState('')
  const [amount, setAmount] = useState('')
  const [notes, setNotes] = useState('')
  const [date, setDate] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState(null)
  const [receipt, setReceipt] = useState(null)

  const fetchLedger = useCallback((studentId, previewAmount) => {
    setLedgerLoading(true)

    api
      .get(`/students/${studentId}/balance`, {
        params: previewAmount ? { preview_amount: previewAmount } : {},
      })
      .then(({ data }) => {
        setLedger(data)
        setPreview(data.preview ?? null)
      })
      .catch((error) => {
        setLedger(null)
        setPreview(null)
        setFormError(
          loadErrorMessage(
            error,
            'Balance',
            'Unable to load this student’s balance.',
          ),
        )
      })
      .finally(() => setLedgerLoading(false))
  }, [])

  const handleStudentChange = useCallback(
    (next) => {
      setStudent(next)
      setLedger(null)
      setPreview(null)
      setReceipt(null)
      setErrors({})
      setFormError(null)
      setAmount('')

      if (next) {
        fetchLedger(next.id)
      }
    },
    [fetchLedger],
  )

  const handleAmountChange = (value) => {
    setAmount(value)

    // Refresh the settlement preview as the cashier types an amount.
    if (student) {
      const parsed = Number(value)

      fetchLedger(student.id, parsed > 0 ? parsed.toFixed(2) : undefined)
    }
  }

  const handleSubmit = async (event) => {
    event.preventDefault()

    setSubmitting(true)
    setErrors({})
    setFormError(null)

    try {
      const { data } = await api.post('/payments', {
        student_id: student.id,
        payment_method: paymentMethod,
        reference: reference.trim() || undefined,
        amount: Number(amount),
        notes: notes.trim() || undefined,
        ...(can('payments.backdate') && date ? { date } : {}),
        idempotency_key: newIdempotencyKey(),
      })

      setReceipt(data.payment)

      // The form clears after a successful write; the balance refreshes.
      setPaymentMethod('CASH')
      setReference('')
      setAmount('')
      setNotes('')
      setDate('')
      fetchLedger(student.id)
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        setFormError(
          loadErrorMessage(
            error,
            'Payment',
            'Unable to record the payment. Please try again.',
          ),
        )
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="grid gap-6 lg:grid-cols-3">
      <Card className="lg:col-span-2">
        <CardHeader>
          <CardTitle>Receive payment</CardTitle>
          <CardDescription>
            The receipt number and the posting date are decided by the server.
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-6">
            <FormField id="pay-student" label="Student" required error={errors.student_id}>
              <StudentPicker
                id="pay-student"
                endpoint="/payments/students"
                value={student?.id ?? null}
                student={student}
                onChange={handleStudentChange}
              />
            </FormField>

            <div className="grid gap-6 sm:grid-cols-2">
              <FormField
                id="pay-method"
                label="Payment method"
                required
                error={errors.payment_method}
              >
                <Select
                  id="pay-method"
                  value={paymentMethod}
                  onChange={(event) => setPaymentMethod(event.target.value)}
                >
                  {PAYMENT_METHODS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </Select>
              </FormField>

              <FormField
                id="pay-reference"
                label="Reference"
                value={reference}
                onChange={(event) => setReference(event.target.value)}
                error={errors.reference}
                placeholder="Slip or transaction code"
              />
            </div>

            <div className="grid gap-6 sm:grid-cols-2">
              <FormField
                id="pay-amount"
                label="Amount"
                required
                type="number"
                min="0.01"
                step="0.01"
                value={amount}
                onChange={(event) => handleAmountChange(event.target.value)}
                error={errors.amount}
                inputClassName="text-right tabular-nums"
                placeholder="0.00"
              />

              {can('payments.backdate') ? (
                <FormField
                  id="pay-date"
                  label="Date"
                  type="date"
                  max={new Date().toISOString().slice(0, 10)}
                  value={date}
                  onChange={(event) => setDate(event.target.value)}
                  error={errors.date}
                />
              ) : (
                <FormField id="pay-date" label="Date">
                  <Input
                    id="pay-date"
                    readOnly
                    disabled
                    value={new Date().toLocaleDateString()}
                  />
                </FormField>
              )}
            </div>

            <FormField
              id="pay-notes"
              label="Notes"
              multiline
              rows={2}
              value={notes}
              onChange={(event) => setNotes(event.target.value)}
              error={errors.notes}
            />

            {formError && (
              <p role="alert" className="text-sm font-medium text-destructive">
                {formError}
              </p>
            )}

            <div className="flex justify-end">
              <Button type="submit" disabled={submitting || !student || !amount}>
                {submitting ? 'Recording…' : 'Record payment'}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <div className="space-y-6">
        <BalanceCard
          ledger={ledger}
          loading={ledgerLoading}
          student={student}
        />

        {preview && <SettlementPreview preview={preview} />}
      </div>

      {receipt && (
        <ReceiptCard receipt={receipt} onDismiss={() => setReceipt(null)} />
      )}
    </div>
  )
}

/** Reverse a payment: a linked negative row re-opens its invoices. */
function ReversePaymentTab() {
  const [student, setStudent] = useState(null)
  const [payments, setPayments] = useState([])
  const [loading, setLoading] = useState(false)
  const [loadError, setLoadError] = useState(null)
  const [target, setTarget] = useState(null)
  const [preview, setPreview] = useState(null)
  const [previewError, setPreviewError] = useState(null)
  const [reason, setReason] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState(null)

  const refreshPayments = useCallback((studentId) => {
    setLoading(true)
    setLoadError(null)

    api
      .get(`/students/${studentId}/payments`)
      .then(({ data }) => setPayments(data.payments ?? []))
      .catch((error) => {
        setPayments([])
        setLoadError(
          loadErrorMessage(
            error,
            'Payments',
            'Unable to load this student’s payments.',
          ),
        )
      })
      .finally(() => setLoading(false))
  }, [])

  const handleStudentChange = useCallback(
    (next) => {
      setStudent(next)
      setPayments([])
      setLoadError(null)

      if (next) {
        refreshPayments(next.id)
      }
    },
    [refreshPayments],
  )

  const openDialog = async (payment) => {
    setTarget(payment)
    setPreview(null)
    setPreviewError(null)
    setReason('')
    setSubmitError(null)

    try {
      const { data } = await api.get(`/payments/${payment.id}/reversal-preview`)

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
      await api.post(`/payments/${target.id}/reverse`, {
        reason: reason.trim(),
        idempotency_key: newIdempotencyKey(),
      })

      setTarget(null)

      // Keep the student, refresh their table.
      if (student) {
        refreshPayments(student.id)
      }
    } catch (error) {
      const fieldErrors = validationErrors(error)

      // A 409 (a refund would push net payments negative) carries its
      // explanation in `message`, which loadErrorMessage surfaces.
      setSubmitError(
        fieldErrors.reason ??
          loadErrorMessage(
            error,
            'Reversal',
            'Unable to reverse this payment. It may already have been reversed.',
          ),
      )
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Reverse payment</CardTitle>
        <CardDescription>
          A reversal writes a new linked row with a negative amount. The original
          receipt is never edited or deleted, and the invoices it settled
          re-open.
        </CardDescription>
      </CardHeader>

      <CardContent className="space-y-6">
        <FormField id="rev-student" label="Student" required>
          <StudentPicker
            id="rev-student"
            endpoint="/payments/students"
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

        {student && !loadError && payments.length === 0 && !loading && (
          <p className="py-6 text-center text-sm text-muted-foreground">
            This student has no payments on the ledger.
          </p>
        )}

        {payments.length > 0 && (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b text-left text-muted-foreground">
                  <th scope="col" className="pb-3 font-medium whitespace-nowrap">
                    Receipt #
                  </th>
                  <th scope="col" className="pb-3 font-medium whitespace-nowrap">
                    Method
                  </th>
                  <th scope="col" className="pb-3 font-medium">
                    Reference
                  </th>
                  <th
                    scope="col"
                    className="pb-3 text-right font-medium whitespace-nowrap"
                  >
                    Amount
                  </th>
                  <th scope="col" className="pb-3 font-medium whitespace-nowrap">
                    Kind
                  </th>
                  <th scope="col" className="pb-3 text-right font-medium">
                    <span className="sr-only">Action</span>
                  </th>
                </tr>
              </thead>

              <tbody className="divide-y">
                {payments.map((payment) => {
                  // A reversal row, or a receipt already reversed, is final.
                  const reversible =
                    payment.entry_kind === 'PAYMENT' && !payment.reversed_by

                  return (
                    <tr key={payment.id} className="hover:bg-muted/40">
                      <td className="py-3 font-medium whitespace-nowrap">
                        {payment.receipt_number}
                      </td>
                      <td className="py-3 whitespace-nowrap">
                        {payment.payment_method_label ?? payment.payment_method}
                      </td>
                      <td className="max-w-xs truncate py-3 text-muted-foreground">
                        {payment.reference ?? '—'}
                      </td>
                      <td className="py-3 text-right tabular-nums whitespace-nowrap">
                        {formatAmount(payment.amount)}
                      </td>
                      <td className="py-3 whitespace-nowrap text-muted-foreground">
                        {ENTRY_KIND_LABELS[payment.entry_kind] ??
                          payment.entry_kind}
                      </td>
                      <td className="py-3 text-right whitespace-nowrap">
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          disabled={!reversible}
                          onClick={() => openDialog(payment)}
                          title={
                            reversible
                              ? 'Reverse this payment'
                              : 'Reversal rows and reversed receipts cannot be reversed again'
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
          title={`Reverse ${target.receipt_number}`}
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

/** Refund held credit back out, up to the student's current credit. */
function RefundCreditTab() {
  const [student, setStudent] = useState(null)
  const [ledger, setLedger] = useState(null)
  const [ledgerLoading, setLedgerLoading] = useState(false)
  const [paymentMethod, setPaymentMethod] = useState('CASH')
  const [reference, setReference] = useState('')
  const [amount, setAmount] = useState('')
  const [reason, setReason] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState(null)
  const [refund, setRefund] = useState(null)
  // The refund-reversal desk: the student's refunds and the one being
  // corrected. A refund paid in error is reversed here, restoring the
  // credit it had cancelled.
  const [refunds, setRefunds] = useState([])
  const [refundsLoading, setRefundsLoading] = useState(false)
  const [refundsError, setRefundsError] = useState(null)
  const [target, setTarget] = useState(null)
  const [preview, setPreview] = useState(null)
  const [previewError, setPreviewError] = useState(null)
  const [reverseReason, setReverseReason] = useState('')
  const [reversing, setReversing] = useState(false)
  const [reverseError, setReverseError] = useState(null)

  const credit = Number(ledger?.credit ?? 0)
  const exceedsCredit = Number(amount) > credit

  const refreshRefunds = useCallback((studentId) => {
    setRefundsLoading(true)
    setRefundsError(null)

    api
      .get(`/students/${studentId}/refunds`)
      .then(({ data }) => setRefunds(data.refunds ?? []))
      .catch((error) => {
        setRefunds([])
        setRefundsError(
          loadErrorMessage(
            error,
            'Refunds',
            'Unable to load this student’s refunds.',
          ),
        )
      })
      .finally(() => setRefundsLoading(false))
  }, [])

  const handleStudentChange = useCallback((next) => {
    setStudent(next)
    setLedger(null)
    setRefund(null)
    setErrors({})
    setFormError(null)
    setAmount('')
    setReason('')
    setRefunds([])

    if (!next) {
      return
    }

    setLedgerLoading(true)

    api
      .get(`/students/${next.id}/balance`)
      .then(({ data }) => setLedger(data))
      .catch((error) => {
        setLedger(null)
        setFormError(
          loadErrorMessage(
            error,
            'Balance',
            'Unable to load this student’s balance.',
          ),
        )
      })
      .finally(() => setLedgerLoading(false))

    refreshRefunds(next.id)
  }, [refreshRefunds])

  const openReversal = async (row) => {
    setTarget(row)
    setPreview(null)
    setPreviewError(null)
    setReverseReason('')
    setReverseError(null)

    try {
      const { data } = await api.get(`/refunds/${row.id}/reversal-preview`)

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
    setReversing(true)
    setReverseError(null)

    try {
      await api.post(`/refunds/${target.id}/reverse`, {
        reason: reverseReason.trim(),
        idempotency_key: newIdempotencyKey(),
      })

      setTarget(null)

      // Keep the student, refresh their refunds and the credit the
      // reversal just restored.
      if (student) {
        refreshRefunds(student.id)

        api
          .get(`/students/${student.id}/balance`)
          .then(({ data: refreshed }) => setLedger(refreshed))
          .catch(() => {})
      }
    } catch (error) {
      const fieldErrors = validationErrors(error)

      setReverseError(
        fieldErrors.reason ??
          loadErrorMessage(
            error,
            'Reversal',
            'Unable to reverse this refund. It may already have been reversed.',
          ),
      )
    } finally {
      setReversing(false)
    }
  }

  const handleSubmit = async (event) => {
    event.preventDefault()

    setSubmitting(true)
    setErrors({})
    setFormError(null)

    try {
      const { data } = await api.post('/refunds', {
        student_id: student.id,
        payment_method: paymentMethod,
        reference: reference.trim() || undefined,
        amount: Number(amount),
        reason: reason.trim(),
        idempotency_key: newIdempotencyKey(),
      })

      setRefund(data.refund)
      setPaymentMethod('CASH')
      setReference('')
      setAmount('')
      setReason('')

      // Refresh the credit the refund just drew down, and the list.
      api
        .get(`/students/${student.id}/balance`)
        .then(({ data: refreshed }) => setLedger(refreshed))
        .catch(() => {})

      refreshRefunds(student.id)
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        setFormError(
          loadErrorMessage(
            error,
            'Refund',
            'Unable to refund the credit. Please try again.',
          ),
        )
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="grid gap-6 lg:grid-cols-3">
      <Card className="lg:col-span-2">
        <CardHeader>
          <CardTitle>Refund credit</CardTitle>
          <CardDescription>
            Pay a student&apos;s held credit back out. Only the credit the ledger
            currently shows may be refunded.
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-6">
            <FormField
              id="refund-student"
              label="Student"
              required
              error={errors.student_id}
            >
              <StudentPicker
                id="refund-student"
                endpoint="/payments/students"
                value={student?.id ?? null}
                student={student}
                onChange={handleStudentChange}
              />
            </FormField>

            <div className="grid gap-6 sm:grid-cols-2">
              <FormField
                id="refund-method"
                label="Payment method"
                required
                error={errors.payment_method}
              >
                <Select
                  id="refund-method"
                  value={paymentMethod}
                  onChange={(event) => setPaymentMethod(event.target.value)}
                >
                  {PAYMENT_METHODS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </Select>
              </FormField>

              <FormField
                id="refund-reference"
                label="Reference"
                value={reference}
                onChange={(event) => setReference(event.target.value)}
                error={errors.reference}
                placeholder="Slip or transaction code"
              />
            </div>

            <FormField
              id="refund-amount"
              label="Amount"
              required
              type="number"
              min="0.01"
              step="0.01"
              max={credit > 0 ? credit : undefined}
              value={amount}
              onChange={(event) => setAmount(event.target.value)}
              error={
                errors.amount ??
                (exceedsCredit
                  ? `Cannot exceed the current credit of ${formatKes(credit)}.`
                  : undefined)
              }
              inputClassName="text-right tabular-nums"
              placeholder="0.00"
            />

            <FormField
              id="refund-reason"
              label="Reason"
              required
              multiline
              rows={2}
              value={reason}
              onChange={(event) => setReason(event.target.value)}
              error={errors.reason}
              placeholder="Why is this credit being refunded?"
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
                  !amount ||
                  !reason.trim() ||
                  exceedsCredit ||
                  credit <= 0
                }
              >
                {submitting ? 'Refunding…' : 'Refund credit'}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <BalanceCard ledger={ledger} loading={ledgerLoading} student={student} />

      {refund && (
        <Card>
          <CardHeader>
            <CardTitle>Refund recorded</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2 text-sm">
            <p>
              Refund <span className="font-medium">{refund.refund_number}</span>{' '}
              of {formatKes(refund.amount)} issued.
            </p>
            <p className="text-muted-foreground">
              {refund.student_name} now holds{' '}
              {formatKes(ledger?.credit ?? 0)} credit.
            </p>
          </CardContent>
        </Card>
      )}

      <Card className="lg:col-span-3">
        <CardHeader>
          <CardTitle>Refunds on the ledger</CardTitle>
          <CardDescription>
            Every refund for this student, newest first. A refund paid in error
            is corrected by a linked reversal row — the original is never
            edited or deleted, and the credit it paid out is restored.
          </CardDescription>
        </CardHeader>

        <CardContent>
          {refundsError && (
            <p role="alert" className="text-sm font-medium text-destructive">
              {refundsError}
            </p>
          )}

          {student && !refundsError && refunds.length === 0 && !refundsLoading && (
            <p className="py-6 text-center text-sm text-muted-foreground">
              This student has no refunds on the ledger.
            </p>
          )}

          {refunds.length > 0 && (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <th scope="col" className="pb-3 font-medium whitespace-nowrap">
                      Refund #
                    </th>
                    <th scope="col" className="pb-3 font-medium whitespace-nowrap">
                      Method
                    </th>
                    <th scope="col" className="pb-3 font-medium">Reason</th>
                    <th
                      scope="col"
                      className="pb-3 text-right font-medium whitespace-nowrap"
                    >
                      Amount
                    </th>
                    <th scope="col" className="pb-3 font-medium whitespace-nowrap">
                      Kind
                    </th>
                    <th scope="col" className="pb-3 text-right font-medium">
                      <span className="sr-only">Action</span>
                    </th>
                  </tr>
                </thead>

                <tbody className="divide-y">
                  {refunds.map((row) => {
                    // A reversal row, or a refund already reversed, is final.
                    const reversible =
                      row.entry_kind === 'REFUND' && !row.reversed_by

                    return (
                      <tr key={row.id} className="hover:bg-muted/40">
                        <td className="py-3 font-medium whitespace-nowrap">
                          {row.refund_number}
                        </td>
                        <td className="py-3 whitespace-nowrap">
                          {row.payment_method_label ?? row.payment_method}
                        </td>
                        <td className="max-w-xs truncate py-3 text-muted-foreground">
                          {row.reason ?? '—'}
                        </td>
                        <td className="py-3 text-right tabular-nums whitespace-nowrap">
                          {formatAmount(row.amount)}
                        </td>
                        <td className="py-3 whitespace-nowrap text-muted-foreground">
                          {ENTRY_KIND_LABELS[row.entry_kind] ?? row.entry_kind}
                        </td>
                        <td className="py-3 text-right whitespace-nowrap">
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={!reversible}
                            onClick={() => openReversal(row)}
                            title={
                              reversible
                                ? 'Reverse this refund'
                                : 'Reversal rows and reversed refunds cannot be reversed again'
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
      </Card>

      {target && (
        <ReversalDialog
          title={`Reverse ${target.refund_number}`}
          preview={preview}
          previewError={previewError}
          reason={reverseReason}
          onReasonChange={setReverseReason}
          submitting={reversing}
          submitError={reverseError}
          onCancel={() => setTarget(null)}
          onConfirm={confirmReversal}
        />
      )}
    </div>
  )
}

/** The derived balance beside the desk: total, paid, and what is owed. */
function BalanceCard({ ledger, loading, student }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Balance</CardTitle>
      </CardHeader>

      <CardContent>
        {!student && (
          <p className="text-sm text-muted-foreground">
            Choose a student to see their balance.
          </p>
        )}

        {student && loading && (
          <p className="text-sm text-muted-foreground">Loading…</p>
        )}

        {student && !loading && ledger && (
          <dl className="divide-y divide-border text-sm">
            <BalanceRow label="Total invoiced" value={ledger.total_invoiced} />
            <BalanceRow label="Net payments" value={ledger.net_payments} />
            <BalanceRow label="Balance" value={ledger.balance} strong />
            {ledger.arrears > 0 && (
              <BalanceRow label="In arrears" value={ledger.arrears} />
            )}
            {ledger.credit > 0 && (
              <BalanceRow label="In credit" value={ledger.credit} />
            )}
          </dl>
        )}

        {student && !loading && !ledger && (
          <p className="text-sm text-muted-foreground">No balance to show.</p>
        )}
      </CardContent>
    </Card>
  )
}

function BalanceRow({ label, value, strong = false }) {
  return (
    <div className="flex items-center justify-between gap-4 py-2">
      <dt className={strong ? 'font-medium' : 'text-muted-foreground'}>
        {label}
      </dt>
      <dd className="font-medium tabular-nums">{formatKes(value)}</dd>
    </div>
  )
}

/** How the server would settle this payment, oldest invoice first. */
function SettlementPreview({ preview }) {
  if (!preview.applied?.length && !preview.credit_after) {
    return null
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Settlement preview</CardTitle>
        <CardDescription>How this payment would apply.</CardDescription>
      </CardHeader>

      <CardContent className="space-y-3 text-sm">
        {preview.applied?.length > 0 && (
          <dl className="divide-y divide-border">
            {preview.applied.map((line) => (
              <div
                key={line.invoice_number}
                className="flex items-center justify-between gap-4 py-2"
              >
                <dt className="text-muted-foreground">{line.invoice_number}</dt>
                <dd className="font-medium tabular-nums">
                  {formatKes(line.amount)}
                </dd>
              </div>
            ))}
          </dl>
        )}

        {preview.credit_after > 0 && (
          <p className="rounded-lg bg-amber-500/10 px-3 py-2 text-amber-600 dark:text-amber-400">
            {formatKes(preview.credit_after)} would become student credit.
          </p>
        )}
      </CardContent>
    </Card>
  )
}

/** The printable receipt a successful payment leaves on screen. */
function ReceiptCard({ receipt, onDismiss }) {
  return (
    <Card className="print:border-0 print:shadow-none">
      <CardHeader className="print:px-0 print:pb-2">
        <div className="flex items-center justify-between gap-4">
          <CardTitle>Receipt {receipt.receipt_number}</CardTitle>
          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => window.print()}
            >
              <Printer aria-hidden="true" className="size-4" />
              Print
            </Button>
            <Button type="button" variant="ghost" size="sm" onClick={onDismiss}>
              Dismiss
            </Button>
          </div>
        </div>
      </CardHeader>

      <CardContent className="print:px-0">
        <dl className="divide-y divide-border text-sm print:divide-border">
          <ReceiptRow label="Student" value={receipt.student_name} />
          <ReceiptRow
            label="Admission number"
            value={receipt.student_login_id ?? '—'}
          />
          <ReceiptRow
            label="Session"
            value={receipt.academic_session_name ?? '—'}
          />
          <ReceiptRow
            label="Method"
            value={receipt.payment_method_label ?? receipt.payment_method}
          />
          <ReceiptRow
            label="Reference"
            value={receipt.reference ?? '—'}
          />
          <ReceiptRow label="Amount" value={formatKes(receipt.amount)} strong />
          <ReceiptRow label="Notes" value={receipt.notes ?? '—'} />
        </dl>
      </CardContent>
    </Card>
  )
}

function ReceiptRow({ label, value, strong = false }) {
  return (
    <div className="flex items-center justify-between gap-4 py-2">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className={strong ? 'text-right font-medium' : 'text-right'}>
        {value}
      </dd>
    </div>
  )
}
