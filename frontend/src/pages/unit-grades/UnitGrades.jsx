import { useCallback, useEffect, useState } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { FormField } from '@/components/form-field'
import { Input } from '@/components/ui/input'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { Select } from '@/components/ui/select'
import api, { loadErrorMessage } from '@/lib/api'
import { useAuth } from '@/context/AuthContext'

// The two papers a unit can be graded on; each type has exam numbers 1–3.
const EXAM_TYPES = [
  { type: 'theory', label: 'Theory' },
  { type: 'practical', label: 'Practical' },
]

const EXAM_NUMBERS = [1, 2, 3]

/** The score recorded for one paper slot, or '' when it is still open. */
function scoreFor(data, type, no) {
  const current = (data?.grades ?? []).find(
    (grade) => grade.exam_type === type && grade.exam_no === no,
  )
  return current ? String(current.mark) : ''
}

export default function UnitGrades() {
  const { can } = useAuth()
  const [sessions, setSessions] = useState([])
  const [sessionId, setSessionId] = useState(null)
  const [unit, setUnit] = useState(null) // { id, name } — the course-unit link
  const [student, setStudent] = useState(null) // { id, name }
  const [examType, setExamType] = useState('theory')
  const [examNo, setExamNo] = useState(1)
  const [score, setScore] = useState('')
  const [consoleData, setConsoleData] = useState(null) // { session, student, unit, grades }
  const [loadingConsole, setLoadingConsole] = useState(false)
  const [saving, setSaving] = useState(false)

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
  // session — the only units grading can act on. Searched server-side
  // like the other reference pickers.
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

  // Students come from the unit's registrations: whoever did not
  // register the unit never appears, so every mark saved stays valid.
  const fetchStudentOptions = useCallback(
    async (query, signal) => {
      if (!sessionId || !unit) {
        return []
      }
      const { data } = await api.get('/unit-grades/students', {
        params: {
          academic_session_id: sessionId,
          course_unit_id: unit.id,
          ...(query ? { search: query } : {}),
        },
        signal,
      })
      return (data.students ?? []).map((item) => ({
        id: item.id,
        name: `${item.name} — ${item.admission_no ?? ''}`,
      }))
    },
    [sessionId, unit],
  )

  // Loads one student-unit console and prefills the score box with
  // whatever is recorded for the currently picked paper — saving then
  // replaces it, or removes it when cleared.
  async function loadConsole(activeSessionId, activeUnit, activeStudent) {
    setLoadingConsole(true)
    setConsoleData(null)
    try {
      const { data } = await api.get('/unit-grades', {
        params: {
          academic_session_id: activeSessionId,
          course_unit_id: activeUnit.id,
          student_id: activeStudent.id,
        },
      })
      setConsoleData(data)
      setScore(scoreFor(data, examType, examNo))
    } catch (error) {
      toast.error(loadErrorMessage(error))
    } finally {
      setLoadingConsole(false)
    }
  }

  function handleSessionChange(nextId) {
    setSessionId(nextId)
    // The unit list and its students are session-scoped: start over.
    setUnit(null)
    setStudent(null)
    setConsoleData(null)
    setScore('')
  }

  function handleUnitChange(nextId, option) {
    setUnit(nextId ? { id: nextId, name: option?.name ?? '' } : null)
    setStudent(null)
    setConsoleData(null)
    setScore('')
  }

  function handleStudentChange(nextId, option) {
    setStudent(nextId ? option : null)
    if (nextId && unit) {
      loadConsole(sessionId, unit, option)
    } else {
      setConsoleData(null)
      setScore('')
    }
  }

  function handleExamTypeChange(nextType) {
    setExamType(nextType)
    setScore(scoreFor(consoleData, nextType, examNo))
  }

  function handleExamNoChange(nextNo) {
    setExamNo(nextNo)
    setScore(scoreFor(consoleData, examType, nextNo))
  }

  async function handleSave(event) {
    event.preventDefault()

    // The save endpoint stores the full slot set: keep every other
    // recorded paper, then replace (or clear) the selected one.
    const marks = (consoleData?.grades ?? [])
      .filter((grade) => !(grade.exam_type === examType && grade.exam_no === examNo))
      .map(({ exam_type, exam_no, mark }) => ({ exam_type, exam_no, mark }))

    if (score !== '') {
      marks.push({ exam_type: examType, exam_no: examNo, mark: Number(score) })
    }

    if (marks.length === 0) {
      toast.error('Enter at least one mark before saving.')
      return
    }

    setSaving(true)
    try {
      await api.put('/unit-grades', {
        academic_session_id: sessionId,
        student_id: student.id,
        course_unit_id: unit.id,
        marks,
      })
      toast.success('Marks saved.')
      await loadConsole(sessionId, unit, student)
    } catch (error) {
      toast.error(loadErrorMessage(error))
    } finally {
      setSaving(false)
    }
  }

  const grades = consoleData?.grades ?? []
  const existingMark =
    grades.find((grade) => grade.exam_type === examType && grade.exam_no === examNo) ?? null
  const canSave = score !== '' || existingMark !== null

  let scoreHelp = 'Choose a student to load their recorded marks.'
  if (consoleData) {
    scoreHelp = 'Enter a score between 0 and 100 to enable saving.'
    if (canSave && existingMark) {
      scoreHelp =
        'Saving replaces this paper’s recorded score — clear the field and save to remove it.'
    } else if (canSave) {
      scoreHelp = 'Saving records this paper on the unit.'
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Unit Grading</h1>
        <p className="text-sm text-muted-foreground">
          Award theory and practical marks for a student&apos;s registered units. A unit is fully
          graded once three papers of each type have been recorded.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Grading Console</CardTitle>
          <CardDescription>
            Pick the session, the unit and a student who registered it, then record one paper at a
            time — type, exam number and score.
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
                Only units students registered for this session can be graded.
              </p>
            </FormField>
          </div>

          {loadingConsole && <p className="text-sm text-muted-foreground">Loading marks…</p>}

          {unit && (
            <form onSubmit={handleSave} className="space-y-5" noValidate>
              <div className="grid gap-5 lg:grid-cols-2">
                <FormField id="exam_type" label="Exam Type" required>
                  <Select
                    id="exam_type"
                    value={examType}
                    onChange={(event) => handleExamTypeChange(event.target.value)}
                  >
                    {EXAM_TYPES.map(({ type, label }) => (
                      <option key={type} value={type}>
                        {label}
                      </option>
                    ))}
                  </Select>
                </FormField>

                <FormField id="exam_no" label="Exam Number" required>
                  <Select
                    id="exam_no"
                    value={examNo}
                    onChange={(event) => handleExamNoChange(Number(event.target.value))}
                  >
                    {EXAM_NUMBERS.map((no) => (
                      <option key={no} value={no}>
                        {no}
                      </option>
                    ))}
                  </Select>
                </FormField>

                <FormField id="student_id" label="Student" required>
                  <SearchableSelect
                    key={unit.id}
                    id="student_select"
                    value={student?.id ?? null}
                    selectedLabel={student?.name ?? '-- Choose a student --'}
                    onChange={handleStudentChange}
                    fetchOptions={fetchStudentOptions}
                    placeholder="-- Choose a student --"
                  />
                </FormField>

                <FormField id="mark" label="Score" required>
                  <Input
                    id="mark"
                    type="number"
                    min="0"
                    max="100"
                    step="0.5"
                    placeholder="0–100"
                    value={score}
                    onChange={(event) => setScore(event.target.value)}
                  />
                </FormField>
              </div>

              <p className="text-xs text-muted-foreground">{scoreHelp}</p>

              {can('unit-grades.update') && (
                <div className="flex justify-end">
                  <Button type="submit" disabled={saving || !consoleData || !canSave}>
                    {saving ? 'Saving…' : 'Save Mark'}
                  </Button>
                </div>
              )}
            </form>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
