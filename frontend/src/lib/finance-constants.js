/**
 * The vocabulary every finance screen shares: the invoice lifecycle the
 * ledger derives, the kinds an invoice charges for, the methods money
 * arrives by, and the money and date formats every figure renders in.
 *
 * Nothing here is invented on the client — each value mirrors an enum
 * the backend enforces, and every status a table shows is derived
 * server-side by the StudentLedgerService.
 */

// Derived from the immutable rows: nothing settled, partly settled,
// fully settled by the oldest-first allocation, or excluded from
// allocation entirely (a reversed original or a reversal row).
export const INVOICE_STATUS_LABELS = {
  unpaid: 'Unpaid',
  partially_paid: 'Partially Paid',
  paid: 'Paid',
  reversed: 'Reversed',
}

export const INVOICE_STATUS_STYLES = {
  unpaid: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
  partially_paid: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
  paid: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
  reversed: 'bg-muted text-muted-foreground',
}

// The statuses a list may filter by; the API rejects anything else.
export const INVOICE_STATUS_FILTERS = [
  { value: '', label: 'All statuses' },
  { value: 'unpaid', label: 'Unpaid' },
  { value: 'partially_paid', label: 'Partially Paid' },
  { value: 'paid', label: 'Paid' },
  { value: 'reversed', label: 'Reversed' },
]

// FEE_INVOICE is the structured one, raised from a fee structure; every
// other type is a free-form "other invoice" the desk amounts by hand.
export const INVOICE_TYPE_LABELS = {
  FEE_INVOICE: 'Fee Invoice',
  PENALTY: 'Penalty',
  HOSTEL: 'Hostel',
  TRANSPORT: 'Transport',
  EXAM_FEE: 'Exam Fee',
  OTHER: 'Other',
}

// The types an "other invoice" may carry — everything except the
// structured fee invoice, which has its own tab and rules.
export const OTHER_INVOICE_TYPES = [
  { value: 'PENALTY', label: 'Penalty' },
  { value: 'HOSTEL', label: 'Hostel' },
  { value: 'TRANSPORT', label: 'Transport' },
  { value: 'EXAM_FEE', label: 'Exam Fee' },
  { value: 'OTHER', label: 'Other' },
]

// The methods money may arrive by. A transaction reference is unique
// within its method, so the same bank slip cannot be entered twice.
export const PAYMENT_METHODS = [
  { value: 'CASH', label: 'Cash' },
  { value: 'BANK', label: 'Bank' },
  { value: 'MOBILE_MONEY', label: 'Mobile Money' },
  { value: 'CARD', label: 'Card' },
]

// How a ledger row entered the books. Only the plain kinds may be
// reversed; a reversal row is final.
export const ENTRY_KIND_LABELS = {
  INVOICE: 'Invoice',
  REVERSAL: 'Reversal',
  PAYMENT: 'Payment',
  PAYMENT_REVERSAL: 'Reversal',
  REFUND: 'Refund',
  REFUND_REVERSAL: 'Reversal',
}

const amountFormat = new Intl.NumberFormat('en-US', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

/** Format a figure as Kenyan shillings: `45,000.00` → `KES 45,000.00`. */
export function formatKes(value) {
  return `KES ${amountFormat.format(Number(value) || 0)}`
}

/** The bare number, for table cells that carry their own currency header. */
export function formatAmount(value) {
  return amountFormat.format(Number(value) || 0)
}

export function formatDateTime(value) {
  if (!value) {
    return '—'
  }

  const date = new Date(value)

  return Number.isNaN(date.getTime())
    ? '—'
    : `${date.toLocaleDateString()} ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
}

/**
 * A fresh idempotency key for one submission attempt. The API replays
 * the original document for a key it has already seen, so a double
 * click or a network retry cannot bill a student twice.
 */
export function newIdempotencyKey() {
  if (globalThis.crypto?.randomUUID) {
    return globalThis.crypto.randomUUID()
  }

  return `idem-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`
}

/** `Wanjiku Otieno — ADM-2026-0001`, or just the name when unnumbered. */
export function studentLabel(student) {
  if (!student) {
    return ''
  }

  return student.login_id
    ? `${student.name} — ${student.login_id}`
    : student.name
}
