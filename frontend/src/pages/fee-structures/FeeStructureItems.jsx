import { ArrowLeft, Pencil, Trash2, X } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import PageLoader from '@/components/page-loader'
import { FormField } from '@/components/form-field'
import { Button, buttonVariants } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ListPagination } from '@/components/list-pagination'
import { ListSearch } from '@/components/list-search'
import { useList } from '@/hooks/use-list'
import api, { loadErrorMessage, validationErrors } from '@/lib/api'

const amountFormat = new Intl.NumberFormat('en-US', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

/**
 * The named charges of one fee structure: added, repriced and removed
 * inline, with the live total they add up to. The total rides along with
 * every items response, so it always reflects what is on screen.
 */
export default function FeeStructureItems() {
  const { feeStructureId } = useParams()

  const [structure, setStructure] = useState(null)
  const [loadError, setLoadError] = useState(null)
  const [editingItem, setEditingItem] = useState(null)
  const [formKey, setFormKey] = useState(0)
  const [deletingId, setDeletingId] = useState(null)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const [total, setTotal] = useState(0)

  // The structure header loads once; failures are shown instead of the
  // page because the items cannot be identified without it.
  useEffect(() => {
    let active = true

    api
      .get(`/fee-structures/${feeStructureId}`)
      .then(({ data }) => {
        if (active) {
          setStructure(data.fee_structure)
        }
      })
      .catch((error) => {
        if (active) {
          setLoadError(
            loadErrorMessage(
              error,
              'Fee structure',
              'Unable to load the fee structure. It may have been deleted.',
            ),
          )
        }
      })

    return () => {
      active = false
    }
  }, [feeStructureId])

  const {
    items,
    meta,
    loading,
    loadError: itemsError,
    search,
    setSearch,
    goToPage,
    refresh,
  } = useList({
    resource: 'items',
    endpoint: `/fee-structures/${feeStructureId}/items`,
    errorMessage: 'Unable to load fee structure items. Please try again.',
    onData: (data) => setTotal(data.total ?? 0),
  })

  const startEdit = (item) => {
    setEditingItem(item)
    setErrors({})
  }

  const cancelEdit = () => {
    setEditingItem(null)
    setErrors({})
  }

  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setSubmitting(true)

    const form = new FormData(event.currentTarget)
    const payload = {
      name: form.get('name')?.trim(),
      amount: form.get('amount'),
    }

    try {
      if (editingItem) {
        await api.put(
          `/fee-structures/${feeStructureId}/items/${editingItem.id}`,
          payload,
        )
        toast.success(`Updated "${payload.name}"`)
        setEditingItem(null)
      } else {
        await api.post(`/fee-structures/${feeStructureId}/items`, payload)
        toast.success(`Added "${payload.name}"`)
        setFormKey((key) => key + 1)
      }

      refresh()
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        toast.error(
          error.response?.data?.message ?? 'Unable to save the item.',
        )
      }
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async (item) => {
    if (!window.confirm(`Delete the "${item.name}" item? Its amount leaves the total.`)) {
      return
    }

    setDeletingId(item.id)

    try {
      await api.delete(`/fee-structures/${feeStructureId}/items/${item.id}`)

      if (editingItem?.id === item.id) {
        setEditingItem(null)
      }

      refresh()
      toast.success(`Deleted "${item.name}"`)
    } catch {
      toast.error(`Unable to delete "${item.name}". Please try again.`)
    } finally {
      setDeletingId(null)
    }
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
          to="/fee-structures"
          className={buttonVariants({ variant: 'outline' })}
        >
          <ArrowLeft aria-hidden="true" />
          Back to fee structures
        </Link>
      </div>
    )
  }

  if (!structure) {
    return <PageLoader />
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <Link
        to="/fee-structures"
        className={buttonVariants({ variant: 'ghost' })}
      >
        <ArrowLeft aria-hidden="true" />
        Back to fee structures
      </Link>

      <div>
        <h1 className="text-2xl font-semibold tracking-tight">
          {structure.name}
        </h1>
        <p className="text-sm text-muted-foreground">
          The charges that add up to this structure&rsquo;s total. Removing an
          item takes its amount out of the total.
        </p>
      </div>

      {itemsError && (
        <div
          role="alert"
          className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
        >
          {itemsError}
        </div>
      )}

      <Card>
        <CardHeader className="flex flex-row items-center justify-between gap-4">
          <CardTitle>Items</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search items…"
          />
        </CardHeader>
        <CardContent className="space-y-4">
          <form
            key={editingItem ? `edit-${editingItem.id}` : `new-${formKey}`}
            onSubmit={handleSubmit}
            className="flex flex-wrap items-end gap-3 rounded-xl border p-4"
            noValidate
          >
            <div className="min-w-48 flex-1">
              <FormField
                id="name"
                label="Item name"
                name="name"
                required
                defaultValue={editingItem?.name ?? ''}
                placeholder="e.g. Tuition"
                autoComplete="off"
                maxLength={150}
                error={errors.name}
              />
            </div>
            <div className="w-44">
              <FormField
                id="amount"
                label="Amount"
                name="amount"
                type="number"
                required
                defaultValue={editingItem?.amount ?? ''}
                placeholder="0.00"
                min="0"
                step="0.01"
                error={errors.amount}
              />
            </div>
            <div className="flex items-center gap-2">
              <Button type="submit" disabled={submitting}>
                {submitting
                  ? 'Saving…'
                  : editingItem
                    ? 'Save Changes'
                    : 'Add Item'}
              </Button>
              {editingItem && (
                <Button
                  type="button"
                  variant="outline"
                  onClick={cancelEdit}
                >
                  <X aria-hidden="true" />
                  Cancel
                </Button>
              )}
            </div>
          </form>

          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : items.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {search
                ? `No items match “${search}”.`
                : 'No items yet. Add the first charge with the form above.'}
            </p>
          ) : (
            <>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b text-left text-muted-foreground">
                      <th className="pb-3 font-medium">Name</th>
                      <th className="pb-3 text-right font-medium">Amount</th>
                      <th className="pb-3 text-right font-medium">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {items.map((item) => (
                      <tr key={item.id}>
                        <td className="py-3 font-medium">{item.name}</td>
                        <td className="py-3 text-right tabular-nums">
                          {amountFormat.format(item.amount)}
                        </td>
                        <td className="py-3">
                          <div className="flex items-center justify-end gap-2">
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => startEdit(item)}
                            >
                              <Pencil aria-hidden="true" />
                              Edit
                            </Button>
                            <Button
                              variant="destructive"
                              size="sm"
                              disabled={deletingId === item.id}
                              onClick={() => handleDelete(item)}
                            >
                              <Trash2 aria-hidden="true" />
                              {deletingId === item.id ? 'Deleting…' : 'Delete'}
                            </Button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                  <tfoot>
                    <tr className="border-t font-semibold">
                      <td className="py-3">Total</td>
                      <td className="py-3 text-right tabular-nums">
                        {amountFormat.format(total)}
                      </td>
                      <td />
                    </tr>
                  </tfoot>
                </table>
              </div>

              <ListPagination
                meta={meta}
                loading={loading}
                onPageChange={goToPage}
                label="items"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
