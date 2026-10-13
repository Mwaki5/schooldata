import { Suspense, useEffect, useState } from 'react'
import {
  BookOpen,
  CalendarCheck,
  ChevronRight,
  ClipboardList,
  LayoutDashboard,
  LogOut,
  Menu,
  PanelLeftClose,
  PanelLeftOpen,
  School,
  ShieldCheck,
  Wallet,
} from 'lucide-react'
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import PageLoader from '@/components/page-loader'
import { useAuth } from '@/context/AuthContext'
import { cn } from '@/lib/utils'

const NAV_ITEMS = [
  { label: 'Dashboard', icon: LayoutDashboard, to: '/' },
  {
    label: 'Access Control',
    icon: ShieldCheck,
    roles: ['admin'],
    children: [
      { label: 'Roles', to: '/roles', roles: ['admin'] },
      { label: 'Users', disabled: true, roles: ['admin'] },
      { label: 'Activity Log', to: '/activities', roles: ['admin'] },
    ],
  },
  {
    label: 'Academics',
    icon: BookOpen,
    roles: ['admin'],
    children: [
      { label: 'Students', disabled: true, roles: ['admin'] },
      { label: 'Staff', to: '/staff', roles: ['admin'] },
      { label: 'Classes', disabled: true, roles: ['admin'] },
      { label: 'Departments', to: '/departments', roles: ['admin'] },
      { label: 'Exam Bodies', to: '/exam-bodies', roles: ['admin'] },
      { label: 'Certification Levels', to: '/certification-levels', roles: ['admin'] },
    ],
  },
  { label: 'Attendance', icon: CalendarCheck, disabled: true },
  { label: 'Exams & Grades', icon: ClipboardList, disabled: true },
  { label: 'Fees', icon: Wallet, disabled: true },
]

const PAGE_TITLES = {
  '/': 'Dashboard',
  '/roles': 'Roles',
  '/departments': 'Departments',
  '/exam-bodies': 'Exam Bodies',
  '/certification-levels': 'Certification Levels',
  '/staff': 'Staff',
  '/activities': 'Activity Log',
}

/**
 * RBAC visibility: `roles` is an array of role slugs allowed to see the
 * nav item (group, link or disabled entry). Items without a `roles`
 * list are visible to every signed-in user.
 */
function canAccess(item, role) {
  return !item.roles || item.roles.includes(role)
}

/**
 * Whether a nav path matches the current location. `/` only matches the
 * index; other paths also match their nested routes (e.g. `/roles/3/edit`).
 */
function isPathActive(pathname, to) {
  if (to === '/') {
    return pathname === '/'
  }

  return pathname === to || pathname.startsWith(`${to}/`)
}

/**
 * Resolve the navbar title, matching nested paths to their section
 * (e.g. `/roles/3/edit` shows "Roles").
 */
function pageTitleFor(pathname) {
  const match = Object.entries(PAGE_TITLES)
    .filter(
      ([path]) =>
        pathname === path ||
        (path !== '/' && pathname.startsWith(`${path}/`)),
    )
    .sort(([a], [b]) => b.length - a.length)[0]

  return match?.[1] ?? 'Dashboard'
}

const STORAGE_KEY = 'sidebar-collapsed'
const MOBILE_QUERY = '(max-width: 767px)'

