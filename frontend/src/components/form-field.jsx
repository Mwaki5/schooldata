import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { cn } from '@/lib/utils'

/**
 * Admin-style form field: uppercase micro-label with a red required
 * marker, rounded input (or textarea with `multiline`), and inline
 * validation error. Pass `children` to render a custom control
 * (select, checkbox, …) instead of the default input.
 */
function FormField({
  id,
  label,
  required = false,
  error,
  className,
  inputClassName,
  multiline = false,
  children,
  ...props
}) {
  return (
    <div className={cn('space-y-2', className)}>
      <Label htmlFor={id}>
        {label}
        {required && (
          <span aria-hidden="true" className="font-normal text-destructive">
            *
          </span>
        )}
      </Label>
      {children ?? (multiline ? (
        <Textarea
          id={id}
          required={required}
          aria-invalid={error ? 'true' : undefined}
          className={inputClassName}
          {...props}
        />
      ) : (
        <Input
          id={id}
          required={required}
          aria-invalid={error ? 'true' : undefined}
          className={inputClassName}
          {...props}
        />
      ))}
      {error && (
        <p role="alert" className="text-sm font-medium text-destructive">
          {error}
        </p>
      )}
    </div>
  )
}

export { FormField }
