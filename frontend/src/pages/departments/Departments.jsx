import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { buttonVariants } from '@/components/ui/button'
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

export default function Departments() {
  const { can } = useAuth()
  const [deletingId, setDeletingId] = useState(null)

  const {
    items: departments,
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
    resource: 'departments',
    endpoint: '/departments',
    errorMessage: 'Unable to load departments. Please try again.',
  })

  const handleDelete = async (department) => {
    if (!window.confirm(`Delete the "${department.name}" department?`)) {
      return
    }

    setDeletingId(department.id)

    try {
      await api.delete(`/departments/${department.id}`)

      refresh()
      toast.success(`Deleted "${department.name}"`)
    } catch {
      toast.error(
        `Unable to delete "${department.name}". Please try again.`,
      )
    } finally {
      setDeletingId(null)
    }
  }

  /** Row numbers keep counting across pages, not per page. */
  const rowOffset = meta ? (meta.current_page - 1) * meta.per_page : 0

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight  text-muted-foreground">
        Manage academic departments.
          </h1>
          <p className="text-sm text-muted-foreground">
            Manage academic departments and their heads of department.
          </p>
        </div>
        {can('departments.create') && (
          <Link to="/departments/create" className={buttonVariants()}>
            <Plus aria-hidden="true" />
            New Department
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
          <CardTitle>All Departments</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search departments…"
          />
        </CardHeader>
        <CardContent className="space-y-4">
          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : departments.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {search
                ? `No departments match “${search}”.`
                : 'No departments yet. Create the first one with the button above.'}
            </p>
          ) : (
            <>
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <th className="py-1 px-2 font-medium">#</th>
                    <SortableTh
                      column="name"
                      label="Department"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                      className="py-1"
                    />
                    <th className="py-1 font-medium">HOD</th>
                    <SortableTh
                      column="is_active"
                      label="Status"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                      className="py-1"
                    />
                    <th className="py-1 text-right font-medium">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {departments.map((department,idx) => (
                    <tr key={department.id}>
                      <td className="py-1 px-2 text-muted-foreground">{rowOffset + idx + 1}</td>
                      <td className="py-1 text-muted-foreground">{department.name}</td>
                      <td className="py-1 text-muted-foreground">
                        {department.hod_name ?? '—'}
                      </td>
                      <td className="py-1">
                        <StatusBadge active={department.is_active} />
                      </td>
                      <td className="py-1">
                        <div className="flex items-center justify-end gap-2 ">
                          {can('departments.update') && (
                            <Link
                              to={`/departments/${department.id}/edit`}
                              className={buttonVariants({
                                variant: 'ghost',
                                size: 'sm',
                              })}
                            >
                              <Pencil className=' text-muted-foreground' aria-hidden="true" />
                           <span className=' text-muted-foreground'> Edit</span>
                            </Link>
                          )}
                          {can('departments.delete') && (
                            <button className='flex items-center justify-end gap-2 '
                              disabled={deletingId === department.id}
                              onClick={() => handleDelete(department)}
                            >
                              <Trash2 className=' text-red-400' aria-hidden="true" />
                                  <span className=' text-red-400'>  {deletingId === department.id ? 'Deleting…' : 'Delete'}</span>
    
                            </button>
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
                label="departments"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
