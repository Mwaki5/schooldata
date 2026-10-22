import { Suspense, useEffect, useState } from 'react'
import {
  BookOpen,
  Calendar,
  CalendarCheck,
  ChevronRight,
  ClipboardList,
  LayoutDashboard,
  LogOut,
  Menu,
  Moon,
  PanelLeftClose,
  PanelLeftOpen,
  School,
  ShieldCheck,
  Sun,
  User,
  Wallet,
} from 'lucide-react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import PageLoader from '@/components/page-loader'
import { useAuth } from '@/context/AuthContext'
import { useTheme } from '@/context/ThemeContext'
import { cn } from '@/lib/utils'

/**
 * Top-level rail items. Each renders as its own row with a hairline divider
 * above it; items with `children` expand nested links inline.
 * Visibility is filtered by role (`roles`) before rendering.
 */
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
    roles: ['admin', 'student'],
    children: [
      { label: 'Students', to: '/students', roles: ['admin'] },
      { label: 'Enrolments', to: '/enrolments', roles: ['admin'] },
      { label: 'Staff', to: '/staff', roles: ['admin'] },
      { label: 'Classes', disabled: true, roles: ['admin'] },
      { label: 'Departments', to: '/departments', roles: ['admin'] },
      { label: 'Exam Bodies', to: '/exam-bodies', roles: ['admin'] },
      { label: 'Grades', to: '/exam-body-grades', roles: ['admin'] },
      { label: 'Certification Levels', to: '/certification-levels', roles: ['admin'] },
      { label: 'Curriculum', to: '/curricula', roles: ['admin'] },
      { label: 'Courses', to: '/courses', roles: ['admin'] },
      { label: 'Units', to: '/units', roles: ['admin'] },
      { label: 'Course Units', to: '/course-units', roles: ['admin'] },
      // Students only ever see their own course's units, never the
      // admin catalogue.
      { label: 'My Units', to: '/my-units', roles: ['student'] },
    ],
  },
  {
    label: 'Calendar',
    icon: Calendar,
    roles: ['admin'],
    children: [
      { label: 'Academic Year', to: '/academic-years', roles: ['admin'] },
      { label: 'Academic Session', to: '/academic-sessions', roles: ['admin'] },
      { label: 'Open Session', to: '/session-offerings', roles: ['admin'] },
      { label: 'Term Events', to: '/term-events', roles: ['admin'] },
    ],
  },
  { label: 'Attendance', icon: CalendarCheck, disabled: true },
  {
    label: 'Exams & Grades',
    icon: ClipboardList,
    roles: ['admin', 'trainer', 'student'],
    children: [
      { label: 'Unit Grading', to: '/unit-grades', roles: ['admin', 'trainer'] },
      { label: 'Marksheet', to: '/marksheet', roles: ['admin', 'trainer'] },
      // The student's own marksheet: their registered units and marks.
      { label: 'Grades', to: '/graded', roles: ['student'] },
    ],
  },
  {
    label: 'Fees',
    icon: Wallet,
    roles: ['admin'],
    children: [
      { label: 'Fee Structure', to: '/fee-structures', roles: ['admin'] },
      { label: 'Course Fees', to: '/course-fee-assignments', roles: ['admin'] },
    ],
  },
]

