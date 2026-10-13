import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { buttonVariants } from '@/components/ui/button'
import {
  Card,
  CardContent,
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

export default function Departments() {
  const [departments, setDepartments] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [deletingId, setDeletingId] = useState(null)

  useEffect(() => {
    api
      .get('/departments')
      .then(({ data }) => setDepartments(data.departments))
      .catch(() =>
        setLoadError('Unable to load departments. Please try again.'),
      )
      .finally(() => setLoading(false))
  }, [])

  const handleDelete = async (department) => {
    if (!window.confirm(`Delete the "${department.name}" department?`)) {
      return
    }

    setDeletingId(department.id)

    try {
      await api.delete(`/departments/${department.id}`)

      setDepartments((current) =>
        current.filter((item) => item.id !== department.id),
      )
      toast.success(`Deleted "${department.name}"`)
    } catch {
      toast.error(
        `Unable to delete "${department.name}". Please try again.`,
      )
    } finally {
      setDeletingId(null)
    }
  }

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
        <Link to="/departments/create" className={buttonVariants()}>
          <Plus aria-hidden="true" />
          New Department
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
        <CardContent>
          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : departments.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              No departments yet. Create the first one with the button above.
            </p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b text-left text-muted-foreground">
                  <th className="py-1 px-2 font-medium">#</th>
                  <th className="py-1 font-medium">Department</th>
                  <th className="py-1 font-medium">HOD</th>
                  <th className="py-1 font-medium">Status</th>
                  <th className="py-1 text-right font-medium">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y">
                {departments.map((department,idx) => (
                  <tr key={department.id}>
                    <td className="py-1 px-2 text-muted-foreground">{idx+1}</td>
                    <td className="py-1 text-muted-foreground">{department.name}</td>
                    <td className="py-1 text-muted-foreground">
                      {department.hod_name ?? '—'}
                    </td>
                    <td className="py-1">
                      <StatusBadge active={department.is_active} />
                    </td>
                    <td className="py-1">
                      <div className="flex items-center justify-end gap-2 ">
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
                        <button className='flex items-center justify-end gap-2 '
                          disabled={deletingId === department.id}
                          onClick={() => handleDelete(department)}
                        >
                          <Trash2 className=' text-red-400' aria-hidden="true" />
                              <span className=' text-red-400'>  {deletingId === department.id ? 'Deleting…' : 'Delete'}</span>

                        </button>
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
