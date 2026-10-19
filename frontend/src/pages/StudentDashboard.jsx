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
import { useAuth } from '@/context/AuthContext'
import api from '@/lib/api'
import { cn } from '@/lib/utils'

const ENROLMENT_STATUS_STYLES = {
  ENROLLED: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
  COMPLETED: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
  DROPPED: 'bg-destructive/10 text-destructive',
}

const PROFILE_STATUS_STYLES = {
  active: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
  inactive: 'bg-muted text-muted-foreground',
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

function capitalize(value) {
  return value ? value.charAt(0).toUpperCase() + value.slice(1).toLowerCase() : '—'
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

function DetailRow({ label, children }) {
  return (
    <div className="flex items-center justify-between gap-3 py-2.5">
      <span className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
        {label}
      </span>
      <span className="text-right text-sm">{children}</span>
    </div>
  )
}

export default function StudentDashboard() {
  const { user } = useAuth()
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')

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

  const profile = data?.profile ?? null
  const session = data?.academic?.session ?? null
  const year = data?.academic?.year ?? null
  const stats = data?.stats ?? { active: 0, completed: 0, courses: 0, units: 0 }
  const enrolments = data?.enrolments ?? []

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
            </div>
          </div>
          <p className="text-sm text-muted-foreground">
            {year?.name ?? 'No active academic year'}
            {session &&
              ` · ${formatDate(session.start_date)} – ${formatDate(session.end_date)}`}
          </p>
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

        <Card>
          <CardHeader>
            <CardTitle>My Profile</CardTitle>
          </CardHeader>
          <CardContent>
            {!profile ? (
              <p className="text-sm text-muted-foreground">
                No student profile is linked to this account yet. Contact the
                registrar.
              </p>
            ) : (
              <>
                <div className="divide-y">
                  <DetailRow label="Status">
                    <StatusBadge
                      status={profile.status}
                      styles={PROFILE_STATUS_STYLES}
                    />
                  </DetailRow>
                  <DetailRow label="Gender">
                    {capitalize(profile.gender)}
                  </DetailRow>
                  <DetailRow label="Date of Birth">
                    {formatDate(profile.date_of_birth)}
                  </DetailRow>
                  <DetailRow label="County">
                    {profile.county ?? '—'}
                  </DetailRow>
                  <DetailRow label="Phone">
                    {profile.phone ?? '—'}
                  </DetailRow>
                  <DetailRow label="National ID">
                    {profile.national_id ?? '—'}
                  </DetailRow>
                  <DetailRow label="Email">
                    {profile.email ?? user?.email ?? '—'}
                  </DetailRow>
                </div>

                {profile.sponsored && (
                  <div className="mt-4 rounded-xl border bg-muted/40 px-4 py-3">
                    <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                      Sponsorship
                    </p>
                    <p className="mt-1 text-sm font-medium">
                      {profile.sponsored_by ?? 'Sponsored'}
                    </p>
                    {profile.index_no && (
                      <p className="text-xs text-muted-foreground">
                        Index no. {profile.index_no}
                        {profile.exam_year && ` · Exam year ${profile.exam_year}`}
                      </p>
                    )}
                  </div>
                )}
              </>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
