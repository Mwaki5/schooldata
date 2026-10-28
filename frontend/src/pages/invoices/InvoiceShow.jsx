import { ArrowLeft, Link2, Undo2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import PageLoader from '@/components/page-loader'
import { buttonVariants } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import api, { loadErrorMessage } from '@/lib/api'
import {
  ENTRY_KIND_LABELS,
  INVOICE_TYPE_LABELS,
  formatAmount,
  formatDateTime,
  studentLabel,
} from '@/lib/finance-constants'
import { StatusPill } from '@/pages/invoices/status-pill'
import { useAuth } from '@/context/AuthContext'

/**
 * One invoice in full, read-only.
 *
 * Every figure is derived by the backend's ledger; this screen only
 * renders them. There is no edit and no delete — a mistake is
 * corrected on the "Reverse Invoice" tab, and when this invoice has
 * been reversed (or is itself a reversal) the linked document is shown
 * here so the trail stays visible.
 */
export default function InvoiceShow() {
  const { invoiceId } = useParams()
  const { can } = useAuth()
  const [invoice, setInvoice] = useState(null)
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let active = true

    api
      .get(`/invoices/${invoiceId}`)
      .then(({ data }) => {
        if (active) {
          setInvoice(data.invoice)
          setError(null)
        }
      })
      .catch((caught) => {
        if (active) {
          setError(
            loadErrorMessage(
              caught,
              'Invoice',
              'Unable to load this invoice. Please try again.',
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
  }, [invoiceId])

  if (loading) {
    return <PageLoader />
  }

  if (error) {
    return (
      <div className="mx-auto max-w-3xl space-y-6">
        <Link
          to="/invoices"
          className={`${buttonVariants({ variant: 'outline', size: 'sm' })} w-fit`}
        >
          <ArrowLeft aria-hidden="true" className="size-4" />
          Back to ledger
        </Link>
        <p role="alert" className="py-12 text-center text-sm text-destructive">
          {error}
        </p>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <Link
          to="/invoices"
          className={`${buttonVariants({ variant: 'outline', size: 'sm' })} w-fit`}
        >
          <ArrowLeft aria-hidden="true" className="size-4" />
          Back to ledger
        </Link>

        {can('invoices.reverse') &&
          invoice.entry_kind === 'INVOICE' &&
          invoice.status !== 'reversed' && (
            <Link
              to="/invoices/create"
              className={buttonVariants({ variant: 'outline', size: 'sm' })}
            >
              <Undo2 aria-hidden="true" className="size-4" />
              Reverse this invoice
            </Link>
          )}
      </div>

      <Card>
        <CardHeader>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <CardTitle className="flex items-center gap-3">
              {invoice.invoice_number}
              <StatusPill status={invoice.status} />
            </CardTitle>
            <span className="text-sm text-muted-foreground">
              {ENTRY_KIND_LABELS[invoice.entry_kind] ?? invoice.entry_kind}
            </span>
          </div>
        </CardHeader>

        <CardContent className="space-y-6">
          <dl className="grid grid-cols-1 gap-x-8 gap-y-4 text-sm sm:grid-cols-2">
            <Detail label="Student" value={studentLabel({
              name: invoice.student_name,
              login_id: invoice.student_login_id,
            })} />
            <Detail
              label="Academic session"
              value={invoice.academic_session_name ?? '—'}
            />
            <Detail
              label="Type"
              value={
                INVOICE_TYPE_LABELS[invoice.invoice_type] ?? invoice.invoice_type
              }
            />
            <Detail
              label="Fee structure"
              value={invoice.fee_structure_name ?? '—'}
            />
            <Detail
              label="Amount"
              value={formatAmount(invoice.amount)}
              numeric
            />
            <Detail label="Paid" value={formatAmount(invoice.paid)} numeric />
            <Detail
              label="Outstanding"
              value={formatAmount(invoice.outstanding)}
              numeric
            />
            <Detail
              label="Raised"
              value={formatDateTime(invoice.created_at)}
            />
          </dl>

          {invoice.description && (
            <div>
              <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                Description
              </h3>
              <p className="mt-1 text-sm">{invoice.description}</p>
            </div>
          )}

          {invoice.reversal_reason && (
            <div className="rounded-lg bg-muted px-3 py-2 text-sm">
              <p className="flex items-center gap-2 font-medium">
                <Link2 aria-hidden="true" className="size-4" />
                {invoice.entry_kind === 'REVERSAL'
                  ? 'This invoice is a reversal'
                  : 'This invoice has been reversed'}
              </p>
              <p className="mt-1 text-muted-foreground">
                Reason: {invoice.reversal_reason}
              </p>
            </div>
          )}

          {invoice.reverses && (
            <p className="text-sm text-muted-foreground">
              Reverses{' '}
              <Link
                to={`/invoices/${invoice.reverses.id}`}
                className="font-medium text-primary underline-offset-4 hover:underline"
              >
                {invoice.reverses.invoice_number}
              </Link>{' '}
              ({formatAmount(invoice.reverses.amount)}).
            </p>
          )}

          {invoice.reversed_by && (
            <p className="text-sm text-muted-foreground">
              Reversed by{' '}
              <Link
                to={`/invoices/${invoice.reversed_by.id}`}
                className="font-medium text-primary underline-offset-4 hover:underline"
              >
                {invoice.reversed_by.invoice_number}
              </Link>{' '}
              on {formatDateTime(invoice.reversed_by.created_at)}.
            </p>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

function Detail({ label, value, numeric = false }) {
  return (
    <div>
      <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
        {label}
      </dt>
      <dd
        className={
          numeric ? 'mt-1 font-medium tabular-nums' : 'mt-1 font-medium'
        }
      >
        {value}
      </dd>
    </div>
  )
}
