import {
  BadgeCheck,
  BookOpen,
  CalendarDays,
  ClipboardList,
  GraduationCap,
} from 'lucide-react'
import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { UnitTypeBadge } from '@/components/unit-type-badge'
import { useAuth } from '@/context/AuthContext'
import api from '@/lib/api'
import { cn } from '@/lib/utils'

const ENROLMENT_STATUS_STYLES = {
  ENROLLED: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
  COMPLETED: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
  DROPPED: 'bg-destructive/10 text-destructive',
}

function StatusBadge({ status, styles }) {
  const label = status
    ? status.charAt(0).toUpperCase() + status.slice(1).toLowerCase()
    : 'Unknown'

  return (
    <span
      className={cn(
        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
        styles[status] ?? 'bg-muted text-muted-foreground',
      )}
    >
      {label}
    </span>
  )
}

/** `2026-01-10T00:00:00.000000Z` or `2026-01-10` → locale date; `—` when empty. */
function formatDate(value) {
  if (!value) {
    return '—'
  }

  const date = new Date(`${String(value).slice(0, 10)}T00:00:00`)

  return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString()
}

function StatCard({ label, value, icon: Icon }) {
  return (
    <Card>
      <CardContent className="flex items-center gap-4">
        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted">
          <Icon className="h-5 w-5 text-muted-foreground" aria-hidden="true" />
        </span>
        <div>
          <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
            {label}
          </p>
          <p className="text-2xl font-semibold tracking-tight">{value}</p>
        </div>
      </CardContent>
    </Card>
  )
}

