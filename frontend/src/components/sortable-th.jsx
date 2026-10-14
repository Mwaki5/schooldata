import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react'
import { cn } from '@/lib/utils'

/**
 * Clickable table header for columns the API can sort on.
 *
 * Cycle is ascending → descending → back to the endpoint's default order.
 * Columns that are not whitelisted server-side (relations, computed cells)
 * stay a plain `<th>`.
 *
 * @param {object} props
 * @param {string} props.column Sort key sent as `?sort=`.
 * @param {string} props.label Header text.
 * @param {string|null} props.sort Current sort key from `useList`.
 * @param {'asc'|'desc'} props.order Current direction from `useList`.
 * @param {(column: string) => void} props.onSort
 */
export function SortableTh({ column, label, sort, order, onSort, className }) {
  const active = sort === column

  return (
    <th
      scope="col"
      aria-sort={active ? (order === 'asc' ? 'ascending' : 'descending') : 'none'}
      className={cn('pb-3 font-medium', className)}
    >
      <button
        type="button"
        onClick={() => onSort(column)}
        className={cn(
          'inline-flex items-center gap-1 transition-colors hover:text-foreground',
          active && 'text-foreground',
        )}
        aria-label={`Sort by ${label}`}
      >
        {label}
        {active ? (
          order === 'asc' ? (
            <ArrowUp aria-hidden="true" className="h-3.5 w-3.5" />
          ) : (
            <ArrowDown aria-hidden="true" className="h-3.5 w-3.5" />
          )
        ) : (
          <ArrowUpDown aria-hidden="true" className="h-3.5 w-3.5 opacity-40" />
        )}
      </button>
    </th>
  )
}
