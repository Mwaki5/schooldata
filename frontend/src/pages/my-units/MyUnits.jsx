import { BookOpen } from 'lucide-react'
import { useEffect, useState } from 'react'
import PageLoader from '@/components/page-loader'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import api from '@/lib/api'
import { cn } from '@/lib/utils'

function TypeBadge({ core }) {
  return (
    <span
      className={cn(
        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
        core
          ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400'
          : 'bg-muted text-muted-foreground',
      )}
    >
      {core ? 'Core' : 'Elective'}
    </span>
  )
}

function moduleLabel(moduleNumber) {
  if (moduleNumber === null) {
    return 'No module'
  }

  return `Module ${moduleNumber}`
}

/**
 * The signed-in student's units, grouped by the course + curriculum pair
 * they are enrolled in, then sectioned by the number of modules each unit
 * is taught in. The backend scopes the payload to the requesting account
 * and only returns units that are currently offered.
 */
export default function MyUnits() {
  const [groups, setGroups] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)

  useEffect(() => {
    let active = true

    api
      .get('/me/units')
      .then(({ data }) => {
        if (active) {
          setGroups(data.groups ?? [])
        }
      })
      .catch(() => {
        if (active) {
          setLoadError('Unable to load your units. Please try again.')
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

  if (loading) {
    return <PageLoader />
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">My Units</h1>
        <p className="text-sm text-muted-foreground">
          Every unit offered by the courses you are enrolled in, grouped by
          course, curriculum and the module each unit is taught in.
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

      {groups.length === 0 && !loadError ? (
        <Card>
          <CardContent className="flex flex-col items-center gap-3 py-10 text-center">
            <BookOpen
              className="size-10 text-muted-foreground"
              aria-hidden="true"
            />
            <p className="text-sm font-medium">No units to show yet</p>
            <p className="max-w-md text-sm text-muted-foreground">
              Your units will appear here once you are enrolled in a course
              that offers them.
            </p>
          </CardContent>
        </Card>
      ) : (
        groups.map((group) => (
          <Card key={`${group.course.id}-${group.curriculum.id}`}>
            <CardHeader>
              <CardTitle>{group.course.name}</CardTitle>
              <CardDescription>
                {group.curriculum.name} · {group.stats.total}{' '}
                {group.stats.total === 1 ? 'unit' : 'units'} · {group.stats.core}{' '}
                core · {group.stats.elective} elective
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              {group.modules.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                  No units have been added to this curriculum yet.
                </p>
              ) : (
                group.modules.map((module) => (
                  <section key={module.modules_taught ?? 'none'}>
                    <div className="mb-2 flex items-baseline justify-between gap-3">
                      <h3 className="text-sm font-semibold">
                        {moduleLabel(module.modules_taught)}
                      </h3>
                      <span className="text-xs text-muted-foreground">
                        {module.total}{' '}
                        {module.total === 1 ? 'unit' : 'units'}
                      </span>
                    </div>

                    <div className="overflow-x-auto">
                      <table className="w-full text-sm">
                        <thead>
                          <tr className="border-b text-left text-muted-foreground">
                            <th className="pb-3 font-medium">Code</th>
                            <th className="pb-3 font-medium">Unit</th>
                            <th className="pb-3 font-medium">Type</th>
                            <th className="pb-3 font-medium">Hours</th>
                            <th className="pb-3 font-medium">Credit</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y">
                          {module.units.map((unit) => (
                            <tr key={unit.id}>
                              <td className="py-3 font-mono text-xs text-muted-foreground">
                                {unit.code}
                              </td>
                              <td className="py-3 font-medium">{unit.name}</td>
                              <td className="py-3">
                                <TypeBadge core={unit.core} />
                              </td>
                              <td className="py-3 text-muted-foreground">
                                {unit.taught_hours ?? '—'}
                              </td>
                              <td className="py-3 text-muted-foreground">
                                {unit.credit_factor ?? '—'}
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </section>
                ))
              )}
            </CardContent>
          </Card>
        ))
      )}
    </div>
  )
}
