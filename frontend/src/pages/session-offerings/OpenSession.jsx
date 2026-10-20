import {
  ArrowLeft,
  ArrowRight,
  CalendarClock,
  Check,
  Search,
} from 'lucide-react'
import { useCallback, useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Select } from '@/components/ui/select'
import api from '@/lib/api'
import { cn } from '@/lib/utils'

const PAGE_SIZE = 12

const SOURCE_STYLES = {
  cohort: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
  last_year: 'bg-sky-500/10 text-sky-700 dark:text-sky-400',
  new_intake: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
}

const SOURCE_LABELS = {
  cohort: 'Cohort',
  last_year: 'Last year',
  new_intake: 'New intake',
}

const WINDOW_STYLES = {
  open: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
  upcoming: 'bg-sky-500/10 text-sky-700 dark:text-sky-400',
  ended: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
  not_configured: 'bg-destructive/10 text-destructive',
}

const WINDOW_LABELS = {
  open: 'Open now',
  upcoming: 'Opens later',
  ended: 'Closed',
  not_configured: 'Not configured',
}

/** Format a date-only API value without shifting it across time zones. */
function formatEventDate(value) {
  if (!value) {
    return '—'
  }

  const date = new Date(`${String(value).slice(0, 10)}T00:00:00`)

  if (Number.isNaN(date.getTime())) {
    return '—'
  }

  return date.toLocaleDateString(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

/** Update one stage in the loaded grid while keeping the open count in sync. */
function updateStageState(payload, stageId, isOpen) {
  if (!payload) {
    return payload
  }

  let delta = 0

  const courses = payload.courses.map((course) => ({
    ...course,
    stages: course.stages.map((stage) => {
      if (stage.id !== stageId || stage.is_open === isOpen) {
        return stage
      }

      delta = isOpen ? 1 : -1

      return { ...stage, is_open: isOpen }
    }),
  }))

  if (delta === 0) {
    return { ...payload, courses }
  }

  return {
    ...payload,
    courses,
    stats: {
      ...payload.stats,
      open_stages: Math.max(0, (payload.stats?.open_stages ?? 0) + delta),
    },
  }
}

function SourceBadge({ source }) {
  return (
    <span
      className={cn(
        'rounded-full px-1.5 py-0.5 text-[10px] font-medium',
        SOURCE_STYLES[source],
      )}
    >
      {SOURCE_LABELS[source]}
    </span>
  )
}

function StepMarker({ number, label, active, done }) {
  return (
    <div className="flex items-center gap-2">
      <span
        className={cn(
          'flex size-7 items-center justify-center rounded-full border text-xs font-semibold',
          active && 'border-primary bg-primary text-primary-foreground',
          done && 'border-emerald-500 bg-emerald-500 text-white',
          !active && !done && 'border-input text-muted-foreground',
        )}
      >
        {done ? <Check aria-hidden="true" className="size-3.5" /> : number}
      </span>
      <span
        className={cn(
          'text-sm font-medium',
          active || done ? 'text-foreground' : 'text-muted-foreground',
        )}
      >
        {label}
      </span>
    </div>
  )
}

export default function OpenSession() {
  const [sessions, setSessions] = useState([])
  const [sessionId, setSessionId] = useState('')
  const [step, setStep] = useState(1)
  const [data, setData] = useState(null)
  const [loadingSessions, setLoadingSessions] = useState(true)
  const [loadingGrid, setLoadingGrid] = useState(false)
  const [loadError, setLoadError] = useState(null)
  const [pendingStages, setPendingStages] = useState({})

  const [search, setSearch] = useState('')
  const [departmentId, setDepartmentId] = useState('')
  const [stageCode, setStageCode] = useState('')
  const [page, setPage] = useState(1)

  const requestId = useRef(0)
  const loadedFilterKey = useRef('')

  useEffect(() => {
    let active = true

    api
      .get('/academic-sessions', {
        params: {
          per_page: 100,
          is_active: 1,
          sort: 'start_date',
          order: 'desc',
        },
      })
      .then(({ data: payload }) => {
        if (!active) {
          return
        }

        const rows = payload.academic_sessions ?? []
        setSessions(rows)
        setSessionId(String(rows[0]?.id ?? ''))
      })
      .catch(() => {
        if (active) {
          setLoadError('Unable to load academic sessions.')
        }
      })
      .finally(() => {
        if (active) {
          setLoadingSessions(false)
        }
      })

    return () => {
      active = false
    }
  }, [])

  const loadGrid = useCallback(
    async ({ filters = null } = {}) => {
      if (!sessionId) {
        return
      }

      const activeFilters = filters ?? { search, departmentId, stageCode }
      const activeSearch = activeFilters.search.trim()
      const request = requestId.current + 1
      const filterKey = `${sessionId}|${activeSearch}|${activeFilters.departmentId}|${activeFilters.stageCode}`

      requestId.current = request
      setLoadingGrid(true)
      setLoadError(null)

      try {
        const { data: payload } = await api.get('/session-offerings', {
          params: {
            academic_session_id: sessionId,
            ...(activeSearch ? { search: activeSearch } : {}),
            ...(activeFilters.departmentId !== ''
              ? { department_id: activeFilters.departmentId }
              : {}),
            ...(activeFilters.stageCode
              ? { stage_code: activeFilters.stageCode }
              : {}),
          },
        })

        if (request !== requestId.current) {
          return
        }

        setData(payload)
        setPage(1)
        loadedFilterKey.current = filterKey
      } catch {
        if (request === requestId.current) {
          setLoadError('Unable to load the stage grid for this session.')
        }
      } finally {
        if (request === requestId.current) {
          setLoadingGrid(false)
        }
      }
    },
    [sessionId, search, departmentId, stageCode],
  )

  const filterKey = `${sessionId}|${search.trim()}|${departmentId}|${stageCode}`

  // Filters are resolved by the API. Debounce typing so a search does not
  // fire one request per keystroke.
  useEffect(() => {
    if (step !== 2 || !data || loadingGrid) {
      return undefined
    }

    if (loadedFilterKey.current === filterKey) {
      return undefined
    }

    const timer = window.setTimeout(() => {
      loadGrid()
    }, 300)

    return () => window.clearTimeout(timer)
  }, [step, data, loadingGrid, filterKey, loadGrid])

  const startWizard = () => {
    setData(null)
    setPendingStages({})
    setSearch('')
    setDepartmentId('')
    setStageCode('')
    setPage(1)
    setStep(2)
    loadGrid({
      filters: { search: '', departmentId: '', stageCode: '' },
    })
  }

  const toggleStage = async (stage) => {
    if (pendingStages[stage.id]) {
      return
    }

    const nextOpen = !stage.is_open

    setPendingStages((previous) => ({ ...previous, [stage.id]: true }))
    setData((previous) => updateStageState(previous, stage.id, nextOpen))

    try {
      await api.patch('/session-offerings/stage', {
        academic_session_id: Number(sessionId),
        course_stage_id: stage.id,
        open: nextOpen,
      })
    } catch {
      setData((previous) =>
        updateStageState(previous, stage.id, stage.is_open),
      )
      toast.error('Unable to update this stage. Please try again.')
    } finally {
      setPendingStages((previous) => {
        const next = { ...previous }
        delete next[stage.id]
        return next
      })
    }
  }

  const courses = data?.courses ?? []
  const departments = data?.departments ?? []
  const stageCodes = data?.stage_codes ?? []

  const pageCount = Math.max(1, Math.ceil(courses.length / PAGE_SIZE))
  const currentPage = Math.min(page, pageCount)
  const visibleCourses = courses.slice(
    (currentPage - 1) * PAGE_SIZE,
    currentPage * PAGE_SIZE,
  )

  const session = data?.session
  const registrationWindow = data?.registration_window
  const chosenSession = sessions.find(
    (row) => String(row.id) === String(sessionId),
  )

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Open Session</h1>
        <p className="text-sm text-muted-foreground">
          Tick a stage to open it for registration; untick it to close it.
          Changes save immediately. Registration dates come from the Session
          Registration term event.
        </p>
      </div>

      <div className="flex flex-wrap items-center gap-4 rounded-2xl border bg-card px-5 py-4">
        <StepMarker
          number={1}
          label="Session"
          active={step === 1}
          done={step > 1}
        />
        <span className="text-muted-foreground">/</span>
        <StepMarker number={2} label="Stages" active={step === 2} />
      </div>

      {loadError && (
        <div
          role="alert"
          className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
        >
          {loadError}
        </div>
      )}

      {step === 1 && (
        <Card>
          <CardHeader>
            <CardTitle>1. Choose the active academic session</CardTitle>
          </CardHeader>
          <CardContent className="space-y-5">
            {loadingSessions ? (
              <p className="text-sm text-muted-foreground">Loading sessions…</p>
            ) : sessions.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                No active academic session exists. Activate one under Academic
                Session first.
              </p>
            ) : (
              <>
                <div className="max-w-md space-y-2">
                  <label
                    htmlFor="session"
                    className="text-xs font-medium text-muted-foreground uppercase"
                  >
                    Active academic session
                  </label>
                  <Select
                    id="session"
                    value={sessionId}
                    onChange={(event) => setSessionId(event.target.value)}
                  >
                    {sessions.map((option) => (
                      <option key={option.id} value={option.id}>
                        {option.name}
                      </option>
                    ))}
                  </Select>
                  {chosenSession && (
                    <p className="text-xs text-muted-foreground">
                      {chosenSession.start_date ?? 'No start date'} to{' '}
                      {chosenSession.end_date ?? 'no end date'}
                    </p>
                  )}
                </div>

                <div className="flex justify-end">
                  <Button onClick={startWizard} disabled={!sessionId}>
                    Continue <ArrowRight aria-hidden="true" />
                  </Button>
                </div>
              </>
            )}
          </CardContent>
        </Card>
      )}

      {step === 2 && (
        <div className="space-y-5">
          <Card>
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-4">
              <div className="min-w-0">
                <CardTitle>
                  {session?.name ?? 'Stage grid'}
                  {session?.slot ? ` · Slot ${session.slot}` : ''}
                </CardTitle>
                <p className="mt-1 text-sm text-muted-foreground">
                  Tick a stage to open it. Untick it to close it for
                  registration.
                </p>

                {data && (
                  <div className="mt-3 rounded-xl border bg-muted/20 px-3 py-2 text-xs">
                    <div className="flex flex-wrap items-center gap-2">
                      <CalendarClock
                        aria-hidden="true"
                        className="size-4 text-muted-foreground"
                      />
                      {registrationWindow ? (
                        <>
                          <span className="font-medium">
                            Registration{' '}
                            {formatEventDate(registrationWindow.starts_on)} –{' '}
                            {formatEventDate(registrationWindow.ends_on)}
                          </span>
                          <span
                            className={cn(
                              'rounded-full px-2 py-0.5 font-medium',
                              WINDOW_STYLES[registrationWindow.state],
                            )}
                          >
                            {WINDOW_LABELS[registrationWindow.state]}
                          </span>
                          <Link
                            to="/term-events"
                            className="text-muted-foreground underline"
                          >
                            Edit in Term Events
                          </Link>
                        </>
                      ) : (
                        <>
                          <span className="font-medium text-destructive">
                            No Session Registration event
                          </span>
                          <Link
                            to="/term-events"
                            className="text-muted-foreground underline"
                          >
                            Add it in Term Events
                          </Link>
                        </>
                      )}
                    </div>

                    {registrationWindow &&
                      registrationWindow.state !== 'open' && (
                        <p className="mt-1 text-amber-700 dark:text-amber-400">
                          Registrations are currently refused because the window
                          is{' '}
                          {registrationWindow.state === 'upcoming'
                            ? 'not open yet'
                            : 'closed'}
                          .
                        </p>
                      )}

                    {!registrationWindow && (
                      <p className="mt-1 text-destructive">
                        Registrations will be refused until a Session
                        Registration event is added for this session.
                      </p>
                    )}

                    {registrationWindow && !registrationWindow.is_active && (
                      <p className="mt-1 text-muted-foreground">
                        This term event is marked inactive.
                      </p>
                    )}
                  </div>
                )}
              </div>
              <div className="flex flex-wrap items-center gap-2 text-xs">
                <span className="rounded-full bg-emerald-500/10 px-2.5 py-1 font-medium text-emerald-700 dark:text-emerald-400">
                  {data?.stats?.open_stages ?? 0} open
                </span>
                <span className="rounded-full bg-muted px-2.5 py-1 font-medium text-muted-foreground">
                  {data?.stats?.suggested_stages ?? 0} suggested
                </span>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-3 sm:grid-cols-[1fr_200px_160px]">
                <div className="relative">
                  <Search
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                  />
                  <Input
                    value={search}
                    onChange={(event) => {
                      setSearch(event.target.value)
                      setPage(1)
                    }}
                    placeholder="Search by course code or name…"
                    className="pl-9"
                    aria-label="Search courses"
                  />
                </div>
                <Select
                  value={departmentId}
                  onChange={(event) => {
                    setDepartmentId(event.target.value)
                    setPage(1)
                  }}
                  aria-label="Filter by department"
                >
                  <option value="">All departments</option>
                  {departments.map((department) => (
                    <option key={department.id} value={department.id}>
                      {department.name}
                    </option>
                  ))}
                  <option value="0">Unassigned</option>
                </Select>
                <Select
                  value={stageCode}
                  onChange={(event) => {
                    setStageCode(event.target.value)
                    setPage(1)
                  }}
                  aria-label="Filter by stage code"
                >
                  <option value="">All stages</option>
                  {stageCodes.map((code) => (
                    <option key={code} value={code}>
                      {code}
                    </option>
                  ))}
                </Select>
              </div>

              <p className="text-xs text-muted-foreground">
                {courses.length} course(s) match the filters. Toggle any stage
                below to save its status immediately.
              </p>
            </CardContent>
          </Card>

          {loadingGrid ? (
            <Card>
              <CardContent className="py-10 text-center text-sm text-muted-foreground">
                Loading the course × stage grid…
              </CardContent>
            </Card>
          ) : courses.length === 0 ? (
            <Card>
              <CardContent className="py-10 text-center text-sm text-muted-foreground">
                No courses match these filters.
              </CardContent>
            </Card>
          ) : (
            <div className="space-y-3">
              {visibleCourses.map((course) => {
                const courseOpenCount = course.stages.filter(
                  (stage) => stage.is_open,
                ).length

                return (
                  <div
                    key={course.id}
                    className="rounded-2xl border bg-card px-5 py-4"
                  >
                    <div className="flex flex-wrap items-start justify-between gap-2">
                      <div>
                        <p className="font-medium">
                          {course.code} — {course.name}
                        </p>
                        <p className="text-xs text-muted-foreground">
                          {course.department_name ?? 'Unassigned'}
                          {course.is_active ? '' : ' · inactive course'}
                        </p>
                      </div>
                      <span className="text-xs text-muted-foreground">
                        {courseOpenCount}/{course.stages.length} open
                      </span>
                    </div>

                    {course.stages.length === 0 ? (
                      <p className="mt-3 text-xs text-muted-foreground">
                        No stages generated for this course yet.
                      </p>
                    ) : (
                      <div className="mt-3 flex flex-wrap gap-2">
                        {course.stages.map((stage) => {
                          const pending = Boolean(pendingStages[stage.id])

                          return (
                            <label
                              key={stage.id}
                              aria-busy={pending}
                              className={cn(
                                'flex cursor-pointer items-center gap-2 rounded-lg border px-2.5 py-1.5 text-xs transition-colors',
                                stage.is_open
                                  ? 'border-emerald-500 bg-emerald-500/5'
                                  : 'border-input hover:bg-accent/50',
                                pending && 'pointer-events-none opacity-60',
                              )}
                            >
                              <Checkbox
                                checked={stage.is_open}
                                disabled={pending}
                                onChange={() => toggleStage(stage)}
                                aria-label={`${course.code} ${stage.code} ${
                                  stage.is_open ? 'open' : 'closed'
                                }`}
                              />
                              <span className="font-medium">{stage.code}</span>
                              {stage.source && (
                                <SourceBadge source={stage.source} />
                              )}
                              <span
                                className={cn(
                                  'text-[10px] font-medium',
                                  stage.is_open
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-muted-foreground',
                                )}
                              >
                                {stage.is_open ? 'open' : 'closed'}
                              </span>
                            </label>
                          )
                        })}
                      </div>
                    )}
                  </div>
                )
              })}

              <div className="flex items-center justify-between">
                <p className="text-xs text-muted-foreground">
                  Page {currentPage} of {pageCount}
                </p>
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setPage((value) => Math.max(1, value - 1))}
                    disabled={currentPage <= 1}
                  >
                    <ArrowLeft aria-hidden="true" /> Previous
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                      setPage((value) => Math.min(pageCount, value + 1))
                    }
                    disabled={currentPage >= pageCount}
                  >
                    Next <ArrowRight aria-hidden="true" />
                  </Button>
                </div>
              </div>
            </div>
          )}

          <div className="flex flex-wrap items-center justify-between gap-3">
            <Button variant="outline" onClick={() => setStep(1)}>
              <ArrowLeft aria-hidden="true" /> Back to session
            </Button>
            <p className="text-xs text-muted-foreground">
              Every toggle is saved immediately.
            </p>
          </div>
        </div>
      )}
    </div>
  )
}
