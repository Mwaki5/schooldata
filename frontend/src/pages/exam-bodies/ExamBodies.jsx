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

export default function ExamBodies() {
  const [examBodies, setExamBodies] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [deletingId, setDeletingId] = useState(null)

  useEffect(() => {
    api
      .get('/exam-bodies')
      .then(({ data }) => setExamBodies(data.exam_bodies))
      .catch(() =>
        setLoadError('Unable to load exam bodies. Please try again.'),
      )
      .finally(() => setLoading(false))
  }, [])

  const handleDelete = async (examBody) => {
    if (!window.confirm(`Delete the "${examBody.code}" exam body?`)) {
      return
    }

    setDeletingId(examBody.id)

    try {
      await api.delete(`/exam-bodies/${examBody.id}`)

      setExamBodies((current) =>
        current.filter((item) => item.id !== examBody.id),
      )
      toast.success(`Deleted "${examBody.code}"`)
    } catch {
      toast.error(
        `Unable to delete "${examBody.code}". Please try again.`,
      )
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">
            Exam Bodies
          </h1>
          <p className="text-sm text-muted-foreground">
            Manage the examination authorities recognised by the school.
          </p>
        </div>
        <Link to="/exam-bodies/create" className={buttonVariants()}>
          <Plus aria-hidden="true" />
          New Exam Body
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
          <CardTitle>All Exam Bodies</CardTitle>
          <CardDescription>
            {loading
              ? 'Loading exam bodies…'
              : `${examBodies.length} ${
                  examBodies.length === 1 ? 'exam body' : 'exam bodies'
                } total`}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : examBodies.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              No exam bodies yet. Create the first one with the button above.
            </p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b text-left text-muted-foreground">
                  <th className="pb-3 font-medium">Code</th>
                  <th className="pb-3 font-medium">Name</th>
                  <th className="pb-3 font-medium">Website</th>
                  <th className="pb-3 font-medium">Status</th>
                  <th className="pb-3 text-right font-medium">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y">
                {examBodies.map((examBody) => (
                  <tr key={examBody.id}>
                    <td className="py-3 text-muted-foreground">
                      {examBody.code}
                    </td>
                    <td className="py-3 font-medium">{examBody.name}</td>
                    <td className="max-w-56 truncate py-3 text-muted-foreground">
                      {examBody.website ? (
                        <a
                          href={examBody.website}
                          target="_blank"
                          rel="noreferrer"
                          className="underline-offset-4 hover:underline"
                        >
                          {examBody.website}
                        </a>
                      ) : (
                        '—'
                      )}
                    </td>
                    <td className="py-3">
                      <StatusBadge active={examBody.is_active} />
                    </td>
                    <td className="py-3">
                      <div className="flex items-center justify-end gap-2">
                        <Link
                          to={`/exam-bodies/${examBody.id}/edit`}
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
                          disabled={deletingId === examBody.id}
                          onClick={() => handleDelete(examBody)}
                        >
                          <Trash2 aria-hidden="true" />
                          {deletingId === examBody.id
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
