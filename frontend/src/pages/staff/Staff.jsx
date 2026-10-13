import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { Button, buttonVariants } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import api from '@/lib/api'
import { cn } from '@/lib/utils'

function StatusBadge({ active }) {
  return (
    <span
      className={cn(
        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
        active
          ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
          : 'bg-muted text-muted-foreground',
      )}
    >
      {active ? 'Active' : 'Inactive'}
    </span>
  )
}

export default function Staff() {
  const [staff, setStaff] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [deletingId, setDeletingId] = useState(null)

  useEffect(() => {
    api
      .get('/staff')
      .then(({ data }) => setStaff(data.staff))
      .catch(() =>
        setLoadError('Unable to load staff. Please try again.'),
      )
      .finally(() => setLoading(false))
  }, [])

  const handleDelete = async (staffMember) => {
    if (
      !window.confirm(
        `Delete the "${staffMember.employment_no}" staff record for "${staffMember.name}"?`,
      )
    ) {
      return
    }

    setDeletingId(staffMember.id)

    try {
      await api.delete(`/staff/${staffMember.id}`)

      setStaff((current) =>
        current.filter((item) => item.id !== staffMember.id),
      )
      toast.success(`Deleted "${staffMember.employment_no}"`)
    } catch {
      toast.error(
        `Unable to delete "${staffMember.employment_no}". Please try again.`,
      )
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Staff</h1>
          <p className="text-sm text-muted-foreground">
            Manage the school&apos;s teaching and non-teaching staff.
          </p>
        </div>
        <Link to="/staff/create" className={buttonVariants()}>
          <Plus aria-hidden="true" />
          New Staff Member
        </Link>
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
          <CardTitle>All Staff</CardTitle>
          <CardDescription>
            {loading
              ? 'Loading staff…'
              : `${staff.length} ${
                  staff.length === 1 ? 'staff member' : 'staff members'
                } total`}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : staff.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              No staff members yet. Create the first one with the button
              above.
            </p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b text-left text-muted-foreground">
                  <th className="pb-3 font-medium">Employee No</th>
                  <th className="pb-3 font-medium">Name</th>
                  <th className="pb-3 font-medium">Department</th>
                  <th className="pb-3 font-medium">Position</th>
                  <th className="pb-3 font-medium">Status</th>
                  <th className="pb-3 text-right font-medium">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y">
                {staff.map((staffMember) => (
                  <tr key={staffMember.id}>
                    <td className="py-3 text-muted-foreground">
                      {staffMember.employment_no}
                    </td>
                    <td className="py-3 font-medium">{staffMember.name}</td>
                    <td className="py-3 text-muted-foreground">
                      {staffMember.department_name ?? '—'}
                    </td>
                    <td className="py-3 text-muted-foreground">
                      {staffMember.position ?? '—'}
                    </td>
                    <td className="py-3">
                      <StatusBadge active={staffMember.is_active} />
                    </td>
                    <td className="py-3">
                      <div className="flex items-center justify-end gap-2">
                        <Link
                          to={`/staff/${staffMember.id}/edit`}
                          className={buttonVariants({
                            variant: 'ghost',
                            size: 'sm',
                          })}
                        >
                          <Pencil aria-hidden="true" />
                          Edit
                        </Link>
                        <Button
                          variant="destructive"
                          size="sm"
                          disabled={deletingId === staffMember.id}
                          onClick={() => handleDelete(staffMember)}
                        >
                          <Trash2 aria-hidden="true" />
                          {deletingId === staffMember.id
                            ? 'Deleting…'
                            : 'Delete'}
                        </Button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
