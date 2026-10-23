import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { Button, buttonVariants } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { ListPagination } from '@/components/list-pagination'
import { ListSearch } from '@/components/list-search'
import { SortableTh } from '@/components/sortable-th'
import { useList } from '@/hooks/use-list'
import api from '@/lib/api'
import { cn } from '@/lib/utils'
import { useAuth } from '@/context/AuthContext'

const EMPLOYMENT_TYPE_LABELS = {
  full_time: 'Full-time',
  part_time: 'Part-time',
  contract: 'Contract',
  intern: 'Intern',
  volunteer: 'Volunteer',
}

const ROLE_LABELS = {
  admin: 'Admin',
  trainer: 'Trainer',
  finance: 'Finance',
}

const STATUS_STYLES = {
  active: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
  probation: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
  suspended: 'bg-orange-500/10 text-orange-600 dark:text-orange-400',
  inactive: 'bg-muted text-muted-foreground',
  terminated: 'bg-destructive/10 text-destructive',
  retired: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
}

function StatusBadge({ status }) {
  const label = status
    ? status.charAt(0).toUpperCase() + status.slice(1)
    : 'Unknown'

  return (
    <span
      className={cn(
        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
        STATUS_STYLES[status] ?? STATUS_STYLES.inactive,
      )}
    >
      {label}
    </span>
  )
}

export default function Staff() {
  const { can } = useAuth()
  const [deletingId, setDeletingId] = useState(null)

  const {
    items: staff,
    meta,
    loading,
    loadError,
    search,
    setSearch,
    goToPage,
    sort,
    order,
    toggleSort,
    refresh,
  } = useList({
    resource: 'staff',
    endpoint: '/staff',
    errorMessage: 'Unable to load staff. Please try again.',
  })

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

      refresh()
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
    <div className="mx-auto max-w-6xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Staff</h1>
          <p className="text-sm text-muted-foreground">
            Manage the school&apos;s teaching and non-teaching staff.
          </p>
        </div>
        {can('staff.create') && (
          <Link to="/staff/create" className={buttonVariants()}>
            <Plus aria-hidden="true" />
            New Staff Member
          </Link>
        )}
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
        <CardHeader className="flex flex-row items-center justify-between gap-4">
          <CardTitle>All Staff</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search staff…"
          />
        </CardHeader>
        <CardContent className="space-y-4">
          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : staff.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {search
                ? `No staff members match “${search}”.`
                : 'No staff members yet. Create the first one with the button above.'}
            </p>
          ) : (
            <>
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <SortableTh
                      column="employment_no"
                      label="Employee No"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <SortableTh
                      column="name"
                      label="Name"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <th className="pb-3 font-medium">Role</th>
                    <th className="pb-3 font-medium">Department</th>
                    <th className="pb-3 font-medium">Position</th>
                    <th className="pb-3 font-medium">Type</th>
                    <SortableTh
                      column="employment_status"
                      label="Status"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
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
                        {ROLE_LABELS[staffMember.role] ?? '—'}
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {staffMember.department_name ?? '—'}
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {staffMember.position ?? '—'}
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {EMPLOYMENT_TYPE_LABELS[staffMember.employment_type] ??
                          '—'}
                      </td>
                      <td className="py-3">
                        <StatusBadge status={staffMember.employment_status} />
                      </td>
                      <td className="py-3">
                        <div className="flex items-center justify-end gap-2">
                          {can('staff.update') && (
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
                          )}
                          {can('staff.delete') && (
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
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>

              <ListPagination
                meta={meta}
                loading={loading}
                onPageChange={goToPage}
                label="staff members"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
