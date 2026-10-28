import { Eye, Plus } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { buttonVariants } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ListPagination } from '@/components/list-pagination'
import { ListSearch } from '@/components/list-search'
import { SortableTh } from '@/components/sortable-th'
import { Select } from '@/components/ui/select'
import { useList } from '@/hooks/use-list'
import {
  INVOICE_STATUS_FILTERS,
  INVOICE_TYPE_LABELS,
  formatAmount,
  formatDateTime,
} from '@/lib/finance-constants'
import { StatusPill } from '@/pages/invoices/status-pill'
import { useAuth } from '@/context/AuthContext'

/**
 * The billing desk's read-only ledger: every invoice raised against a
 * student, newest first, each showing what it charged, what the
 * derived ledger says has been paid, and what is still outstanding.
 *
 * There is deliberately no edit and no delete here — the ledger is
 * write-once, and a wrong invoice is corrected on the "Reverse Invoice"
 * tab, which writes a linked negative row.
 */
export default function Invoices() {
  const { can } = useAuth()
  const [status, setStatus] = useState('')

  const {
    items: invoices,
    meta,
    loading,
    loadError,
    search,
    setSearch,
    goToPage,
    sort,
    order,
    toggleSort,
  } = useList({
    resource: 'invoices',
    endpoint: '/invoices',
    filters: { status },
    errorMessage: 'Unable to load invoices. Please try again.',
  })

  return (
    <div className="mx-auto max-w-7xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold">Invoices</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            The immutable billing ledger. Corrections are made by reversal,
            never by edit.
          </p>
        </div>

        {can('invoices.create') && (
          <Link
            to="/invoices/create"
            className={buttonVariants({ variant: 'default' })}
          >
            <Plus aria-hidden="true" className="size-4" />
            New invoice
          </Link>
        )}
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Ledger</CardTitle>
          <div className="flex flex-wrap items-center gap-3">
            <ListSearch
              value={search}
              onChange={setSearch}
              placeholder="Search number or student…"
            />
            <Select
              aria-label="Filter by status"
              value={status}
              onChange={(event) => setStatus(event.target.value)}
              className="h-9 w-full sm:w-48"
            >
              {INVOICE_STATUS_FILTERS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </Select>
          </div>
        </CardHeader>

        <CardContent>
          {loadError && (
            <p role="alert" className="py-8 text-center text-sm text-destructive">
              {loadError}
            </p>
          )}

          {!loadError && invoices.length === 0 && !loading && (
            <p className="py-8 text-center text-sm text-muted-foreground">
              {search || status
                ? 'No invoices match this filter.'
                : 'No invoices have been raised yet.'}
            </p>
          )}

          {invoices.length > 0 && (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <SortableTh
                      column="invoice_number"
                      label="Invoice #"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <th scope="col" className="pb-3 font-medium whitespace-nowrap">
                      Student
                    </th>
                    <th scope="col" className="pb-3 font-medium whitespace-nowrap">
                      Session
                    </th>
                    <SortableTh
                      column="invoice_type"
                      label="Type"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <th scope="col" className="pb-3 font-medium">
                      Description
                    </th>
                    <SortableTh
                      column="amount"
                      label="Amount"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                      className="text-right"
                    />
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
                    <th scope="col" className="pb-3 font-medium whitespace-nowrap">
                      Status
                    </th>
                    <SortableTh
                      column="created_at"
                      label="Raised"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <th scope="col" className="pb-3 text-right font-medium">
                      <span className="sr-only">Actions</span>
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
                        <div>{invoice.student_name}</div>
                        {invoice.student_login_id && (
                          <div className="text-xs text-muted-foreground">
                            {invoice.student_login_id}
                          </div>
                        )}
                      </td>
                      <td className="py-3 whitespace-nowrap text-muted-foreground">
                        {invoice.academic_session_name ?? '—'}
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
                        {formatAmount(invoice.paid)}
                      </td>
                      <td className="py-3 text-right tabular-nums whitespace-nowrap">
                        {formatAmount(invoice.outstanding)}
                      </td>
                      <td className="py-3 whitespace-nowrap">
                        <StatusPill status={invoice.status} />
                      </td>
                      <td className="py-3 whitespace-nowrap text-muted-foreground">
                        {formatDateTime(invoice.created_at)}
                      </td>
                      <td className="py-3 text-right whitespace-nowrap">
                        <Link
                          to={`/invoices/${invoice.id}`}
                          className={`${buttonVariants({ variant: 'ghost', size: 'sm' })} size-8 p-0`}
                          aria-label={`View invoice ${invoice.invoice_number}`}
                          title="View"
                        >
                          <Eye aria-hidden="true" className="size-4" />
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          <ListPagination
            meta={meta}
            loading={loading}
            onPageChange={goToPage}
            label="invoices"
          />
        </CardContent>
      </Card>
    </div>
  )
}
