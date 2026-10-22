import { ListChecks, Pencil, Plus, Trash2 } from 'lucide-react'
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
import { cn } from '@/lib/utils'

function StatusBadge({ active }) {
  return (
    <span
      className={cn(
        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
        active
          ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
          : 'bg-muted text-muted-foreground',
      )}
    >
      {active ? 'Active' : 'Inactive'}
    </span>
  )
}

const amountFormat = new Intl.NumberFormat('en-US', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

export default function FeeStructures() {
  const [deletingId, setDeletingId] = useState(null)

  const {
    items: feeStructures,
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
    resource: 'fee_structures',
    endpoint: '/fee-structures',
    errorMessage: 'Unable to load fee structures. Please try again.',
  })

  const handleDelete = async (feeStructure) => {
    if (!window.confirm(`Delete the "${feeStructure.name}" fee structure?`)) {
      return
    }

    setDeletingId(feeStructure.id)

    try {
      await api.delete(`/fee-structures/${feeStructure.id}`)

      refresh()
      toast.success(`Deleted "${feeStructure.name}"`)
    } catch {
      toast.error(
        `Unable to delete "${feeStructure.name}". Please try again.`,
      )
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">
            Fee Structures
          </h1>
          <p className="text-sm text-muted-foreground">
            The named fee plans the school charges against. Retire a plan with
            its status instead of deleting it once invoices reference it.
          </p>
        </div>
        <Link to="/fee-structures/create" className={buttonVariants()}>
          <Plus aria-hidden="true" />
          New Fee Structure
        </Link>
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
          <CardTitle>All Fee Structures</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search fee structures…"
          />
        </CardHeader>
        <CardContent className="space-y-4">
          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : feeStructures.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {search
                ? `No fee structures match “${search}”.`
                : 'No fee structures yet. Create the first one with the button above.'}
            </p>
          ) : (
            <>
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <SortableTh
                      column="name"
                      label="Name"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <SortableTh
                      column="is_active"
                      label="Status"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <th className="pb-3 text-right font-medium">Total</th>
                    <th className="pb-3 text-right font-medium">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {feeStructures.map((feeStructure) => (
                    <tr key={feeStructure.id}>
                      <td className="py-3 font-medium">{feeStructure.name}</td>
                      <td className="py-3">
                        <StatusBadge active={feeStructure.is_active} />
                      </td>
                      <td className="py-3 text-right tabular-nums">
                        {amountFormat.format(feeStructure.total ?? 0)}
                      </td>
                      <td className="py-3">
                        <div className="flex items-center justify-end gap-2">
                          <Link
                            to={`/fee-structures/${feeStructure.id}/items`}
                            className={buttonVariants({
                              variant: 'ghost',
                              size: 'sm',
                            })}
                          >
                            <ListChecks aria-hidden="true" />
                            Items
                          </Link>
                          <Link
                            to={`/fee-structures/${feeStructure.id}/edit`}
                            className={buttonVariants({
                              variant: 'ghost',
                              size: 'sm',
                            })}
                          >
                            <Pencil aria-hidden="true" />
                            Edit
                          </Link>
                          <Button
                            variant="destructive"
                            size="sm"
                            disabled={deletingId === feeStructure.id}
                            onClick={() => handleDelete(feeStructure)}
                          >
                            <Trash2 aria-hidden="true" />
                            {deletingId === feeStructure.id
                              ? 'Deleting…'
                              : 'Delete'}
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>

              <ListPagination
                meta={meta}
                loading={loading}
                onPageChange={goToPage}
                label="fee structures"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
