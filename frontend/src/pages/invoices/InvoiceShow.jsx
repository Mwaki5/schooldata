import { ArrowLeft, Pencil, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import PageLoader from '@/components/page-loader'
import { FormField } from '@/components/form-field'
import { Button, buttonVariants } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import api, { isNotFound } from '@/lib/api'
import {
  LINE_TYPES,
  amountFormat,
  formatDate,
} from '@/pages/invoices/invoice-constants'
import { StatusPill } from '@/pages/invoices/status-pill'
import { useAuth } from '@/context/AuthContext'

function lineTypeLabel(type) {
  return LINE_TYPES.find((row) => row.value === type)?.label ?? type
}

/**
 * One invoice in full: its number and status over the billed student,
 * session and date, and the lines that add up to its total — with the
 * edit and delete actions the finance desk holds.
 */
export default function InvoiceShow() {
  const { invoiceId } = useParams()
  const { can } = useAuth()
  const navigate = useNavigate()

  const [invoice, setInvoice] = useState(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [deleting, setDeleting] = useState(false)

  useEffect(() => {
    let active = true

    api
      .get(`/invoices/${invoiceId}`)
      .then(({ data }) => {
        if (active) {
          setInvoice(data.invoice)
        }
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
  }, [invoiceId])

  const handleDelete = async () => {
    if (!window.confirm(`Delete invoice ${invoice.invoice_number}?`)) {
      return
    }

    setDeleting(true)

    try {
      await api.delete(`/invoices/${invoice.id}`)

      toast.success(`Deleted invoice ${invoice.invoice_number}`)
      navigate('/invoices')
    } catch (error) {
      setDeleting(false)
      toast.error(
        error.response?.data?.message ??
          `Unable to delete invoice ${invoice.invoice_number}. Please try again.`,
      )
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

      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <h1 className="text-2xl font-semibold tracking-tight tabular-nums">
            {invoice.invoice_number}
          </h1>
          <StatusPill status={invoice.status} />
        </div>
        <div className="flex items-center gap-2">
          {can('invoices.update') && (
            <Link
              to={`/invoices/${invoice.id}/edit`}
              className={buttonVariants({ variant: 'outline', size: 'sm' })}
            >
              <Pencil aria-hidden="true" />
              Edit
            </Link>
          )}
          {can('invoices.delete') && (
            <Button
              variant="destructive"
              size="sm"
              disabled={deleting}
              onClick={handleDelete}
            >
              <Trash2 aria-hidden="true" />
              {deleting ? 'Deleting…' : 'Delete'}
            </Button>
          )}
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Details</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-5 sm:grid-cols-3">
          <FormField id="show-student" label="Student">
            <div>
              <p className="text-sm font-medium">{invoice.student_name}</p>
              {invoice.student_login_id && (
                <p className="text-xs text-muted-foreground">
                  {invoice.student_login_id}
                </p>
              )}
            </div>
          </FormField>
          <FormField id="show-session" label="Academic Session">
            <p className="text-sm">{invoice.academic_session_name ?? '—'}</p>
          </FormField>
          <FormField id="show-raised" label="Raised">
            <p className="text-sm">{formatDate(invoice.created_at)}</p>
          </FormField>
        </CardContent>
      </Card>

      {invoice.description && (
        <p className="text-sm text-muted-foreground">{invoice.description}</p>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Line Items</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b text-left text-muted-foreground">
                  <th className="pb-3 font-medium">Type</th>
                  <th className="pb-3 font-medium">Description</th>
                  <th className="pb-3 text-right font-medium">Amount</th>
                </tr>
              </thead>
              <tbody className="divide-y">
                {invoice.items.map((item) => (
                  <tr key={item.id}>
                    <td className="py-3">
                      <span className="inline-flex rounded-full bg-muted px-2 py-0.5 text-xs font-medium">
                        {lineTypeLabel(item.type)}
                      </span>
                    </td>
                    <td className="py-3">{item.description}</td>
                    <td className="py-3 text-right tabular-nums">
                      {amountFormat.format(item.amount ?? 0)}
                    </td>
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr className="border-t font-medium">
                  <td className="pt-3" colSpan={2}>
                    Total
                  </td>
                  <td className="pt-3 text-right tabular-nums">
                    {amountFormat.format(invoice.total ?? 0)}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
