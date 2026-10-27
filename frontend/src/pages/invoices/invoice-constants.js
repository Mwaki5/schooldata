/**
 * The vocabulary shared by the invoice screens: the lifecycle an
 * invoice wears, the kinds a line may charge for, and the money and
 * date formats every invoice figure renders in.
 */

// Pending until settled, paid once a settlement lands, void for one
// that must not be collected.
export const STATUS_LABELS = {
  pending: 'Pending',
  paid: 'Paid',
  void: 'Void',
}

export const STATUS_STYLES = {
  pending: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
  paid: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
  void: 'bg-muted text-muted-foreground',
}

// The fixed kinds a line carries; the description on each line holds
// the specifics ("Term 2 tuition", "Hostel Block A").
export const LINE_TYPES = [
  { value: 'fee', label: 'Fee' },
  { value: 'penalty', label: 'Penalty' },
  { value: 'hostel', label: 'Hostel' },
  { value: 'transport', label: 'Transport' },
  { value: 'exam', label: 'Exam' },
  { value: 'other', label: 'Other' },
]

export const amountFormat = new Intl.NumberFormat('en-US', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

export function formatDate(value) {
  if (!value) {
    return '—'
  }

  const date = new Date(value)

  return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString()
}
