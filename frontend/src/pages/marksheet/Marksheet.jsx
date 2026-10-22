import { useCallback, useEffect, useState } from 'react'
import { toast } from 'sonner'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { FormField } from '@/components/form-field'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { Select } from '@/components/ui/select'
import api, { loadErrorMessage } from '@/lib/api'

export default function Marksheet() {
  const [sessions, setSessions] = useState([])
  const [sessionId, setSessionId] = useState(null)
  const [unit, setUnit] = useState(null) // { id, name }
  const [sheet, setSheet] = useState(null) // { session, unit, rows }
  const [loading, setLoading] = useState(false)

  // The session list only ever holds a couple of rows per year, so one
  // unfiltered page is enough; the active session is preselected.
  useEffect(() => {
    api
      .get('/academic-sessions', { params: { per_page: 100 } })
      .then(({ data }) => {
        const list = data.academic_sessions ?? []
        setSessions(list)
        const active = list.find((session) => session.is_active)
        if (active) {
          setSessionId(active.id)
        }
      })
      .catch((error) => toast.error(loadErrorMessage(error)))
  }, [])

  // Units are the course-unit links students registered for the picked
  // session. Searched server-side like the other reference pickers.
  const fetchUnitOptions = useCallback(
    async (query, signal) => {
      const { data } = await api.get('/unit-grades/units', {
        params: {
          ...(sessionId ? { academic_session_id: sessionId } : {}),
          ...(query ? { search: query } : {}),
        },
        signal,
      })
      return (data.units ?? []).map((item) => ({
        id: item.course_unit_id,
        name: `${item.code} — ${item.name}`,
      }))
    },
    [sessionId],
  )

  // Loads the sheet for one session + unit: every student who registered
  // it, their papers per slot and each type's average.
  async function loadSheet(activeSessionId, activeUnit) {
    setLoading(true)
    setSheet(null)
    try {
      const { data } = await api.get('/unit-grades/sheet', {
        params: {
          academic_session_id: activeSessionId,
          course_unit_id: activeUnit.id,
        },
      })
      setSheet(data)
    } catch (error) {
      toast.error(loadErrorMessage(error))
    } finally {
      setLoading(false)
    }
  }

  function handleSessionChange(nextId) {
    setSessionId(nextId)
    // The unit list is session-scoped: start over.
    setUnit(null)
    setSheet(null)
  }

  function handleUnitChange(nextId, option) {
    const nextUnit = nextId ? { id: nextId, name: option?.name ?? '' } : null
    setUnit(nextUnit)
    if (nextUnit && sessionId) {
      loadSheet(sessionId, nextUnit)
    } else {
      setSheet(null)
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Marksheet</h1>
        <p className="text-sm text-muted-foreground">
          The class marks sheet for one unit: every registered student&apos;s papers with each
          type&apos;s average.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Marks Sheet</CardTitle>
          <CardDescription>
            Pick the session and the unit to see the whole class in one view.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-5">
          <div className="grid gap-5 lg:grid-cols-2">
            <FormField id="academic_session_id" label="Academic Session" required>
              <Select
                id="academic_session_id"
                value={sessionId ?? ''}
                onChange={(event) => handleSessionChange(Number(event.target.value))}
              >
                <option value="" disabled>
                  — Choose a session —
                </option>
                {sessions.map((session) => (
                  <option key={session.id} value={session.id}>
                    {session.name}
                    {session.is_active ? ' (active)' : ''}
                  </option>
                ))}
              </Select>
            </FormField>

            <FormField id="course_unit_id" label="Unit" required>
              <SearchableSelect
                id="unit_select"
                value={unit?.id ?? null}
                selectedLabel={unit?.name ?? '-- Choose a unit --'}
                onChange={handleUnitChange}
                fetchOptions={fetchUnitOptions}
                placeholder="-- Choose a unit --"
              />
              <p className="text-xs text-muted-foreground">
                The sheet lists only students who registered the unit for this session.
              </p>
            </FormField>
          </div>

          {loading && <p className="text-sm text-muted-foreground">Loading sheet…</p>}

          {sheet &&
            (sheet.rows.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                No student has registered {sheet.unit?.code ?? 'this unit'} for this session yet.
              </p>
            ) : (
              <div className="overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[1100px] text-sm">
                  <thead>
                    <tr className="border-b text-left text-xs uppercase tracking-wider text-muted-foreground">
                      <th rowSpan={2} scope="col" className="whitespace-nowrap px-3 py-2 font-semibold">
                        S/N
                      </th>
                      <th rowSpan={2} scope="col" className="whitespace-nowrap px-3 py-2 font-semibold">
                        ADM NO.
                      </th>
                      <th rowSpan={2} scope="col" className="whitespace-nowrap px-3 py-2 font-semibold">
                        Name
                      </th>
                      <th
                        colSpan={4}
                        scope="colgroup"
                        className="whitespace-nowrap border-l bg-muted px-3 py-2 text-center font-semibold"
                      >
                        Theory Marks (100%)
                      </th>
                      <th
                        colSpan={4}
                        scope="colgroup"
                        className="whitespace-nowrap border-l bg-orange-100 px-3 py-2 text-center font-semibold text-orange-900 dark:bg-orange-950/50 dark:text-orange-300"
                      >
                        Practical Marks (100%)
                      </th>
                    </tr>
                    <tr className="border-b text-left text-xs uppercase tracking-wider text-muted-foreground">
                      <th scope="col" className="whitespace-nowrap border-l bg-muted/60 px-3 py-2 text-center font-semibold">
                        CAM 1
                      </th>
                      <th scope="col" className="whitespace-nowrap bg-muted/60 px-3 py-2 text-center font-semibold">
                        CAM 2
                      </th>
                      <th scope="col" className="whitespace-nowrap bg-muted/60 px-3 py-2 text-center font-semibold">
                        CAM 3
                      </th>
                      <th
                        scope="col"
                        className="whitespace-nowrap px-3 py-2 text-center font-semibold text-red-600 dark:text-red-400"
                      >
                        AVERAGE
                      </th>
                      <th scope="col" className="whitespace-nowrap border-l bg-orange-100/60 px-3 py-2 text-center font-semibold text-orange-900 dark:bg-orange-950/40 dark:text-orange-300">
                        PRACT 1
                      </th>
                      <th scope="col" className="whitespace-nowrap bg-orange-100/60 px-3 py-2 text-center font-semibold text-orange-900 dark:bg-orange-950/40 dark:text-orange-300">
                        PRACT 2
                      </th>
                      <th scope="col" className="whitespace-nowrap bg-orange-100/60 px-3 py-2 text-center font-semibold text-orange-900 dark:bg-orange-950/40 dark:text-orange-300">
                        PRACT 3
                      </th>
                      <th
                        scope="col"
                        className="whitespace-nowrap px-3 py-2 text-center font-semibold text-red-600 dark:text-red-400"
                      >
                        AVERAGE
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {sheet.rows.map((row, index) => (
                      <tr key={row.student_id} className="border-b last:border-b-0">
                        <td className="whitespace-nowrap px-3 py-2 text-muted-foreground">
                          {index + 1}
                        </td>
                        <td className="whitespace-nowrap px-3 py-2">{row.admission_no ?? '—'}</td>
                        <td className="whitespace-nowrap px-3 py-2">{row.name ?? '—'}</td>
                        {row.theory.map((mark, slot) => (
                          <td
                            key={`theory-${slot}`}
                            className={`whitespace-nowrap px-3 py-2 text-center tabular-nums ${
                              slot === 0 ? 'border-l' : ''
                            }`}
                          >
                            {mark ?? '—'}
                          </td>
                        ))}
                        <td className="whitespace-nowrap px-3 py-2 text-center font-semibold tabular-nums text-red-600 dark:text-red-400">
                          {row.theory_average ?? '—'}
                        </td>
                        {row.practical.map((mark, slot) => (
                          <td
                            key={`practical-${slot}`}
                            className={`whitespace-nowrap px-3 py-2 text-center tabular-nums ${
                              slot === 0 ? 'border-l' : ''
                            }`}
                          >
                            {mark ?? '—'}
                          </td>
                        ))}
                        <td className="whitespace-nowrap px-3 py-2 text-center font-semibold tabular-nums text-red-600 dark:text-red-400">
                          {row.practical_average ?? '—'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ))}
        </CardContent>
      </Card>
    </div>
  )
}
