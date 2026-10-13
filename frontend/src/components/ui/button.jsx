/* eslint-disable react-refresh/only-export-components */
import { cn } from '@/lib/utils'

const buttonVariantClasses = {
  default: 'bg-primary text-primary-foreground shadow-xs hover:bg-primary/90',
  secondary:
    'bg-secondary text-secondary-foreground shadow-xs hover:bg-secondary/80',
  outline:
    'border border-input bg-background shadow-xs hover:bg-accent hover:text-accent-foreground',
  ghost: 'hover:bg-accent hover:text-accent-foreground',
  destructive:
    'bg-destructive text-destructive-foreground shadow-xs hover:bg-destructive/90',
  link: 'text-primary underline-offset-4 hover:underline',
}

const buttonSizeClasses = {
  default: 'h-10 px-4 py-2 has-[>svg]:px-3',
  sm: 'h-8 gap-1.5 px-3 has-[>svg]:px-2.5',
  lg: 'h-11 px-6 has-[>svg]:px-4',
  icon: 'size-10',
}

/**
 * Class string for a button variant — usable on `<Link>` elements so
 * navigation controls can look like buttons without nesting interactives.
 */
function buttonVariants({ variant = 'default', size = 'default' } = {}) {
  return cn(
    "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30 aria-invalid:ring-destructive/20 aria-invalid:border-destructive",
    buttonVariantClasses[variant],
    buttonSizeClasses[size],
  )
}

function Button({
  className,
  variant = 'default',
  size = 'default',
  ...props
}) {
  return (
    <button
      data-slot="button"
      data-variant={variant}
      data-size={size}
      className={cn(buttonVariants({ variant, size }), className)}
      {...props}
    />
  )
}

export { Button, buttonVariants }