export default function StudentDashboard() {
  const { user } = useAuth()
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [reporting, setReporting] = useState(false)
  const [sessionUnits, setSessionUnits] = useState(null)
  const [selectedUnits, setSelectedUnits] = useState(() => new Set())
  const [registering, setRegistering] = useState(false)

  useEffect(() => {
    let active = true

    api
      .get('/me/dashboard')
      .then((response) => {
        if (active) {
          setData(response.data)
          setLoadError('')
        }
      })
      .catch(() => {
        if (active) {
          setLoadError('Unable to load your dashboard. Please try again.')
          toast.error('Unable to load your dashboard. Please try again.')
        }
      })
      .finally(() => {
        if (active) {
          setLoading(false)
        }
      })

    return () => {
      active = false
    }
  }, [])

  // The units offered for the running session; only shown once the student
  // has reported, so a failure simply keeps the section hidden.
  useEffect(() => {
    let active = true

    api
      .get('/me/session-units')
      .then((response) => {
        if (active) {
          setSessionUnits(response.data)
        }
      })
      .catch(() => {
        // The section stays hidden without one.
      })

    return () => {
      active = false
    }
  }, [])

  const profile = data?.profile ?? null
  const session = data?.academic?.session ?? null
  const year = data?.academic?.year ?? null
  const stats = data?.stats ?? { active: 0, completed: 0, courses: 0, units: 0 }
  const enrolments = data?.enrolments ?? []
  const report = data?.report_session ?? null

  // Record a "report session" registration. The endpoint only inserts a
  // row; the enrolment itself is untouched.
  const handleReport = async () => {
    setReporting(true)

    try {
      const { data: response } = await api.post('/me/report-session')

      toast.success(
        `Reported for session: module ${response.registration.module_no} ` +
          `(${response.registration.course_stage_code})`,
      )
      setData((current) =>
        current
          ? {
              ...current,
              report_session: {
                ...current.report_session,
                can_report: false,
                reason: 'You have already reported for this session.',
              },
            }
          : current,
      )

      // The session units appear the moment the student has reported.
      api
        .get('/me/session-units')
        .then((refresh) => {
          setSessionUnits(refresh.data)
        })
        .catch(() => {
          // The units section stays hidden; the report itself succeeded.
        })
    } catch (error) {
      toast.error(
        error.response?.data?.message ??
          error.response?.data?.errors?.session?.[0] ??
          'Unable to report for this session.',
      )
    } finally {
      setReporting(false)
    }
  }

  const offeredUnits = sessionUnits?.reported ? sessionUnits.units : []

  const toggleUnit = (unitId, checked) => {
    setSelectedUnits((current) => {
      const next = new Set(current)

      if (checked) {
        next.add(unitId)
      } else {
        next.delete(unitId)
      }

      return next
    })
  }

  // Register the ticked units. The server only records units that are
  // offered and not yet registered, so the response lists the new ones.
  const handleRegisterUnits = async () => {
    setRegistering(true)

    try {
      const { data } = await api.post('/me/session-units', {
        course_units: [...selectedUnits],
      })

      const recorded = new Set(data.registered)

      setSessionUnits((current) =>
        current
          ? {
              ...current,
              units: current.units.map((unit) =>
                recorded.has(unit.course_unit_id)
                  ? { ...unit, registered: true }
                  : unit,
              ),
            }
          : current,
      )
      setSelectedUnits(new Set())

      toast.success(
        recorded.size > 0
          ? `Registered ${recorded.size} unit${recorded.size === 1 ? '' : 's'} ` +
              `for ${sessionUnits?.session?.name ?? 'this session'}.`
          : 'The selected units were already registered.',
      )
    } catch (error) {
      toast.error(
        error.response?.data?.message ??
          error.response?.data?.errors?.course_units?.[0] ??
          'Unable to register the selected units.',
      )
    } finally {
      setRegistering(false)
    }
  }

  const firstName =
    (profile?.first_name || user?.name || '').trim().split(' ')[0] || 'there'
  const admissionNo = profile?.login_id ?? user?.login_id

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div>
        <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
          Student Dashboard
        </p>
        <h1 className="text-2xl font-semibold tracking-tight">
          Welcome back, {firstName}
        </h1>
        <p className="text-sm text-muted-foreground">
          {admissionNo ? `Admission number ${admissionNo}` : user?.email}
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
        <CardContent className="flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted">
              <CalendarDays
                className="h-5 w-5 text-muted-foreground"
                aria-hidden="true"
              />
            </span>
            <div>
              <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                Current Term
              </p>
              <p className="font-medium">
                {session?.name ?? 'No active term'}
              </p>
              <p className="text-sm text-muted-foreground">
                {year?.name ?? 'No active academic year'}
                {session &&
                  ` · ${formatDate(session.start_date)} – ${formatDate(session.end_date)}`}
              </p>
            </div>
          </div>

          <div className="flex flex-col items-start gap-1 sm:items-end">
            {report?.year_no && report?.session_no && (
              <div>
                <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                  Your Level
                </p>
                <p className="text-sm font-medium">
                  Year {report.year_no} · Session {report.session_no}
                </p>
              </div>
            )}
            {/* Only offered when the student can actually act on it — the
                server decides eligibility (stage open + window live). */}
            {report?.can_report ? (
              <button
                type="button"
                onClick={handleReport}
                disabled={reporting || loading}
                className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <ClipboardList className="h-4 w-4" aria-hidden="true" />
                {reporting ? 'Reporting…' : 'Report Session'}
              </button>
            ) : (
              report?.reason && (
                <p className="max-w-xs text-xs text-muted-foreground sm:text-right">
                  {report.reason}
                </p>
              )
            )}
          </div>
        </CardContent>
      </Card>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label="Active Enrolments"
          value={stats.active}
          icon={ClipboardList}
        />
        <StatCard label="Courses" value={stats.courses} icon={GraduationCap} />
        <StatCard label="Units Covered" value={stats.units} icon={BookOpen} />
        <StatCard
          label="Completed"
          value={stats.completed}
          icon={BadgeCheck}
        />
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>My Enrolments</CardTitle>
          </CardHeader>
          <CardContent>
            {loading ? (
              <p className="text-sm text-muted-foreground">Loading…</p>
            ) : enrolments.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                No enrolments yet. They appear here once the registrar enrols
                you for a course.
              </p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b text-left text-muted-foreground">
                      <th className="pb-3 font-medium">Course</th>
                      <th className="pb-3 font-medium">Curriculum</th>
                      <th className="pb-3 font-medium">Term</th>
                      <th className="pb-3 font-medium">Level</th>
                      <th className="pb-3 font-medium">Enrolled On</th>
                      <th className="pb-3 font-medium">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {enrolments.map((enrolment) => (
                      <tr key={enrolment.id}>
                        <td className="py-3 pr-4">
                          <p className="font-medium">
                            {enrolment.course_name ?? '—'}
                          </p>
                          {enrolment.course_code && (
                            <p className="text-xs text-muted-foreground">
                              {enrolment.course_code}
                            </p>
                          )}
                        </td>
                        <td className="py-3 pr-4 text-muted-foreground">
                          {enrolment.curriculum_name ?? '—'}
                        </td>
                        <td className="py-3 pr-4 text-muted-foreground">
                          {enrolment.academic_session_name ?? '—'}
                        </td>
                        <td className="py-3 pr-4 text-muted-foreground">
                          {enrolment.certification_level_name ?? '—'}
                        </td>
                        <td className="py-3 pr-4 whitespace-nowrap text-muted-foreground">
                          {formatDate(enrolment.enrolment_date)}
                        </td>
                        <td className="py-3">
                          <StatusBadge
                            status={enrolment.status}
                            styles={ENROLMENT_STATUS_STYLES}
                          />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>

        {sessionUnits?.reported && (
          <Card>
            <CardHeader>
              <CardTitle>Session Units</CardTitle>
              <p className="text-sm text-muted-foreground">
                {offeredUnits.length > 0
                  ? `Units taught in module ${sessionUnits.module_no} this session — tick to register.`
                  : `No units are taught in module ${sessionUnits.module_no} for this session yet.`}
              </p>
              {offeredUnits.length > 0 && (
                <button
                  type="button"
                  onClick={handleRegisterUnits}
                  disabled={registering || selectedUnits.size === 0}
                  className="mt-2 inline-flex self-start items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  <ClipboardList className="h-4 w-4" aria-hidden="true" />
                  {registering
                    ? 'Registering…'
                    : `Register Units${selectedUnits.size > 0 ? ` (${selectedUnits.size})` : ''}`}
                </button>
              )}
            </CardHeader>
            {offeredUnits.length > 0 && (
              <CardContent className="space-y-2">
                {offeredUnits.map((unit) => (
                  <label
                    key={unit.course_unit_id}
                    className={cn(
                      'flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border px-3 py-2.5',
                      unit.registered
                        ? 'border-emerald-500/30 bg-emerald-500/5'
                        : 'cursor-pointer hover:bg-muted/50',
                    )}
                  >
                    <Checkbox
                      checked={
                        unit.registered || selectedUnits.has(unit.course_unit_id)
                      }
                      disabled={unit.registered}
                      onChange={(event) =>
                        toggleUnit(unit.course_unit_id, event.target.checked)
                      }
                    />
                    <span className="font-mono text-xs text-muted-foreground">
                      {unit.code}
                    </span>
                    <span className="min-w-0 flex-1 text-sm font-medium">
                      {unit.name}
                    </span>
                    <UnitTypeBadge type={unit.unit_type} />
                    <span className="text-xs text-muted-foreground">
                      {unit.credit_factor} cr
                    </span>
                    {unit.registered && (
                      <span className="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                        <BadgeCheck className="h-3.5 w-3.5" aria-hidden="true" />
                        Registered
                      </span>
                    )}
                  </label>
                ))}
              </CardContent>
            )}
          </Card>
        )}
      </div>
    </div>
  )
}