function Sidebar({
  collapsed,
  mobileOpen,
  isMobile,
  onToggleCollapsed,
  onCloseMobile,
}) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()

  /** Manually toggled submenu groups: label -> open boolean. */
  const [openGroups, setOpenGroups] = useState({})

  /** The icon-rail collapse only applies to the desktop sidebar. */
  const railCollapsed = !isMobile && collapsed

  const toggleGroup = (label, defaultOpen) => {
    setOpenGroups((current) => ({
      ...current,
      [label]: !(current[label] ?? defaultOpen),
    }))
  }

  /** In rail mode, clicking a group parent expands the sidebar instead. */
  const expandRail = () => {
    if (collapsed) {
      onToggleCollapsed()
    }
  }

  const handleLogout = async () => {
    await logout()

    navigate('/login', { replace: true })
  }

  const handleHeaderToggle = () => {
    if (isMobile) {
      onCloseMobile()
    } else {
      onToggleCollapsed()
    }
  }

  const initial = (user?.name ?? '?').trim().charAt(0).toUpperCase()

  // RBAC: display only the links the signed-in user's role may access.
  // Each nav item (group or individual link) carries its own `roles`
  // array; groups whose children are all hidden disappear entirely.
  const userRole = user?.role
  const visibleNavItems = NAV_ITEMS.filter((item) => canAccess(item, userRole))
    .map((item) =>
      item.children
        ? {
            ...item,
            children: item.children.filter((child) =>
              canAccess(child, userRole),
            ),
          }
        : item,
    )
    .filter((item) => !item.children || item.children.length > 0)

  return (
    <aside
      className={cn(
        'flex flex-col bg-[#1b263b]',
        isMobile
          ? cn(
              'w-[250px]',
              mobileOpen
                ? 'fixed inset-y-0 left-0 z-50 animate-sidebar-in shadow-2xl'
                : 'hidden',
            )
          : cn(
              'sticky top-0 h-svh shrink-0 transition-[width] duration-200 ease-out',
              railCollapsed ? 'w-16' : 'w-[250px]',
            ),
      )}
    >
      <div
        className={cn(
          'flex h-16 items-center border-b border-white/10',
          railCollapsed ? 'justify-center px-3' : 'gap-2.5 pr-3 pl-5',
        )}
      >
        <School className="size-6 shrink-0 text-white" aria-hidden="true" />
        <span
          className={cn(
            'flex-1 text-base font-semibold text-white',
            railCollapsed && 'hidden',
          )}
        >
          SchoolMS
        </span>
        <button
          type="button"
          onClick={handleHeaderToggle}
          title={railCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          aria-label={railCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          className="shrink-0 rounded-lg p-2 text-slate-300 transition-colors hover:bg-white/10 hover:text-white"
        >
          {railCollapsed ? (
            <PanelLeftOpen className="size-[18px]" aria-hidden="true" />
          ) : (
            <PanelLeftClose className="size-[18px]" aria-hidden="true" />
          )}
        </button>
      </div>

      <nav className="flex-1 space-y-1 overflow-y-auto p-3" aria-label="Main">
        {visibleNavItems.map((item) => {
          if (item.children) {
            const hasActiveChild = item.children.some(
              (child) => child.to && isPathActive(location.pathname, child.to),
            )

            return (
              <NavGroup
                key={item.label}
                item={item}
                railCollapsed={railCollapsed}
                hasActiveChild={hasActiveChild}
                isOpen={openGroups[item.label] ?? hasActiveChild}
                onToggle={() => toggleGroup(item.label, hasActiveChild)}
                onExpandRail={expandRail}
                onCloseMobile={onCloseMobile}
              />
            )
          }

          if (item.disabled) {
            return (
              <div
                key={item.label}
                aria-disabled="true"
                title={railCollapsed ? item.label : undefined}
                className={cn(
                  'flex cursor-not-allowed items-center rounded-lg py-2.5 text-sm text-slate-400 opacity-70 select-none',
                  railCollapsed ? 'justify-center px-0' : 'gap-3 px-3',
                )}
              >
                <item.icon
                  className="size-[18px] shrink-0"
                  aria-hidden="true"
                />
                <span className={cn('flex-1', railCollapsed && 'hidden')}>
                  {item.label}
                </span>
                <span
                  className={cn(
                    'rounded bg-white/10 px-1.5 py-0.5 text-[10px] font-semibold tracking-wider text-slate-300 uppercase',
                    railCollapsed && 'hidden',
                  )}
                >
                  Soon
                </span>
              </div>
            )
          }

          return (
            <NavLink
              key={item.label}
              to={item.to}
              end
              onClick={onCloseMobile}
              title={railCollapsed ? item.label : undefined}
              className={({ isActive }) =>
                cn(
                  'flex items-center rounded-lg py-2.5 transition-colors',
                  railCollapsed ? 'justify-center px-0' : 'gap-3 px-3',
                  isActive
                    ? 'font-medium text-white'
                    : 'text-slate-300 hover:bg-white/5 hover:text-white',
                )
              }
            >
              <item.icon className="size-[18px] shrink-0" aria-hidden="true" />
              <span className={cn('flex-1', railCollapsed && 'hidden')}>
                {item.label}
              </span>
            </NavLink>
          )
        })}
      </nav>

      <div className="border-t border-white/10 p-3">
        <div
          className={cn(
            'flex items-center',
            railCollapsed ? 'flex-col gap-2 py-1' : 'gap-3 px-2 py-1.5',
          )}
        >
          <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#415a77] text-sm font-semibold text-white">
            {initial}
          </div>
          <div className={cn('min-w-0 flex-1', railCollapsed && 'hidden')}>
            <p className="truncate text-sm font-medium text-white">
              {user?.name}
            </p>
            <p className="truncate text-xs text-slate-400">{user?.email}</p>
          </div>
          <button
            type="button"
            onClick={handleLogout}
            title="Sign out"
            aria-label="Sign out"
            className="shrink-0 rounded-lg p-2 text-slate-400 transition-colors hover:bg-white/10 hover:text-white"
          >
            <LogOut className="size-[18px]" aria-hidden="true" />
          </button>
        </div>
      </div>
    </aside>
  )
}

/**
 * A sidebar section with nested links. The parent row shows a chevron that
 * flips between pointing right (collapsed) and left (expanded); in the
 * collapsed icon rail it renders as an icon that expands the sidebar.
 */
function NavGroup({
  item,
  railCollapsed,
  hasActiveChild,
  isOpen,
  onToggle,
  onExpandRail,
  onCloseMobile,
}) {
  if (railCollapsed) {
    return (
      <button
        type="button"
        title={item.label}
        aria-label={item.label}
        onClick={onExpandRail}
        className={cn(
          'flex w-full items-center justify-center rounded-lg py-2.5 transition-colors',
          hasActiveChild
            ? 'bg-white/10 text-white'
            : 'text-slate-300 hover:bg-white/5 hover:text-white',
        )}
      >
        <item.icon className="size-[18px] shrink-0" aria-hidden="true" />
      </button>
    )
  }

  return (
    <div>
      <button
        type="button"
        onClick={onToggle}
        aria-expanded={isOpen}
        className={cn(
          'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors',
          hasActiveChild
            ? 'bg-white/10 font-medium text-white'
            : 'text-slate-300 hover:bg-white/5 hover:text-white',
        )}
      >
        <item.icon className="size-[18px] shrink-0" aria-hidden="true" />
        <span className="flex-1 text-left">{item.label}</span>
        <ChevronRight
          aria-hidden="true"
          className={cn(
            'size-4 shrink-0 transition-transform duration-200',
            isOpen && 'rotate-180',
          )}
        />
      </button>

      {isOpen && (
        <div className="mt-1 space-y-1">
          {item.children.map((child) =>
            child.disabled ? (
              <div
                key={child.label}
                aria-disabled="true"
                className="flex cursor-not-allowed items-center rounded-lg py-2 pr-3 pl-11 text-[13px] text-slate-400 opacity-70 select-none"
              >
                <span className="flex-1">{child.label}</span>
                <span className="rounded bg-white/10 px-1.5 py-0.5 text-[10px] font-semibold tracking-wider text-slate-300 uppercase">
                  Soon
                </span>
              </div>
            ) : (
              <NavLink
                key={child.label}
                to={child.to}
                onClick={onCloseMobile}
                className={({ isActive }) =>
                  cn(
                    'flex items-center rounded-lg py-2 pr-3 pl-11 transition-colors',
                    isActive
                      ? 'font-medium text-white'
                      : 'text-slate-300 hover:bg-white/5 hover:text-white',
                  )
                }
              >
                {child.label}
              </NavLink>
            ),
          )}
        </div>
      )}
    </div>
  )
}

function Navbar({ isMobile, onOpenMobile }) {
  const location = useLocation()

  const title = pageTitleFor(location.pathname)
  const today = new Date().toLocaleDateString(undefined, {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })

  return (
    <header className="flex h-16 shrink-0 items-center justify-between border-b bg-background px-4 md:px-6">
      <div className="flex items-center gap-3">
        {isMobile && (
          <button
            type="button"
            onClick={onOpenMobile}
            title="Open sidebar"
            aria-label="Open sidebar"
            className="rounded-lg p-2 text-foreground transition-colors hover:bg-accent"
          >
            <Menu className="size-5" aria-hidden="true" />
          </button>
        )}
        <h1 className="text-lg font-semibold">{title}</h1>
      </div>
      <p className="hidden text-sm text-muted-foreground sm:block">{today}</p>
    </header>
  )
}

export default function DashboardLayout() {
  const [collapsed, setCollapsed] = useState(
    () => localStorage.getItem(STORAGE_KEY) === 'true',
  )
  const [mobileOpen, setMobileOpen] = useState(false)
  const [isMobile, setIsMobile] = useState(
    () => window.matchMedia(MOBILE_QUERY).matches,
  )

  useEffect(() => {
    const mediaQuery = window.matchMedia(MOBILE_QUERY)

    const handleChange = (event) => {
      setIsMobile(event.matches)

      if (!event.matches) {
        setMobileOpen(false)
      }
    }

    mediaQuery.addEventListener('change', handleChange)

    return () => mediaQuery.removeEventListener('change', handleChange)
  }, [])

  const toggleCollapsed = () => {
    setCollapsed((current) => {
      const next = !current

      localStorage.setItem(STORAGE_KEY, String(next))

      return next
    })
  }

  return (
    <div className="flex min-h-svh">
      {isMobile && mobileOpen && (
        <div
          aria-hidden="true"
          onClick={() => setMobileOpen(false)}
          className="fixed inset-0 z-40 bg-black/50"
        />
      )}
      <Sidebar
        collapsed={collapsed}
        mobileOpen={mobileOpen}
        isMobile={isMobile}
        onToggleCollapsed={toggleCollapsed}
        onCloseMobile={() => setMobileOpen(false)}
      />
      <div className="flex min-w-0 flex-1 flex-col">
        <Navbar isMobile={isMobile} onOpenMobile={() => setMobileOpen(true)} />
        <main className="flex-1 p-6">
          {/* Lazily-loaded child routes swap in here without unmounting
              the shell (sidebar + navbar stay visible during loads). */}
          <Suspense fallback={<PageLoader />}>
            <Outlet />
          </Suspense>
        </main>
      </div>
    </div>
  )
}