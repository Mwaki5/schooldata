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

/**
 * The permission names a role bundles, condensed to their module
 * domains — `fee-structures.view` and `fee-structures.manage` render as
 * one "fee structures" pill. The backend enforces the full names; this
 * column shows what areas the role reaches.
 */
function permissionDomains(permissions) {
  return [...new Set(permissions.map((permission) => permission.split('.')[0]))]
}

export default function Roles() {
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
  } = useList({
    resource: 'roles',
    endpoint: '/roles',
    errorMessage: 'Unable to load roles. Please try again.',
  })

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Roles</h1>
        <p className="text-sm text-muted-foreground">
          The roles used for access control across the school. They are fixed
          by the application and cannot be created, edited or deleted. Each
          bundles the permissions that decide what its users can access.
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
          <ListSearch
            value={search}
            onChange={setSearch}
            placeholder="Search roles…"
          />
        </CardHeader>
        <CardContent className="space-y-4">
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
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {roles.map((role) => (
                    <tr key={role.id}>
                      <td className="py-3 font-medium">{role.slug}</td>
                      <td className="py-3">{role.name}</td>
                      <td className="py-3 text-muted-foreground">
                        {role.description ?? '—'}
                      </td>
                      <td className="py-3">
                        <div className="flex flex-wrap gap-1">
                          {permissionDomains(role.permissions ?? []).map(
                            (domain) => (
                              <span
                                key={domain}
                                className="rounded-md bg-muted px-1.5 py-0.5 text-xs font-medium text-muted-foreground"
                              >
                                {domain.replace(/-/g, ' ')}
                              </span>
                            ),
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
                label="roles"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
