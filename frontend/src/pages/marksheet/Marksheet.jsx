import { Download } from 'lucide-react'
import { useEffect, useMemo, useRef, useState } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { FormField } from '@/components/form-field'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { Select } from '@/components/ui/select'
import api, { loadErrorMessage } from '@/lib/api'

const EXPORT_FORMATS = [
  { format: 'csv', label: 'CSV (.csv)' },
  { format: 'xlsx', label: 'Excel (.xlsx)' },
  { format: 'pdf', label: 'PDF (.pdf)' },
]

/**
 * SearchableSelect fetches over the network; these pickers filter the
 * session's already-loaded list instead, so the same signature wraps an
 * in-memory filter.
 */
function localOptions(options) {
  return (query) => {
    const needle = query.trim().toLowerCase()
    return Promise.resolve(
      needle ? options.filter((option) => option.name.toLowerCase().includes(needle)) : options,
    )
  }
}

export default function Marksheet() {
  const [sessions, setSessions] = useState([])
  const [sessionId, setSessionId] = useState(null)
  const [links, setLinks] = useState([]) // unit/course/curriculum rows of the session
  const [course, setCourse] = useState(null) // { id, name }
  const [curriculum, setCurriculum] = useState(null) // { id, name }
  const [unit, setUnit] = useState(null) // { id, name } — the course-unit link
  const [sheet, setSheet] = useState(null) // { session, unit, rows }
  const [loading, setLoading] = useState(false)
  const [exporting, setExporting] = useState(null) // format while a download runs
  const [exportOpen, setExportOpen] = useState(false)
  const exportRef = useRef(null)

  // The session list only ever holds a couple of rows per year; the
  // picker endpoint serves them all, and the active session is
  // preselected.
  useEffect(() => {
    api
      .get('/academic-sessions/options')
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

  // The session's registered course-unit links carry course and
  // curriculum too, so this one list feeds all three cascade pickers.
  useEffect(() => {
    if (!sessionId) {
      return undefined
    }

    let cancelled = false

    api
      .get('/unit-grades/units', { params: { academic_session_id: sessionId } })
      .then(({ data }) => {
        if (!cancelled) {
          setLinks(data.units ?? [])
        }
      })
      .catch((error) => {
        if (!cancelled) {
          toast.error(
            loadErrorMessage(
              error,
              'Units',
              'Could not load the units registered for this session.',
            ),
          )
        }
      })

    return () => {
      cancelled = true
    }
  }, [sessionId])

  // Close the export menu on any click outside it.
  useEffect(() => {
    if (!exportOpen) {
      return undefined
    }

    function handlePointerDown(event) {
      if (exportRef.current && !exportRef.current.contains(event.target)) {
        setExportOpen(false)
      }
    }

    document.addEventListener('mousedown', handlePointerDown)
    return () => document.removeEventListener('mousedown', handlePointerDown)
  }, [exportOpen])

  // Distinct courses among the session's links, then the picked
  // course's curricula, then that pair's units — one level per picker.
  const courseOptions = useMemo(() => {
    const seen = new Map()
    for (const link of links) {
      if (!seen.has(link.course_id)) {
        seen.set(link.course_id, { id: link.course_id, name: link.course })
      }
    }
    return [...seen.values()]
  }, [links])

  const curriculumOptions = useMemo(() => {
    if (!course) {
      return []
    }

    const seen = new Map()
    for (const link of links) {
      if (link.course_id === course.id && !seen.has(link.curriculum_id)) {
        seen.set(link.curriculum_id, { id: link.curriculum_id, name: link.curriculum })
      }
    }
    return [...seen.values()]
  }, [links, course])

  const unitOptions = useMemo(() => {
    if (!course || !curriculum) {
      return []
    }

    return links
      .filter(
        (link) => link.course_id === course.id && link.curriculum_id === curriculum.id,
      )
      .map((link) => ({
        id: link.course_unit_id,
        name: `${link.code} — ${link.name}`,
      }))
  }, [links, course, curriculum])

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

  // Downloads re-fetch from the API with the same filters — the whole
  // class, never a page of it — as the chosen file format.
  async function handleExport(format) {
    setExportOpen(false)
    setExporting(format)
    try {
      const response = await api.get('/unit-grades/sheet/export', {
        params: {
          academic_session_id: sessionId,
          course_unit_id: unit.id,
          format,
        },
        responseType: 'blob',
      })

      const disposition = response.headers?.['content-disposition'] ?? ''
      const filename = /filename="?([^";]+)"?/.exec(disposition)?.[1] ?? `marksheet.${format}`

      const url = URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url
      link.download = filename
      document.body.appendChild(link)
      link.click()
      link.remove()
      URL.revokeObjectURL(url)

      toast.success(`Marks sheet exported as ${format.toUpperCase()}.`)
    } catch (error) {
      toast.error(
        loadErrorMessage(error, 'Marks sheet', 'The export failed. Please try again.'),
      )
    } finally {
      setExporting(null)
    }
  }

  function handleSessionChange(nextId) {
    setSessionId(nextId)
    // The link list — and every picker derived from it — is session-scoped.
    setLinks([])
    setCourse(null)
    setCurriculum(null)
    setUnit(null)
    setSheet(null)
  }

  function handleCourseChange(nextId, option) {
    setCourse(nextId ? { id: nextId, name: option?.name ?? '' } : null)
    // Curricula and units hang off the course: start over below it.
    setCurriculum(null)
    setUnit(null)
    setSheet(null)
  }

  function handleCurriculumChange(nextId, option) {
    setCurriculum(nextId ? { id: nextId, name: option?.name ?? '' } : null)
    // Units belong to the course + curriculum pair.
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
          type&apos;s average, exportable as PDF, CSV or Excel.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Marks Sheet</CardTitle>
          <CardDescription>
            Pick the session, then narrow down by course and curriculum to the unit — the whole
            class appears in one view.
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

            <FormField id="course_id" label="Course" required>
              <SearchableSelect
                id="course_select"
                value={course?.id ?? null}
                selectedLabel={course?.name ?? '-- Choose a course --'}
                onChange={handleCourseChange}
                fetchOptions={localOptions(courseOptions)}
                placeholder="-- Choose a course --"
              />
              <p className="text-xs text-muted-foreground">
                Only courses with units students registered for this session.
              </p>
            </FormField>

            <FormField id="curriculum_id" label="Curriculum" required>
              {course ? (
                <SearchableSelect
                  key={`curriculum-${course.id}`}
                  id="curriculum_select"
                  value={curriculum?.id ?? null}
                  selectedLabel={curriculum?.name ?? '-- Choose a curriculum --'}
                  onChange={handleCurriculumChange}
                  fetchOptions={localOptions(curriculumOptions)}
                  placeholder="-- Choose a curriculum --"
                />
              ) : (
                <p className="text-sm text-muted-foreground">
                  Pick a course to list its curricula.
                </p>
              )}
            </FormField>

            <FormField id="course_unit_id" label="Unit" required>
              {curriculum ? (
                <SearchableSelect
                  key={`unit-${curriculum.id}`}
                  id="unit_select"
                  value={unit?.id ?? null}
                  selectedLabel={unit?.name ?? '-- Choose a unit --'}
                  onChange={handleUnitChange}
                  fetchOptions={localOptions(unitOptions)}
                  placeholder="-- Choose a unit --"
                />
              ) : (
                <p className="text-sm text-muted-foreground">
                  Pick a curriculum to list its units.
                </p>
              )}
              <p className="text-xs text-muted-foreground">
                The sheet lists students who registered the unit and have at least one mark
                recorded.
              </p>
            </FormField>
          </div>

          {loading && <p className="text-sm text-muted-foreground">Loading sheet…</p>}

          {sheet &&
            (sheet.rows.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                No student has a recorded mark for {sheet.unit?.code ?? 'this unit'} in this
                session yet.
              </p>
            ) : (
              <>
                <div className="flex justify-end">
                  <div ref={exportRef} className="relative inline-block">
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => setExportOpen((open) => !open)}
                      disabled={exporting !== null}
                    >
                      <Download />
                      {exporting ? 'Exporting…' : 'Export'}
                    </Button>
                    {exportOpen && (
                      <div className="absolute right-0 z-10 mt-1 w-44 overflow-hidden rounded-xl border bg-popover shadow-md">
                        {EXPORT_FORMATS.map(({ format, label }) => (
                          <button
                            key={format}
                            type="button"
                            className="block w-full px-3 py-2 text-left text-sm hover:bg-muted disabled:opacity-50"
                            disabled={exporting !== null}
                            onClick={() => handleExport(format)}
                          >
                            {label}
                          </button>
                        ))}
                      </div>
                    )}
                  </div>
                </div>

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
              </>
            ))}
        </CardContent>
      </Card>
    </div>
  )
}
