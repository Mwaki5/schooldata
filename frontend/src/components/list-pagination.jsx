import { Button } from '@/components/ui/button'

/**
 * Pagination footer for list tables: range summary plus prev/next controls.
 *
 * Render it under the table (only when rows exist); everything comes from
 * the endpoint's `meta` block.
 *
 * @param {object} props
 * @param {{current_page: number, last_page: number, per_page: number, total: number}} props.meta
 * @param {boolean} [props.loading] Disables the buttons while a page loads.
 * @param {(page: number) => void} props.onPageChange
 * @param {string} [props.label] Plural noun for the summary line.
 */
export function ListPagination({ meta, loading = false, onPageChange, label = 'items' }) {
  if (!meta || meta.total === 0) {
    return null
  }

  const start = (meta.current_page - 1) * meta.per_page + 1
  const end = Math.min(meta.current_page * meta.per_page, meta.total)

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
      <p className="text-sm text-muted-foreground">
        Showing {start}–{end} of {meta.total} {label}
      </p>
      <div className="flex items-center gap-3">
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={loading || meta.current_page <= 1}
          onClick={() => onPageChange(meta.current_page - 1)}
        >
          Previous
        </Button>
        <span className="text-sm text-muted-foreground">
          Page {meta.current_page} of {meta.last_page}
        </span>
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={loading || meta.current_page >= meta.last_page}
          onClick={() => onPageChange(meta.current_page + 1)}
        >
          Next
        </Button>
      </div>
    </div>
  )
}
