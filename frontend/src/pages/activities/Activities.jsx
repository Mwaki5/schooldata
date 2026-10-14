import { useState } from 'react'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Select } from '@/components/ui/select'
import { ListPagination } from '@/components/list-pagination'
import { ListSearch } from '@/components/list-search'
import { SortableTh } from '@/components/sortable-th'
import { useList } from '@/hooks/use-list'
import { cn } from '@/lib/utils'

const EVENT_STYLES = {
  created: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
  updated: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
  deleted: 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
  login: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
  logout: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
  register: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
}

const MAX_DETAIL_ENTRIES = 3

/** `App\Models\ExamBody` -> "Exam Body" */
function friendlyTypeName(subjectType) {
  return subjectType
    .split('\\')
    .pop()
    .replace(/([a-z])([A-Z])/g, '$1 $2')
}

function subjectLabel(activity) {
  if (!activity.subject_type) {
    return '—'
  }

  const name = friendlyTypeName(activity.subject_type)

  return activity.subject_id ? `${name} #${activity.subject_id}` : name
}

function formatTimestamp(value) {
  const date = new Date(value)

  return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString()
}

function truncateValue(value) {
  if (value === null || value === undefined) {
    return '—'
  }

  const text = String(value)

  return text.length > 40 ? `${text.slice(0, 40)}…` : text
}

/**
 * Flatten a row's stored properties into readable entries: updates show
 * `old → new`, deletions the last known values, creations the new ones.
 */
function changeEntries({ event, properties }) {
  const attributes = properties?.attributes ?? {}
  const old = properties?.old ?? {}

  if (event === 'updated') {
    return Object.keys(attributes).map((key) => ({
      key,
      text: `${truncateValue(old[key])} → ${truncateValue(attributes[key])}`,
    }))
  }

  const source = event === 'deleted' ? old : attributes

  return Object.entries(source).map(([key, value]) => ({
    key,
    text: truncateValue(value),
  }))
}

function EventBadge({ event }) {
  return (
    <span
      className={cn(
        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
        EVENT_STYLES[event] ?? 'bg-muted text-muted-foreground',
      )}
    >
      {event ?? 'system'}
    </span>
  )
}

function DetailsCell({ activity }) {
  const entries = changeEntries(activity)
  const ip = activity.properties?.ip
  const userAgent = activity.properties?.user_agent
  const origin = [ip, userAgent].filter(Boolean).join(' · ')

  return (
    <div className="space-y-0.5">
      {entries.length === 0 ? (
        <span className="text-muted-foreground">—</span>
      ) : (
        <>
          {entries
            .slice(0, MAX_DETAIL_ENTRIES)
            .map((entry) => (
              <p
                key={entry.key}
                className="max-w-72 truncate text-xs text-muted-foreground"
              >
                <span className="font-medium text-foreground">{entry.key}</span>:{' '}
                {entry.text}
              </p>
            ))}
          {entries.length > MAX_DETAIL_ENTRIES && (
            <p className="text-xs text-muted-foreground">
              +{entries.length - MAX_DETAIL_ENTRIES} more
            </p>
          )}
        </>
      )}
      {origin && (
        <p
          title={userAgent ? `Device: ${userAgent}` : undefined}
          className="max-w-72 truncate text-xs text-muted-foreground"
        >
          {origin}
        </p>
      )}
    </div>
  )
}

export default function Activities() {
  const [events, setEvents] = useState([])
  const [subjectTypes, setSubjectTypes] = useState([])
  const [eventFilter, setEventFilter] = useState('')
  const [subjectFilter, setSubjectFilter] = useState('')

  const {
    items: activities,
    meta,
    loading,
    loadError,
    search,
    setSearch,
    goToPage,
    sort,
    order,
    toggleSort,
  } = useList({
    resource: 'activities',
    endpoint: '/activities',
    errorMessage: 'Unable to load the activity log. Please try again.',
    filters: {
      event: eventFilter || undefined,
      subject_type: subjectFilter || undefined,
    },
    // The activity endpoint answers with extra payloads (the filter option
    // lists) beside the items and meta.
    onData: (data) => {
      setEvents(data.filters.events)
      setSubjectTypes(data.filters.subject_types)
    },
  })

  const clearFilters = () => {
    setEventFilter('')
    setSubjectFilter('')
    setSearch('')
  }

  const hasFilters =
    eventFilter !== '' || subjectFilter !== '' || search !== ''

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Activity Log</h1>
        <p className="text-sm text-muted-foreground">
          Audit trail of who did what across the school.
        </p>
      </div>

      {loadError && (
        <div
          role="alert"
          className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
        >
          {loadError}
        </div>
      )}

      <Card>
        <CardHeader className="flex flex-row items-center justify-between gap-4">
          <CardTitle>Recent Activity</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search activity…"
          />
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-end gap-3">
            <div className="w-48">
              <Select
                value={eventFilter}
                onChange={(event) => setEventFilter(event.target.value)}
                aria-label="Filter by event"
              >
                <option value="">All events</option>
                {events.map((event) => (
                  <option key={event} value={event}>
                    {event}
                  </option>
                ))}
              </Select>
            </div>
            <div className="w-64">
              <Select
                value={subjectFilter}
                onChange={(event) => setSubjectFilter(event.target.value)}
                aria-label="Filter by subject type"
              >
                <option value="">All subjects</option>
                {subjectTypes.map((subjectType) => (
                  <option key={subjectType} value={subjectType}>
                    {friendlyTypeName(subjectType)}
                  </option>
                ))}
              </Select>
            </div>
            {hasFilters && (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={clearFilters}
              >
                Clear filters
              </Button>
            )}
          </div>

          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : activities.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {search
                ? `No activities match “${search}”.`
                : hasFilters
                  ? 'No activities match the selected filters.'
                  : 'No activity recorded yet.'}
            </p>
          ) : (
            <>
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <SortableTh
                      column="created_at"
                      label="When"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <th className="pb-3 font-medium">User</th>
                    <SortableTh
                      column="event"
                      label="Event"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <th className="pb-3 font-medium">Subject</th>
                    <th className="pb-3 font-medium">Details</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {activities.map((activity) => (
                    <tr key={activity.id}>
                      <td className="py-3 whitespace-nowrap text-muted-foreground">
                        {formatTimestamp(activity.created_at)}
                      </td>
                      <td className="py-3">
                        {activity.causer_name ? (
                          <div>
                            <p className="font-medium">
                              {activity.causer_name}
                            </p>
                            <p className="text-xs text-muted-foreground">
                              {activity.causer_email}
                            </p>
                          </div>
                        ) : (
                          <span className="text-muted-foreground">System</span>
                        )}
                      </td>
                      <td className="py-3">
                        <EventBadge event={activity.event} />
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {subjectLabel(activity)}
                      </td>
                      <td className="py-3">
                        <DetailsCell activity={activity} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>

              <ListPagination
                meta={meta}
                loading={loading}
                onPageChange={goToPage}
                label="activities"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