const PAGE_TITLES = {
  '/': 'Dashboard',
  '/profile': 'Profile',
  '/roles': 'Roles',
  '/departments': 'Departments',
  '/exam-bodies': 'Exam Bodies',
  '/exam-body-grades': 'Exam Body Grades',
  '/certification-levels': 'Certification Levels',
  '/curricula': 'Curriculum',
  '/courses': 'Courses',
  '/units': 'Units',
  '/course-units': 'Course Units',
  '/my-units': 'My Units',
  '/graded': 'Grades',
  '/academic-years': 'Academic Years',
  '/academic-sessions': 'Academic Sessions',
  '/session-offerings': 'Open Session',
  '/term-events': 'Term Events',
  '/unit-grades': 'Unit Grading',
  '/marksheet': 'Marksheet',
  '/fee-structures': 'Fee Structures',
  '/fee-structures/:feeStructureId/items': 'Fee Structure Items',
  '/course-fee-assignments': 'Course Fee Assignments',
  '/staff': 'Staff',
  '/students': 'Students',
  '/students/create': 'Students',
  '/students/:studentId/edit': 'Students',
  '/enrolments': 'Enrolments',
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
  const { user } = useAuth()
  const location = useLocation()

  /**
   * Exclusive accordion: at most one submenu group is open at a time.
   * `openGroup` holds the label the user explicitly opened (or null when
   * none). When the user hasn't chosen, the group containing the active
   * route stays open — so the selected item's group is always the open one.
   */
  const [openGroup, setOpenGroup] = useState(null)

  /** The icon-rail collapse only applies to the desktop sidebar. */
  const railCollapsed = !isMobile && collapsed

  /** Whether a group is open: user's explicit choice wins, else the active one. */
  const isGroupOpen = (label, hasActiveChild) =>
    openGroup === null ? hasActiveChild : openGroup === label

  /** Toggle one group; opening it closes every other group. */
  const toggleGroup = (label, isOpen) => {
    setOpenGroup(isOpen ? null : label)
  }

  /** In rail mode, clicking a group parent expands the sidebar instead. */
  const expandRail = () => {
    if (collapsed) {
      onToggleCollapsed()
    }
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
        'flex flex-col border-r border-sidebar-border bg-sidebar',
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
      {/* Brand row — 54px tall with a 30px emerald tile, matching the
          reference's logo/title/subtitle hierarchy. */}
      <div
        className={cn(
          'flex h-[54px] shrink-0 items-center border-b border-sidebar-line',
          railCollapsed ? 'justify-center px-3' : 'gap-2 pr-2 pl-3.5',
        )}
      >
        <span className="flex size-[30px] shrink-0 items-center justify-center rounded-lg bg-sidebar-accent">
          <School className="size-[17px] text-white" aria-hidden="true" />
        </span>
        <span
          className={cn(
            'min-w-0 flex-1',
            railCollapsed && 'hidden',
          )}
        >
          <span className="block truncate text-sm font-bold text-white">
            SchoolMS
          </span>
          <span className="block truncate text-[11px] text-sidebar-subtle">
            School management portal
          </span>
        </span>
        <button
          type="button"
          onClick={handleHeaderToggle}
          title={railCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          aria-label={railCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          className="shrink-0 rounded-lg p-1 text-sidebar-text transition-colors hover:bg-white/10 hover:text-white"
        >
          {railCollapsed ? (
            <PanelLeftOpen className="size-[18px]" aria-hidden="true" />
          ) : (
            <PanelLeftClose className="size-[18px]" aria-hidden="true" />
          )}
        </button>
      </div>

      <nav
        className="sidebar-scroll flex-1 overflow-y-auto px-2 py-3"
        aria-label="Main"
      >
        {visibleNavItems.map((item, itemIndex) => {
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
                isOpen={isGroupOpen(item.label, hasActiveChild)}
                onToggle={() =>
                  toggleGroup(
                    item.label,
                    isGroupOpen(item.label, hasActiveChild),
                  )
                }
                onExpandRail={expandRail}
                onCloseMobile={onCloseMobile}
                withDivider={itemIndex > 0}
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
                  // Every top-level label is separated by a hairline divider
                  // (the first sits directly under the brand row's border).
                  itemIndex > 0 && 'border-t border-sidebar-line',
                  'flex h-11 cursor-not-allowed items-center text-[13px] text-sidebar-text/45 select-none',
                  railCollapsed ? 'justify-center px-0' : 'gap-2.5 pr-4 pl-3.5',
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
                    'rounded border border-white/10 bg-white/5 px-1.5 py-0.5 text-[9px] font-semibold tracking-wider text-sidebar-subtle uppercase',
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
                  // 44px square-cornered rows; the active row is a flat band
                  // with a hairline bottom edge and a 3px emerald bar flush
                  // to the left — no rounding. Its top edge is the divider.
                  itemIndex > 0 && 'border-t border-sidebar-line',
                  'relative flex h-11 items-center text-[13px] transition-colors',
                  railCollapsed ? 'justify-center px-0' : 'gap-2.5 pr-4 pl-3.5',
                  isActive
                    ? 'border-b border-sidebar-line bg-sidebar-active font-semibold text-white'
                    : 'text-sidebar-text hover:bg-white/[0.04] hover:text-white',
                )
              }
            >
              {({ isActive }) => (
                <>
                  {isActive && (
                    <span
                      aria-hidden="true"
                      className="absolute inset-y-0 left-0 w-[3px] bg-sidebar-accent"
                    />
                  )}
                  <item.icon
                    className="size-[18px] shrink-0"
                    aria-hidden="true"
                  />
                  <span className={cn('flex-1', railCollapsed && 'hidden')}>
                    {item.label}
                  </span>
                </>
              )}
            </NavLink>
          )
        })}
      </nav>

      {/* Signed-in user's card — links to their own profile page. */}
      <div className="border-t border-sidebar-line p-3">
        <Link
          to="/profile"
          title="View profile"
          className={cn(
            'flex items-center rounded-lg transition-colors hover:bg-white/[0.04]',
            railCollapsed ? 'flex-col gap-2 px-1 py-2' : 'gap-3 px-2 py-1.5',
          )}
        >
          <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-sidebar-accent text-sm font-semibold text-white">
            {initial}
          </div>
          <div className={cn('min-w-0 flex-1', railCollapsed && 'hidden')}>
            <p className="truncate text-sm font-medium text-white">
              {user?.name}
            </p>
            <p className="truncate text-xs text-sidebar-subtle">{user?.email}</p>
          </div>
        </Link>
      </div>
    </aside>
  )
}

