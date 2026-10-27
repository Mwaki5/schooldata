import { Eye, Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { Button, buttonVariants } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ListPagination } from '@/components/list-pagination'
import { ListSearch } from '@/components/list-search'
import { SortableTh } from '@/components/sortable-th'
import { useList } from '@/hooks/use-list'
import api from '@/lib/api'
import {
  amountFormat,
  formatDate,
} from '@/pages/invoices/invoice-constants'
import { StatusPill } from '@/pages/invoices/status-pill'
import { useAuth } from '@/context/AuthContext'

/**
 * The billing desk's list: every invoice raised against a student,
 * newest first, with the student, session, status and total each one
 * carries. "Invoice Student" opens the form that raises the next one.
 */
export default function Invoices() {
  const { can } = useAuth()
  const [deletingId, setDeletingId] = useState(null)

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
    refresh,
  } = useList({
    resource: 'invoices',
    endpoint: '/invoices',
    errorMessage: 'Unable to load invoices. Please try again.',
  })

  const handleDelete = async (invoice) => {
    if (!window.confirm(`Delete invoice ${invoice.invoice_number}?`)) {
      return
    }

    setDeletingId(invoice.id)

    try {
      await api.delete(`/invoices/${invoice.id}`)

      refresh()
      toast.success(`Deleted invoice ${invoice.invoice_number}`)
    } catch {
      toast.error(
        `Unable to delete invoice ${invoice.invoice_number}. Please try again.`,
      )
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Invoices</h1>
          <p className="text-sm text-muted-foreground">
            Every bill raised against a student — fees, penalties, hostel
            charges and the rest, line by line.
          </p>
        </div>
        {can('invoices.create') && (
          <Link to="/invoices/create" className={buttonVariants()}>
            <Plus aria-hidden="true" />
            Invoice Student
          </Link>
        )}
      </div>

      {loadError && (
        <div
          role="alert"
          className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
        >
          {loadError}
        </div>
      )}

      <Card>
        <CardHeader className="flex flex-row items-center justify-between gap-4">
          <CardTitle>All Invoices</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search invoices…"
          />
        </CardHeader>
        <CardContent className="space-y-4">
          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : invoices.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {search
                ? `No invoices match “${search}”.`
                : 'No invoices yet. Raise the first one with “Invoice Student” above.'}
            </p>
          ) : (
            <>
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
                      <th className="pb-3 font-medium">Student</th>
                      <th className="pb-3 font-medium">Session</th>
                      <SortableTh
                        column="status"
                        label="Status"
                        sort={sort}
                        order={order}
                        onSort={toggleSort}
                      />
                      <th className="pb-3 text-right font-medium">Total</th>
                      <SortableTh
                        column="created_at"
                        label="Raised"
                        sort={sort}
                        order={order}
                        onSort={toggleSort}
                      />
                      <th className="pb-3 text-right font-medium">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {invoices.map((invoice) => (
                      <tr key={invoice.id}>
                        <td className="py-3 font-medium tabular-nums">
                          {invoice.invoice_number}
                        </td>
                        <td className="py-3">
                          <p>{invoice.student_name}</p>
                          {invoice.student_login_id && (
                            <p className="text-xs text-muted-foreground">
                              {invoice.student_login_id}
                            </p>
                          )}
                        </td>
                        <td className="py-3 text-muted-foreground">
                          {invoice.academic_session_name ?? '—'}
                        </td>
                        <td className="py-3">
                          <StatusPill status={invoice.status} />
                        </td>
                        <td className="py-3 text-right tabular-nums">
                          {amountFormat.format(invoice.total ?? 0)}
                        </td>
                        <td className="py-3 text-muted-foreground">
                          {formatDate(invoice.created_at)}
                        </td>
                        <td className="py-3">
                          <div className="flex items-center justify-end gap-2">
                            <Link
                              to={`/invoices/${invoice.id}`}
                              className={buttonVariants({
                                variant: 'ghost',
                                size: 'sm',
                              })}
                            >
                              <Eye aria-hidden="true" />
                              View
                            </Link>
                            {can('invoices.update') && (
                              <Link
                                to={`/invoices/${invoice.id}/edit`}
                                className={buttonVariants({
                                  variant: 'ghost',
                                  size: 'sm',
                                })}
                              >
                                <Pencil aria-hidden="true" />
                                Edit
                              </Link>
                            )}
                            {can('invoices.delete') && (
                              <Button
                                variant="destructive"
                                size="sm"
                                disabled={deletingId === invoice.id}
                                onClick={() => handleDelete(invoice)}
                              >
                                <Trash2 aria-hidden="true" />
                                {deletingId === invoice.id
                                  ? 'Deleting…'
                                  : 'Delete'}
                              </Button>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <ListPagination
                meta={meta}
                loading={loading}
                onPageChange={goToPage}
                label="invoices"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
