import { ChevronDown, Search } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { cn } from '@/lib/utils'

/**
 * Searchable select (combobox) built for large datasets.
 *
 * Options are fetched from `fetchOptions(query)` when the panel opens and
 * again as the user types (debounced), so only one small page of matches is
 * ever held in memory — safe for tens of thousands of records.
 *
 * @param {string | number | null} value - id of the selected option
 * @param {(value: string | number | null, option: object | null) => void} onChange
 * @param {(query: string, signal: AbortSignal) => Promise<object[]>} fetchOptions
 * @param {string} selectedLabel - display label for the current value
 */
function SearchableSelect({
  id,
  value,
  selectedLabel = '',
  onChange,
  fetchOptions,
  placeholder = '— Choose an option —',
  searchPlaceholder = 'Type to search…',
  valueKey = 'id',
  labelKey = 'name',
  className,
}) {
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [options, setOptions] = useState([])
  const [loading, setLoading] = useState(false)
  const [fetchError, setFetchError] = useState(null)
  const [highlighted, setHighlighted] = useState(0)

  const rootRef = useRef(null)
  const inputRef = useRef(null)
  const listRef = useRef(null)
  const fetchRef = useRef(fetchOptions)

  // Keep the latest fetchOptions without re-running the fetch effect
  // (parents may pass a new inline function on every render).
  useEffect(() => {
    fetchRef.current = fetchOptions
  }, [fetchOptions])

  // Fetch matches when the panel opens and as the query changes.
  useEffect(() => {
    if (!open) {
      return undefined
    }

    const controller = new AbortController()
    const timer = setTimeout(async () => {
      setLoading(true)

      try {
        const results = await fetchRef.current(query, controller.signal)

        if (!controller.signal.aborted) {
          setOptions(results)
          setHighlighted(0)
          setFetchError(null)
        }
      } catch {
        if (!controller.signal.aborted) {
          setFetchError('Search failed. Please try again.')
        }
      } finally {
        if (!controller.signal.aborted) {
          setLoading(false)
        }
      }
    }, query ? 300 : 0)

    return () => {
      controller.abort()
      clearTimeout(timer)
    }
  }, [open, query])

  // Close when clicking outside the control.
  useEffect(() => {
    if (!open) {
      return undefined
    }

    const handlePointerDown = (event) => {
      if (rootRef.current && !rootRef.current.contains(event.target)) {
        setOpen(false)
      }
    }

    document.addEventListener('mousedown', handlePointerDown)

    return () => document.removeEventListener('mousedown', handlePointerDown)
  }, [open])

  // Focus the search field each time the panel opens.
  useEffect(() => {
    if (open) {
      inputRef.current?.focus()
    }
  }, [open])

  // Keep the highlighted option visible while arrowing.
  useEffect(() => {
    if (open && listRef.current) {
      listRef.current.children[highlighted]?.scrollIntoView({
        block: 'nearest',
      })
    }
  }, [open, highlighted])

  const select = (option) => {
    onChange(option[valueKey], option)
    setOpen(false)
    setQuery('')
  }

  const handleKeyDown = (event) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setHighlighted((current) => Math.min(current + 1, options.length - 1))
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      setHighlighted((current) => Math.max(current - 1, 0))
    } else if (event.key === 'Enter') {
      event.preventDefault()

      const option = options[highlighted]
      if (option) {
        select(option)
      }
    } else if (event.key === 'Escape') {
      setOpen(false)
    }
  }

  return (
    <div ref={rootRef} className={cn('relative', className)}>
      <button
        id={id}
        type="button"
        aria-haspopup="listbox"
        aria-expanded={open}
        onClick={() => {
          setQuery('')
          setOpen((current) => !current)
        }}
        className={cn(
          'flex h-11 w-full min-w-0 items-center gap-2 rounded-xl border border-input bg-background px-4 py-2 text-left text-sm shadow-xs',
          'transition-[color,box-shadow] outline-none',
          'focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30',
          'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
          open && 'border-ring ring-2 ring-ring/30',
        )}
      >
        <span
          className={cn(
            'flex-1 truncate',
            !value && 'text-muted-foreground',
          )}
        >
          {value ? selectedLabel || placeholder : placeholder}
        </span>

        {value && (
          <span
            role="button"
            tabIndex={-1}
            aria-label="Clear selection"
            onClick={(event) => {
              event.stopPropagation()
              onChange(null, null)
            }}
            className="rounded-full p-0.5 text-muted-foreground hover:bg-accent hover:text-foreground"
          >
            ×
          </span>
        )}

        <ChevronDown
          aria-hidden="true"
          className={cn(
            'size-4 shrink-0 text-muted-foreground transition-transform duration-200',
            open && 'rotate-180',
          )}
        />
      </button>

      {open && (
        <div className="absolute z-50 mt-1 w-full overflow-hidden rounded-xl border bg-background shadow-md">
          <div className="flex items-center gap-2 border-b px-3">
            <Search
              aria-hidden="true"
              className="size-4 shrink-0 text-muted-foreground"
            />
            <input
              ref={inputRef}
              type="text"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              onKeyDown={handleKeyDown}
              placeholder={searchPlaceholder}
              autoComplete="off"
              className="h-10 w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
            />
          </div>

          <ul
            ref={listRef}
            role="listbox"
            className="max-h-60 overflow-y-auto p-1"
          >
            {loading && (
              <li className="px-3 py-2 text-sm text-muted-foreground">
                Searching…
              </li>
            )}

            {!loading && fetchError && (
              <li className="px-3 py-2 text-sm text-destructive">
                {fetchError}
              </li>
            )}

            {!loading && !fetchError && options.length === 0 && (
              <li className="px-3 py-2 text-sm text-muted-foreground">
                No matches found.
              </li>
            )}

            {!loading &&
              !fetchError &&
              options.map((option, index) => {
                const optionValue = option[valueKey]

                return (
                  <li
                    key={optionValue}
                    role="option"
                    aria-selected={optionValue === value}
                  >
                    <button
                      type="button"
                      onMouseEnter={() => setHighlighted(index)}
                      onClick={() => select(option)}
                      className={cn(
                        'flex w-full items-center rounded-lg px-3 py-2 text-left text-sm',
                        index === highlighted &&
                          'bg-accent text-accent-foreground',
                        optionValue === value &&
                          'font-medium text-primary',
                      )}
                    >
                      {option[labelKey]}
                    </button>
                  </li>
                )
              })}
          </ul>
        </div>
      )}
    </div>
  )
}

export { SearchableSelect }
