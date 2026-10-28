import { AlertTriangle } from 'lucide-react'
import { FormField } from '@/components/form-field'
import { Button } from '@/components/ui/button'
import { formatKes } from '@/lib/finance-constants'

/**
 * The confirmation behind every "Reverse" action on the finance desk.
 *
 * A reversal is never an edit and never a delete — it writes a new
 * linked row with a negative amount, so the original stays on the
 * ledger forever. Because that write cannot be undone by another edit,
 * this dialog makes the operator read the effect before it lands: the
 * balance shift, any credit it creates, and the invoices it re-opens.
 *
 * The effect preview is fetched by the caller from the backend's own
 * derived ledger (`/reversal-preview`) — nothing here is computed on
 * the client, and nothing is persisted until the confirm is pressed.
 *
 * @param {object} props
 * @param {string} props.title Dialog heading, naming the document.
 * @param {object|null} props.preview Derived effect from the backend.
 * @param {string|null} props.previewError Failed to load the preview.
 * @param {string} props.reason Mandatory reversal reason.
 * @param {(reason: string) => void} props.onReasonChange
 * @param {boolean} props.submitting Disables the buttons mid-flight.
 * @param {string|null} props.submitError Server refusal, shown inline.
 * @param {() => void} props.onCancel
 * @param {() => void} props.onConfirm
 */
export function ReversalDialog({
  title,
  preview,
  previewError,
  reason,
  onReasonChange,
  submitting,
  submitError,
  onCancel,
  onConfirm,
}) {
  const reasonTooShort = reason.trim().length > 0 && reason.trim().length < 5
  const canConfirm = reason.trim().length >= 5 && !submitting && !previewError

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label={title}
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    >
      <div className="w-full max-w-lg rounded-xl border bg-background p-6 shadow-lg">
        <h2 className="text-lg font-semibold">{title}</h2>
        <p className="mt-1 text-sm text-muted-foreground">
          This writes a new reversing row against the ledger. The original
          document is never edited or deleted, and the reversal itself
          cannot be reversed.
        </p>

        <div className="mt-4 space-y-3">
          {previewError && (
            <p role="alert" className="text-sm font-medium text-destructive">
              {previewError}
            </p>
          )}

          {!previewError && !preview && (
            <p className="text-sm text-muted-foreground">Loading effect…</p>
          )}

          {preview && (
            <>
              <dl className="divide-y divide-border rounded-lg border text-sm">
                <EffectRow
                  label="Amount being reversed"
                  value={formatKes(preview.amount)}
                />
                <EffectRow
                  label="Balance before"
                  value={formatKes(preview.balance_before)}
                />
                <EffectRow
                  label="Balance after"
                  value={formatKes(preview.balance_after)}
                />
                {typeof preview.credit_after === 'number' && (
                  <EffectRow
                    label="Student credit after"
                    value={formatKes(preview.credit_after)}
                  />
                )}
              </dl>

              {typeof preview.credit_after === 'number' &&
                preview.credit_after > 0 && (
                  <p className="flex items-start gap-2 rounded-lg bg-amber-500/10 px-3 py-2 text-sm text-amber-600 dark:text-amber-400">
                    <AlertTriangle
                      aria-hidden="true"
                      className="mt-0.5 size-4 shrink-0"
                    />
                    <span>
                      {formatKes(preview.credit_after)} will become student
                      credit.
                    </span>
                  </p>
                )}

              {preview.reopened_invoices?.length > 0 && (
                <div className="rounded-lg bg-muted px-3 py-2 text-sm">
                  <p className="font-medium">Invoices that re-open:</p>
                  <ul className="mt-1 list-inside list-disc text-muted-foreground">
                    {preview.reopened_invoices.map((invoiceNumber) => (
                      <li key={invoiceNumber}>{invoiceNumber}</li>
                    ))}
                  </ul>
                </div>
              )}
            </>
          )}
        </div>

        <FormField
          id="reversal-reason"
          label="Reason"
          required
          multiline
          rows={3}
          value={reason}
          onChange={(event) => onReasonChange(event.target.value)}
          error={reasonTooShort ? 'Give a reason of at least 5 characters.' : undefined}
          placeholder="Why is this being reversed?"
          className="mt-4"
        />

        {submitError && (
          <p role="alert" className="mt-3 text-sm font-medium text-destructive">
            {submitError}
          </p>
        )}

        <div className="mt-6 flex justify-end gap-3">
          <Button
            type="button"
            variant="outline"
            onClick={onCancel}
            disabled={submitting}
          >
            Cancel
          </Button>
          <Button
            type="button"
            variant="destructive"
            onClick={onConfirm}
            disabled={!canConfirm}
          >
            {submitting ? 'Reversing…' : 'Confirm reversal'}
          </Button>
        </div>
      </div>
    </div>
  )
}

function EffectRow({ label, value }) {
  return (
    <div className="flex items-center justify-between gap-4 px-3 py-2">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="font-medium tabular-nums">{value}</dd>
    </div>
  )
}
