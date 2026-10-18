import { Search } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'

/**
 * Debounced-friendly text search box for list tables.
 *
 * The keystrokes stay local — pass the value straight to `useList`'s
 * `search` / `setSearch`, which debounces before hitting the API.
 */
export function ListSearch({ value, onChange, placeholder = 'Search…', className }) {
  return (
    <div className={cn('relative w-full sm:w-64', className)}>
      <Search
        aria-hidden="true"
        className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
      />
      <Input
        type="search"
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder={placeholder}
        aria-label={placeholder}
        className="h-9 pl-9"
      />
    </div>
  )
}
