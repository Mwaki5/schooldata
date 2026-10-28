import { useCallback } from 'react'
import { SearchableSelect } from '@/components/ui/searchable-select'
import api from '@/lib/api'
import { studentLabel } from '@/lib/finance-constants'

/**
 * The one student picker every finance screen shares.
 *
 * It searches the backend's finance-desk endpoints — which answer with
 * just a composed name and admission number — rather than the staff
 * roster, because the finance desk bills students without ever holding
 * `students.view`.
 *
 * The endpoint is passed in so each desk rides its own permission: the
 * billing screens use `/invoices/students`, the cash desk and the
 * statement screen use `/payments/students`.
 *
 * @param {object} props
 * @param {string} props.endpoint Finance-desk student search path.
 * @param {number|string|null} props.value Selected student id, or null.
 * @param {{name: string, login_id: string|null}|null} props.student
 *        The full selected row, kept so the label survives re-renders.
 * @param {(student: object|null) => void} props.onChange
 */
export function StudentPicker({ id, endpoint, value, student, onChange, ...props }) {
  const fetchOptions = useCallback(
    (query, signal) =>
      api
        .get(endpoint, {
          params: query ? { search: query } : {},
          signal,
        })
        .then(({ data }) => data.students ?? []),
    [endpoint],
  )

  return (
    <SearchableSelect
      id={id}
      value={value}
      selectedLabel={studentLabel(student)}
      onChange={(_value, option) => onChange(option ?? null)}
      fetchOptions={fetchOptions}
      placeholder="— Choose a student —"
      searchPlaceholder="Type a name or admission number…"
      {...props}
    />
  )
}
