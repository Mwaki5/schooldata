import { Fragment, useState } from 'react'
import { ChevronDown, Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { FormField } from '@/components/form-field'
import { ListPagination } from '@/components/list-pagination'
import { ListSearch } from '@/components/list-search'
import { SortableTh } from '@/components/sortable-th'
import { useAuth } from '@/context/AuthContext'
import { useList } from '@/hooks/use-list'
import api from '@/lib/api'
import { cn } from '@/lib/utils'

/**
 * Roles and their permissions, edited live.
 *
 * The table lists every role — the seeded catalog first, custom roles
 * after — and expanding one opens the permission matrix grouped by
 * resource. Each checkbox toggles immediately through
 * `PUT /roles/{role}/permissions`, so what you see is what the backend
 * grants on the next request. Custom roles can also be renamed and
 * deleted here; the admin role is locked and holds everything.
 */
export default function Roles() {
  const { can } = useAuth()
  const [catalog, setCatalog] = useState([])
  const [expanded, setExpanded] = useState(null)
  const [drafts, setDrafts] = useState({})
  const [creating, setCreating] = useState(false)
  const [newName, setNewName] = useState('')
  const [renameValue, setRenameValue] = useState('')
  const [busy, setBusy] = useState(false)

  const {
    items: roles,
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
    resource: 'roles',
    endpoint: '/roles',
    errorMessage: 'Unable to load roles. Please try again.',
    // The catalog rides along with the list so the matrix renders the
    // whole permission set, including permissions no role holds yet.
    onData: (data) => setCatalog(data.catalog ?? []),
  })

  const totalPermissions = catalog.reduce(
    (sum, group) => sum + group.permissions.length,
    0,
  )

  /** What the matrix shows for a role: the pending draft, else the server copy. */
  const selectedFor = (role) => drafts[role.slug] ?? role.permissions ?? []

  /** Checkboxes edit only when the user may update and the role is not the locked admin. */
  const editable = (role) => can('roles.update') && !role.locked

  const openPanel = (role) => {
    if (expanded === role.slug) {
      setExpanded(null)
      return
    }
    setExpanded(role.slug)
    setRenameValue(role.slug)
  }

  const togglePermission = async (role, permission) => {
    const current = selectedFor(role)
    const next = current.includes(permission)
      ? current.filter((name) => name !== permission)
      : [...current, permission]

    setDrafts((previous) => ({ ...previous, [role.slug]: next }))

    try {
      await api.put(`/roles/${role.slug}/permissions`, { permissions: next })
      toast.success(`Saved permissions for ${role.name}`)
    } catch (error) {
      // Roll the optimistic toggle back so the matrix never lies.
      setDrafts((previous) => ({ ...previous, [role.slug]: current }))
      toast.error(
        error.response?.data?.message ?? 'Unable to save permissions. Please try again.',
      )
    }
  }

  const handleCreate = async (event) => {
    event.preventDefault()
    const name = newName.trim()
    if (!name) {
      return
    }

    setBusy(true)
    try {
      const { data } = await api.post('/roles', { name })

      toast.success(`Created "${data.role.name}"`)
      setNewName('')
      setCreating(false)
      setExpanded(data.role.slug)
      setRenameValue(data.role.slug)
      refresh()
    } catch (error) {
      toast.error(
        error.response?.data?.message ?? 'Unable to create the role. Please try again.',
      )
    } finally {
      setBusy(false)
    }
  }

  const handleRename = async (role) => {
    const name = renameValue.trim()
    if (!name || name === role.slug) {
      return
    }

    setBusy(true)
    try {
      const { data } = await api.put(`/roles/${role.slug}`, { name })

      // The slug is the role's key everywhere — in the matrix and the
      // endpoints — so drop the old draft and follow the new name.
      setDrafts((previous) => {
        const next = { ...previous }
        delete next[role.slug]
        return next
      })
      setExpanded(data.role.slug)
      setRenameValue(data.role.slug)
      toast.success(`Renamed to "${data.role.name}"`)
      refresh()
    } catch (error) {
      toast.error(
        error.response?.data?.message ?? 'Unable to rename the role. Please try again.',
      )
    } finally {
      setBusy(false)
    }
  }

  const handleDelete = async (role) => {
    if (
      !window.confirm(
        `Delete the "${role.name}" role? Users holding it lose everything it granted.`,
      )
    ) {
      return
    }

    setBusy(true)
    try {
      await api.delete(`/roles/${role.slug}`)

      toast.success(`Deleted "${role.name}"`)
      setExpanded(null)
      refresh()
    } catch (error) {
      toast.error(
        error.response?.data?.message ?? 'Unable to delete the role. Please try again.',
      )
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Roles</h1>
        <p className="text-sm text-muted-foreground">
          Each role bundles the permissions that decide what its users can
          reach. Open a role to toggle those permissions — changes save
          immediately. The admin role is locked and always holds everything
          except the student portal.
        </p>
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
          <CardTitle>All Roles</CardTitle>
          <div className="flex items-center gap-2">
            <ListSearch
              value={search}
              onChange={setSearch}
              placeholder="Search roles…"
            />
            {can('roles.create') && (
              <Button
                type="button"
                onClick={() => setCreating((open) => !open)}
              >
                <Plus aria-hidden="true" />
                New Role
              </Button>
            )}
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          {creating && (
            <form
              onSubmit={handleCreate}
              className="flex flex-wrap items-end gap-3 rounded-lg border p-4"
            >
              <FormField
                id="new-role-name"
                label="Role name"
                required
                className="w-64"
                value={newName}
                onChange={(event) => setNewName(event.target.value)}
                placeholder="registrar"
                autoComplete="off"
              />
              <p className="pb-2 text-xs text-muted-foreground">
                Lowercase letters, digits and dashes — it displays titled.
              </p>
              <div className="flex gap-2">
                <Button type="submit" disabled={busy || !newName.trim()}>
                  {busy ? 'Creating…' : 'Create'}
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setCreating(false)}
                >
                  Cancel
                </Button>
              </div>
            </form>
          )}

          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : roles.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {search
                ? `No roles match “${search}”.`
                : 'No roles available.'}
            </p>
          ) : (
            <>
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <SortableTh
                      column="slug"
                      label="Slug"
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
                    <th className="pb-3 font-medium">Description</th>
                    <th className="pb-3 font-medium">Permissions</th>
                    <th className="pb-3 text-right font-medium">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {roles.map((role) => {
                    const selected = selectedFor(role)
                    const isOpen = expanded === role.slug

                    return (
                      <Fragment key={role.id}>
                        <tr>
                          <td className="py-3 font-medium">{role.slug}</td>
                          <td className="py-3">
                            <span className="inline-flex items-center gap-2">
                              {role.name}
                              {role.locked && (
                                <span className="rounded-md bg-muted px-1.5 py-0.5 text-xs font-medium text-muted-foreground">
                                  Locked
                                </span>
                              )}
                            </span>
                          </td>
                          <td className="py-3 text-muted-foreground">
                            {role.description ?? '—'}
                          </td>
                          <td className="py-3 tabular-nums">
                            {selected.length} of {totalPermissions}
                          </td>
                          <td className="py-3 text-right">
                            <Button
                              type="button"
                              variant="ghost"
                              size="sm"
                              aria-expanded={isOpen}
                              onClick={() => openPanel(role)}
                            >
                              Permissions
                              <ChevronDown
                                aria-hidden="true"
                                className={cn(
                                  'transition-transform',
                                  isOpen && 'rotate-180',
                                )}
                              />
                            </Button>
                          </td>
                        </tr>

                        {isOpen && (
                          <tr>
                            <td colSpan={5} className="pb-4">
                              <div className="space-y-4 rounded-lg border bg-muted/30 p-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                  <p className="text-sm font-medium">
                                    {role.name} — {selected.length} of{' '}
                                    {totalPermissions} permissions
                                  </p>
                                  {role.locked && (
                                    <p className="text-xs text-muted-foreground">
                                      Admin always holds every permission and
                                      cannot be changed.
                                    </p>
                                  )}
                                </div>

                                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                  {catalog.map((group) => (
                                    <div
                                      key={group.name}
                                      className="rounded-lg border bg-background p-3"
                                    >
                                      <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        {group.label}
                                      </p>
                                      <div className="space-y-1.5">
                                        {group.permissions.map((permission) => (
                                          <label
                                            key={permission.name}
                                            className={cn(
                                              'flex items-center gap-2 text-sm',
                                              editable(role)
                                                ? 'cursor-pointer'
                                                : 'cursor-not-allowed opacity-60',
                                            )}
                                          >
                                            <Checkbox
                                              checked={selected.includes(
                                                permission.name,
                                              )}
                                              disabled={!editable(role)}
                                              onChange={() =>
                                                togglePermission(role, permission.name)
                                              }
                                            />
                                            <span>{permission.label}</span>
                                          </label>
                                        ))}
                                      </div>
                                    </div>
                                  ))}
                                </div>

                                {!role.built_in && (
                                  <div className="flex flex-wrap items-end gap-3 border-t pt-4">
                                    {can('roles.update') && (
                                      <form
                                        onSubmit={(event) => {
                                          event.preventDefault()
                                          handleRename(role)
                                        }}
                                        className="flex items-end gap-2"
                                      >
                                        <FormField
                                          id={`rename-${role.slug}`}
                                          label="Rename role"
                                          required
                                          className="w-64"
                                          value={renameValue}
                                          onChange={(event) =>
                                            setRenameValue(event.target.value)
                                          }
                                          autoComplete="off"
                                        />
                                        <Button
                                          type="submit"
                                          variant="outline"
                                          disabled={
                                            busy ||
                                            !renameValue.trim() ||
                                            renameValue.trim() === role.slug
                                          }
                                        >
                                          Rename
                                        </Button>
                                      </form>
                                    )}
                                    {can('roles.delete') && (
                                      <Button
                                        type="button"
                                        variant="destructive"
                                        disabled={busy}
                                        onClick={() => handleDelete(role)}
                                      >
                                        <Trash2 aria-hidden="true" />
                                        Delete role
                                      </Button>
                                    )}
                                  </div>
                                )}
                              </div>
                            </td>
                          </tr>
                        )}
                      </Fragment>
                    )
                  })}
                </tbody>
              </table>

              <ListPagination
                meta={meta}
                loading={loading}
                onPageChange={goToPage}
                label="roles"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
