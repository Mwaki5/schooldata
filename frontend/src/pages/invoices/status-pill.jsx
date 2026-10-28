import { cn } from '@/lib/utils'
import {
  INVOICE_STATUS_LABELS,
  INVOICE_STATUS_STYLES,
} from '@/lib/finance-constants'

/** The inline pill an invoice's derived status wears — no Badge component. */
export function StatusPill({ status }) {
  return (
    <span
      className={cn(
        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
        INVOICE_STATUS_STYLES[status] ?? INVOICE_STATUS_STYLES.reversed,
      )}
    >
      {INVOICE_STATUS_LABELS[status] ?? status}
    </span>
  )
}
