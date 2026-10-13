import { useEffect, useState } from 'react'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Select } from '@/components/ui/select'
import api from '@/lib/api'
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
  const [activities, setActivities] = useState([])
  const [meta, setMeta] = useState(null)
  const [events, setEvents] = useState([])
  const [subjectTypes, setSubjectTypes] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [page, setPage] = useState(1)
  const [eventFilter, setEventFilter] = useState('')
  const [subjectFilter, setSubjectFilter] = useState('')

  useEffect(() => {
    api
      .get('/activities', {
        params: {
          page,
          event: eventFilter || undefined,
          subject_type: subjectFilter || undefined,
        },
      })
      .then(({ data }) => {
        setActivities(data.activities)
        setMeta(data.meta)
        setEvents(data.filters.events)
        setSubjectTypes(data.filters.subject_types)
        setLoadError(null)
      })
      .catch(() =>
        setLoadError('Unable to load the activity log. Please try again.'),
      )
      .finally(() => setLoading(false))
  }, [page, eventFilter, subjectFilter])

  /** Page navigation: flip to loading, then let the effect refetch. */
  const goToPage = (nextPage) => {
    setLoading(true)
    setPage(nextPage)
  }

  /** Filter changes always restart from the first page. */
  const applyFilters = (nextEvent, nextSubject) => {
    setLoading(true)
    setPage(1)
    setEventFilter(nextEvent)
    setSubjectFilter(nextSubject)
  }

  const handleEventChange = (event) => {
    applyFilters(event.target.value, subjectFilter)
  }

  const handleSubjectChange = (event) => {
    applyFilters(eventFilter, event.target.value)
  }

  const clearFilters = () => {
    applyFilters('', '')
  }

  const hasFilters = eventFilter !== '' || subjectFilter !== ''
  const start = meta ? (meta.current_page - 1) * meta.per_page + 1 : 0
  const end = meta ? Math.min(meta.current_page * meta.per_page, meta.total) : 0

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
        <CardHeader>
          <CardTitle>Recent Activity</CardTitle>
          <CardDescription>
            {loading
              ? 'Loading activity…'
              : `${meta?.total ?? 0} ${
                  (meta?.total ?? 0) === 1 ? 'activity' : 'activities'
                } total`}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-end gap-3">
            <div className="w-48">
              <Select
                value={eventFilter}
                onChange={handleEventChange}
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
                onChange={handleSubjectChange}
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
              {hasFilters
                ? 'No activities match the selected filters.'
                : 'No activity recorded yet.'}
            </p>
          ) : (
            <>
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <th className="pb-3 font-medium">When</th>
                    <th className="pb-3 font-medium">User</th>
                    <th className="pb-3 font-medium">Event</th>
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

              {meta && (
                <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
                  <p className="text-sm text-muted-foreground">
                    Showing {start}–{end} of {meta.total} activities
                  </p>
                  <div className="flex items-center gap-3">
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      disabled={loading || meta.current_page <= 1}
                      onClick={() => goToPage(meta.current_page - 1)}
                    >
                      Previous
                    </Button>
                    <span className="text-sm text-muted-foreground">
                      Page {meta.current_page} of {meta.last_page}
                    </span>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      disabled={loading || meta.current_page >= meta.last_page}
                      onClick={() => goToPage(meta.current_page + 1)}
                    >
                      Next
                    </Button>
                  </div>
                </div>
              )}
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
