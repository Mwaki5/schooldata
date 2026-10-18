import { useState } from 'react'
import { Eye, EyeOff } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'

/**
 * Password input with a visibility toggle.
 *
 * Render inside `<FormField>` via its `children` slot to keep the label
 * and inline error handling. The toggle is `type="button"` so it never
 * submits the surrounding form.
 */
function PasswordInput({ className, ...props }) {
  const [visible, setVisible] = useState(false)

  return (
    <div className={cn('relative', className)}>
      <Input
        type={visible ? 'text' : 'password'}
        className="pr-11"
        {...props}
      />
      <button
        type="button"
        onClick={() => setVisible((current) => !current)}
        aria-label={visible ? 'Hide password' : 'Show password'}
        aria-pressed={visible}
        tabIndex={-1}
        className="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-xl text-muted-foreground transition-colors outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/30"
      >
        {visible ? (
          <EyeOff className="size-4" aria-hidden="true" />
        ) : (
          <Eye className="size-4" aria-hidden="true" />
        )}
      </button>
    </div>
  )
}

export { PasswordInput }
