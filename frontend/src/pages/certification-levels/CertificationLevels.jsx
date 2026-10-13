import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { Button, buttonVariants } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import api from '@/lib/api'
import { cn } from '@/lib/utils'
import { Pencil, Trash2, Plus } from 'lucide-react'

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

export default function CertificationLevels() {
  const [levels, setLevels] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [deletingId, setDeletingId] = useState(null)

  useEffect(() => {
    api
      .get('/certification-levels')
      .then(({ data }) => setLevels(data.certification_levels))
      .catch(() => setLoadError('Unable to load certification levels.'))
      .finally(() => setLoading(false))
  }, [])

  const handleDelete = async (level) => {
    if (!window.confirm(`Delete the "${level.code}" level?`)) {
      return
    }

    setDeletingId(level.id)

    try {
      await api.delete(`/certification-levels/${level.id}`)

      setLevels((current) => current.filter((item) => item.id !== level.id))
      toast.success(`Deleted "${level.code}"`)
    } catch {
      toast.error(`Unable to delete "${level.code}". Please try again.`)
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">
            Certification Levels
          </h1>
          <p className="text-sm text-muted-foreground">
            Manage the qualification levels recognised by exam bodies.
          </p>
        </div>
        <Link to="/certification-levels/create" className={buttonVariants()}>
          <Plus aria-hidden="true" /> New Certification Level
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
          <CardTitle>All Certification Levels</CardTitle>
          <CardDescription>
            {loading
              ? 'Loading certification levels…'
              : `${levels.length} ${
                  levels.length === 1 ? 'certification level' : 'certification levels'
                } total`}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : levels.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              No certification levels yet. Create the first one with the button above.
            </p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b text-left text-muted-foreground">
                  <th className="pb-3 font-medium">Exam Body</th>
                  <th className="pb-3 font-medium">Code</th>
                  <th className="pb-3 font-medium">Name</th>
                  <th className="pb-3 font-medium">Sequence</th>
                  <th className="pb-3 font-medium">Status</th>
                  <th className="pb-3 text-right font-medium">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y">
                {levels.map((level) => (
                  <tr key={level.id}>
                    <td className="py-3 font-medium">{level.exam_body_name ?? '—'}</td>
                    <td className="py-3 text-muted-foreground">{level.code}</td>
                    <td className="py-3 font-medium">{level.name}</td>
                    <td className="py-3 text-muted-foreground">{level.sequence}</td>
                    <td className="py-3">
                      <StatusBadge active={level.is_active} />
                    </td>
                    <td className="py-3">
                      <div className="flex items-center justify-end gap-2">
                        <Link
                          to={`/certification-levels/${level.id}/edit`}
                          className={buttonVariants({ variant: 'ghost', size: 'sm' })}>
                          <Pencil aria-hidden="true" /> Edit
                        </Link>
                        <Button
                          variant="destructive"
                          size="sm"
                          disabled={deletingId === level.id}
                          onClick={() => handleDelete(level)}
                        >
                          <Trash2 aria-hidden="true" />
                          {deletingId === level.id
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