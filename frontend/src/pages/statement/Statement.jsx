import { Download, Printer } from 'lucide-react'
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
import { StudentPicker } from '@/components/finance/student-picker'
import api, { loadErrorMessage } from '@/lib/api'
import {
  INVOICE_TYPE_LABELS,
  formatAmount,
  formatKes,
  studentLabel,
} from '@/lib/finance-constants'
import { StatusPill } from '@/pages/invoices/status-pill'

const ENTRY_TYPE_LABELS = {
  invoice: 'Invoice',
  invoice_reversal: 'Invoice reversal',
  payment: 'Payment',
  payment_reversal: 'Payment reversal',
  refund: 'Refund',
}

/**
 * A student's statement of account: the chronological ledger with a
 * running balance, the closing figure, and each invoice's live status.
 *
 * Everything here is read-only and derived server-side by the same
 * StudentLedgerService the desks write through, so the closing balance
 * reconciles exactly with the arrears or credit shown. The ledger can
 * be exported as CSV or printed (PDF through the print dialog) — it
 * can never be edited from this screen.
 */
export default function Statement() {
  const [student, setStudent] = useState(null)
  const [statement, setStatement] = useState(null)
  const [loading, setLoading] = useState(false)
  const [loadError, setLoadError] = useState(null)

  const handleStudentChange = useCallback((next) => {
    setStudent(next)
    setStatement(null)
    setLoadError(null)

    if (!next) {
      return
    }

    setLoading(true)

    api
      .get(`/students/${next.id}/statement`)
      .then(({ data }) => setStatement(data))
      .catch((error) => {
        setStatement(null)
        setLoadError(
          loadErrorMessage(
            error,
            'Statement',
            'Unable to load this student’s statement.',
          ),
        )
      })
      .finally(() => setLoading(false))
  }, [])

  const handleExportCsv = () => {
    if (!statement) {
      return
    }

    const rows = [
      [
        'Date',
        'Type',
        'Document',
        'Description',
        'Debit',
        'Credit',
        'Running balance',
      ],
      ...statement.entries.map((entry) => [
        entry.date,
        ENTRY_TYPE_LABELS[entry.type] ?? entry.type,
        entry.document_number,
        entry.description,
        entry.debit,
        entry.credit,
        entry.running_balance,
      ]),
    ]

    // Escape any field containing a comma, quote or newline.
    const csv = rows
      .map((row) =>
        row
          .map((field) => {
            const value = String(field ?? '')

            return /[",\n]/.test(value)
              ? `"${value.replaceAll('"', '""')}"`
              : value
          })
          .join(','),
      )
      .join('\n')

    // The BOM keeps Excel reading the UTF-8 accents correctly.
    const blob = new Blob([`\uFEFF${csv}`], {
      type: 'text/csv;charset=utf-8;',
    })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')

    link.href = url
    link.download = `statement-${statement.student.login_id ?? statement.student.id}.csv`
    link.click()

    URL.revokeObjectURL(url)
  }

  // Active and reversed invoices in one chronological table.
  const invoices = [
    ...(statement?.invoices ?? []),
    ...(statement?.reversed_invoices ?? []),
  ].sort((left, right) => String(right.created_at).localeCompare(String(left.created_at)))

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div className="no-print">
        <h1 className="text-2xl font-semibold">Statement</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          A read-only account of every invoice, payment and refund, reconciled to
          the cent.
        </p>
      </div>

      <div className="no-print">
        <FormField id="statement-student" label="Student" required>
          <StudentPicker
            id="statement-student"
            endpoint="/payments/students"
            value={student?.id ?? null}
            student={student}
            onChange={handleStudentChange}
          />
        </FormField>
      </div>

      {loadError && (
        <p role="alert" className="no-print text-sm font-medium text-destructive">
          {loadError}
        </p>
      )}

      {statement && (
        <>
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h2 className="text-lg font-semibold">
                {studentLabel(statement.student)}
              </h2>
              <p className="text-sm text-muted-foreground">
                Statement of account
              </p>
            </div>

            <div className="no-print flex items-center gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={handleExportCsv}
              >
                <Download aria-hidden="true" className="size-4" />
                Export CSV
              </Button>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => window.print()}
              >
                <Printer aria-hidden="true" className="size-4" />
                Print / PDF
              </Button>
            </div>
          </div>

          <Card>
            <CardHeader className="no-print">
              <CardTitle>Summary</CardTitle>
            </CardHeader>

            <CardContent>
              <dl className="grid grid-cols-2 gap-x-8 gap-y-4 text-sm sm:grid-cols-4">
                <SummaryCell
                  label="Total invoiced"
                  value={formatKes(statement.total_invoiced)}
                />
                <SummaryCell
                  label="Net payments"
                  value={formatKes(statement.net_payments)}
                />
                <SummaryCell
                  label="Closing balance"
                  value={formatKes(statement.closing_balance)}
                  strong
                />
                <SummaryCell
                  label={statement.credit > 0 ? 'In credit' : 'In arrears'}
                  value={formatKes(
                    statement.credit > 0 ? statement.credit : statement.arrears,
                  )}
                  strong
                />
              </dl>
            </CardContent>
          </Card>

          {invoices.length > 0 && (
            <Card>
              <CardHeader className="no-print">
                <CardTitle>Invoices</CardTitle>
                <CardDescription>
                  Each invoice&apos;s live status, derived from the ledger.
                </CardDescription>
              </CardHeader>

              <CardContent>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b text-left text-muted-foreground">
                        <th
                          scope="col"
                          className="pb-3 font-medium whitespace-nowrap"
                        >
                          Invoice #
                        </th>
                        <th
                          scope="col"
                          className="pb-3 font-medium whitespace-nowrap"
                        >
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
                          Paid
                        </th>
                        <th
                          scope="col"
                          className="pb-3 text-right font-medium whitespace-nowrap"
                        >
                          Outstanding
                        </th>
                        <th
                          scope="col"
                          className="pb-3 font-medium whitespace-nowrap"
                        >
                          Status
                        </th>
                      </tr>
                    </thead>

                    <tbody className="divide-y">
                      {invoices.map((invoice) => (
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
                            {formatAmount(invoice.paid ?? 0)}
                          </td>
                          <td className="py-3 text-right tabular-nums whitespace-nowrap">
                            {formatAmount(invoice.outstanding ?? 0)}
                          </td>
                          <td className="py-3 whitespace-nowrap">
                            <StatusPill status={invoice.status} />
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </CardContent>
            </Card>
          )}

          <Card>
            <CardHeader className="no-print">
              <CardTitle>Ledger</CardTitle>
              <CardDescription>
                Every row in date order, with the balance as it stood after each.
              </CardDescription>
            </CardHeader>

            <CardContent>
              {statement.entries.length === 0 ? (
                <p className="py-6 text-center text-sm text-muted-foreground">
                  This student has no ledger activity yet.
                </p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b text-left text-muted-foreground">
                        <th
                          scope="col"
                          className="pb-3 font-medium whitespace-nowrap"
                        >
                          Date
                        </th>
                        <th
                          scope="col"
                          className="pb-3 font-medium whitespace-nowrap"
                        >
                          Type
                        </th>
                        <th
                          scope="col"
                          className="pb-3 font-medium whitespace-nowrap"
                        >
                          Document
                        </th>
                        <th scope="col" className="pb-3 font-medium">
                          Description
                        </th>
                        <th
                          scope="col"
                          className="pb-3 text-right font-medium whitespace-nowrap"
                        >
                          Debit
                        </th>
                        <th
                          scope="col"
                          className="pb-3 text-right font-medium whitespace-nowrap"
                        >
                          Credit
                        </th>
                        <th
                          scope="col"
                          className="pb-3 text-right font-medium whitespace-nowrap"
                        >
                          Balance
                        </th>
                      </tr>
                    </thead>

                    <tbody className="divide-y">
                      {statement.entries.map((entry, index) => (
                        <tr key={`${entry.document_number}-${index}`}>
                          <td className="py-2.5 whitespace-nowrap">
                            {entry.date}
                          </td>
                          <td className="py-2.5 whitespace-nowrap text-muted-foreground">
                            {ENTRY_TYPE_LABELS[entry.type] ?? entry.type}
                          </td>
                          <td className="py-2.5 font-medium whitespace-nowrap">
                            {entry.document_number}
                          </td>
                          <td className="max-w-xs truncate py-2.5 text-muted-foreground">
                            {entry.description}
                          </td>
                          <td className="py-2.5 text-right tabular-nums whitespace-nowrap">
                            {entry.debit > 0 ? formatAmount(entry.debit) : '—'}
                          </td>
                          <td className="py-2.5 text-right tabular-nums whitespace-nowrap">
                            {entry.credit > 0 ? formatAmount(entry.credit) : '—'}
                          </td>
                          <td className="py-2.5 text-right font-medium tabular-nums whitespace-nowrap">
                            {formatAmount(entry.running_balance)}
                          </td>
                        </tr>
                      ))}
                    </tbody>

                    <tfoot>
                      <tr className="border-t font-medium">
                        <td
                          colSpan={6}
                          className="py-3 text-right text-muted-foreground"
                        >
                          Closing balance
                        </td>
                        <td className="py-3 text-right tabular-nums">
                          {formatAmount(statement.closing_balance)}
                        </td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              )}
            </CardContent>
          </Card>
        </>
      )}

      {student && loading && (
        <p className="no-print py-6 text-center text-sm text-muted-foreground">
          Loading statement…
        </p>
      )}

      {student && !loading && !statement && !loadError && (
        <p className="no-print py-6 text-center text-sm text-muted-foreground">
          No statement to show.
        </p>
      )}
    </div>
  )
}

function SummaryCell({ label, value, strong = false }) {
  return (
    <div>
      <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
        {label}
      </dt>
      <dd
        className={
          strong ? 'mt-1 font-semibold tabular-nums' : 'mt-1 font-medium tabular-nums'
        }
      >
        {value}
      </dd>
    </div>
  )
}
