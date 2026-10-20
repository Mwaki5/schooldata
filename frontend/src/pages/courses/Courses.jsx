import { useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { Button, buttonVariants } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ListPagination } from '@/components/list-pagination'
import { ListSearch } from '@/components/list-search'
import { SortableTh } from '@/components/sortable-th'
import { useList } from '@/hooks/use-list'
import api from '@/lib/api'
import { cn } from '@/lib/utils'
import { BookOpen, Pencil, Trash2, Plus } from 'lucide-react'

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

export default function Courses() {
  const [deletingId, setDeletingId] = useState(null)

  const {
    items: courses,
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
    resource: 'courses',
    endpoint: '/courses',
    errorMessage: 'Unable to load courses.',
  })

  const handleDelete = async (course) => {
    if (!window.confirm(`Delete the "${course.code}" course?`)) {
      return
    }

    setDeletingId(course.id)

    try {
      await api.delete(`/courses/${course.id}`)

      refresh()
      toast.success(`Deleted "${course.code}"`)
    } catch {
      toast.error(`Unable to delete "${course.code}". Please try again.`)
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Courses</h1>
          <p className="text-sm text-muted-foreground">
            Manage the courses offered by the school.
          </p>
        </div>
        <Link to="/courses/create" className={buttonVariants()}>
          <Plus aria-hidden="true" /> New Course
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
        <CardHeader className="flex flex-row items-center justify-between gap-4">
          <CardTitle>All Courses</CardTitle>
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search courses…"
          />
        </CardHeader>
        <CardContent className="space-y-4">
          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : courses.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {search
                ? `No courses match “${search}”.`
                : 'No courses yet. Create the first one with the button above.'}
            </p>
          ) : (
            <>
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <SortableTh
                      column="code"
                      label="Code"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <th className="pb-3 font-medium">Initials</th>
                    <SortableTh
                      column="name"
                      label="Name"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <SortableTh
                      column="total_modules"
                      label="Modules"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <th className="pb-3 font-medium">Entry grade</th>
                    <th className="pb-3 font-medium">Certification Level</th>
                    <th className="pb-3 font-medium">Department</th>
                    <SortableTh
                      column="is_active"
                      label="Status"
                      sort={sort}
                      order={order}
                      onSort={toggleSort}
                    />
                    <th className="pb-3 text-right font-medium">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {courses.map((course) => (
                    <tr key={course.id}>
                      <td className="py-3 text-muted-foreground">{course.code}</td>
                      <td className="py-3 text-muted-foreground">{course.initials}</td>
                      <td className="py-3 font-medium">{course.name}</td>
                      <td className="py-3 text-muted-foreground">
                        {course.total_modules != null
                          ? `${course.total_modules}`
                          : '—'}
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {course.entry_grade ?? '—'}
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {course.certification_level_name ?? '—'}
                      </td>
                      <td className="py-3 text-muted-foreground">
                        {course.department_name ?? '—'}
                      </td>
                      <td className="py-3">
                        <StatusBadge active={course.is_active} />
                      </td>
                      <td className="py-3">
                        <div className="flex items-center justify-end gap-2">
                          <Link
                            to={`/courses/${course.id}/units`}
                            className={buttonVariants({ variant: 'ghost', size: 'sm' })}
                            title="View and edit this course's units">
                            <BookOpen aria-hidden="true" /> Units
                          </Link>
                          <Link
                            to={`/courses/${course.id}/edit`}
                            className={buttonVariants({ variant: 'ghost', size: 'sm' })}>
                            <Pencil aria-hidden="true" /> Edit
                          </Link>
                          <Button
                            variant="destructive"
                            size="sm"
                            disabled={deletingId === course.id}
                            onClick={() => handleDelete(course)}
                          >
                            <Trash2 aria-hidden="true" />
                            {deletingId === course.id
                              ? 'Deleting…'
                              : 'Delete'}
                          </Button>
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
                label="courses"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
