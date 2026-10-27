import { cn } from '@/lib/utils'
import { STATUS_LABELS, STATUS_STYLES } from '@/pages/invoices/invoice-constants'

/** The inline pill an invoice's status wears — no Badge component. */
export function StatusPill({ status }) {
  return (
    <span
      className={cn(
        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
        STATUS_STYLES[status] ?? STATUS_STYLES.void,
      )}
    >
      {STATUS_LABELS[status] ?? status}
    </span>
  )
}