/**
 * A top-level nav item with nested links. The parent row shows a chevron that
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
  withDivider,
}) {
  if (railCollapsed) {
    return (
      <button
        type="button"
        title={item.label}
        aria-label={item.label}
        onClick={onExpandRail}
        className={cn(
          withDivider && 'border-t border-sidebar-line',
          'flex h-11 w-full items-center justify-center transition-colors',
          hasActiveChild
            ? 'bg-sidebar-active text-white'
            : 'text-sidebar-text hover:bg-white/[0.04] hover:text-white',
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
          withDivider && 'border-t border-sidebar-line',
          'flex h-11 w-full items-center gap-2.5 pr-4 pl-3.5 text-[13px] transition-colors',
          hasActiveChild
            ? 'bg-sidebar-active font-semibold text-white'
            : 'text-sidebar-text hover:bg-white/[0.04] hover:text-white',
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
        <div className="relative mt-1 mb-1 ml-3 space-y-0.5 border-l border-sidebar-line pl-3">
          {item.children.map((child) =>
            child.disabled ? (
              <div
                key={child.label}
                aria-disabled="true"
                className="flex cursor-not-allowed items-center py-1.5 pr-2 pl-3 text-[13px] text-sidebar-text/45 select-none"
              >
                <span className="flex-1">{child.label}</span>
                <span className="rounded border border-white/10 bg-white/5 px-1.5 py-0.5 text-[9px] font-semibold tracking-wider text-sidebar-subtle uppercase">
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
                    'relative flex items-center py-1.5 pr-2 pl-3 text-[13px] transition-colors',
                    isActive
                      ? 'border-l-[3px] border-sidebar-accent bg-sidebar-active pl-[9px] font-semibold text-white -ml-[3px]'
                      : 'text-sidebar-text hover:bg-white/[0.04] hover:text-white',
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
  const navigate = useNavigate()
  const { theme, toggleTheme } = useTheme()
  const { user, logout } = useAuth()

  const title = pageTitleFor(location.pathname)
  const today = new Date().toLocaleDateString(undefined, {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })

  const handleLogout = async () => {
    await logout()

    navigate('/login', { replace: true })
  }

  return (
    <header className="sticky top-0 z-30 flex h-14 shrink-0 items-center justify-between border-b border-border bg-background px-4 md:px-6">
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
        <h1 className="text-[15px] font-semibold tracking-tight">{title}</h1>
      </div>
      <div className="flex items-center gap-1.5">
        <p className="mr-2 hidden text-sm text-muted-foreground sm:block">
          {today}
        </p>
        <button
          type="button"
          onClick={toggleTheme}
          title={theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'}
          aria-label={theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'}
          aria-pressed={theme === 'dark'}
          className="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
        >
          {theme === 'dark' ? (
            <Sun className="size-[18px]" aria-hidden="true" />
          ) : (
            <Moon className="size-[18px]" aria-hidden="true" />
          )}
        </button>
        <Link
          to="/profile"
          title="View profile"
          className="flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
        >
          <User className="size-[18px]" aria-hidden="true" />
          <span className="hidden max-w-[140px] truncate text-sm md:inline">
            {user?.name ?? 'Profile'}
          </span>
        </Link>
        <button
          type="button"
          onClick={handleLogout}
          title="Sign out"
          aria-label="Sign out"
          className="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
        >
          <LogOut className="size-[18px]" aria-hidden="true" />
        </button>
      </div>
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
    // The shell is locked to the viewport height so the document itself
    // never scrolls: the sidebar and navbar stay fixed and only <main>
    // below the sticky navbar overflows.
    <div className="flex h-svh overflow-hidden">
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
      <div className="flex min-w-0 flex-1 flex-col overflow-hidden">
        <Navbar isMobile={isMobile} onOpenMobile={() => setMobileOpen(true)} />
        <main className="flex-1 overflow-y-auto p-6">
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