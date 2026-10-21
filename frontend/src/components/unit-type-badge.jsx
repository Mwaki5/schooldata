import { cn } from '@/lib/utils'

const TYPE_STYLES = {
  basic: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
  core: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
  common: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
}

const TYPE_LABELS = {
  basic: 'Basic',
  core: 'Core',
  common: 'Common',
}

/**
 * The role a unit plays inside its course: Basic, Core or Common —
 * stored on the course-unit link, shown wherever a link is listed.
 *
 * @param {object} props
 * @param {string} props.type One of `basic`, `core`, `common`.
 */
export function UnitTypeBadge({ type }) {
  return (
    <span
      className={cn(
        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
        TYPE_STYLES[type] ?? 'bg-muted text-muted-foreground',
      )}
    >
      {TYPE_LABELS[type] ?? type}
    </span>
  )
}
