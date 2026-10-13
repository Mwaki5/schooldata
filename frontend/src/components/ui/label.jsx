import { cn } from '@/lib/utils'

function Label({ className, ...props }) {
  return (
    <label
      data-slot="label"
      className={cn(
        'flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-muted-foreground leading-none',
        'peer-disabled:cursor-not-allowed peer-disabled:opacity-50',
        className,
      )}
      {...props}
    />
  )
}

export { Label }
