import { lazy, Suspense } from 'react'
import { Navigate, Outlet, Route, Routes, useLocation } from 'react-router-dom'
import { Toaster } from 'sonner'
import PageLoader from '@/components/page-loader'
import { AuthProvider, useAuth } from '@/context/AuthContext'

// Every route component is code-split with React.lazy so the initial bundle
// stays small; each route has a Suspense boundary with a loading fallback.
const DashboardLayout = lazy(() => import('@/components/layout/dashboard-layout'))
const Dashboard = lazy(() => import('@/pages/Dashboard'))
const Login = lazy(() => import('@/pages/Login'))
const Register = lazy(() => import('@/pages/Register'))
const Roles = lazy(() => import('@/pages/roles/Roles'))
const Departments = lazy(() => import('@/pages/departments/Departments'))
const DepartmentForm = lazy(
  () => import('@/pages/departments/DepartmentForm'),
)
const ExamBodies = lazy(() => import('@/pages/exam-bodies/ExamBodies'))
const ExamBodyForm = lazy(() => import('@/pages/exam-bodies/ExamBodyForm'))
const CertificationLevels = lazy(() => import('@/pages/certification-levels/CertificationLevels'))
const CertificationLevelForm = lazy(() => import('@/pages/certification-levels/CertificationLevelForm'))
const Staff = lazy(() => import('@/pages/staff/Staff'))
const StaffForm = lazy(() => import('@/pages/staff/StaffForm'))
const Activities = lazy(() => import('@/pages/activities/Activities'))

function ProtectedRoute({ children }) {
  const { user, loading } = useAuth()
  const location = useLocation()

  if (loading) {
    return <PageLoader fullPage />
  }

  if (!user) {
    return <Navigate to="/login" replace state={{ from: location }} />
  }

  return children
}

function GuestRoute({ children }) {
  const { user, loading } = useAuth()

  if (loading) {
    return <PageLoader fullPage />
  }

  if (user) {
    return <Navigate to="/" replace />
  }

  return children
}

/**
 * Gates a section behind one or more roles (`roles={['admin']}`). The
 * backend enforces the same rules with the `role:admin` middleware, so
 * this guard only keeps the UI honest — unauthorised users are sent
 * back to the dashboard.
 */
function RoleRoute({ roles, children }) {
  const { user, loading } = useAuth()

  if (loading) {
    return <PageLoader fullPage />
  }

  if (!user || !roles.includes(user.role)) {
    return <Navigate to="/" replace />
  }

  return children
}

export default function App() {
  return (
    <AuthProvider>
      <Routes>
        <Route
          path="/login"
          element={
            <GuestRoute>
              <Suspense fallback={<PageLoader fullPage />}>
                <Login />
              </Suspense>
            </GuestRoute>
          }
        />
        <Route
          path="/register"
          element={
            <GuestRoute>
              <Suspense fallback={<PageLoader fullPage />}>
                <Register />
              </Suspense>
            </GuestRoute>
          }
        />
        <Route
          path="/"
          element={
            <ProtectedRoute>
              <Suspense fallback={<PageLoader fullPage />}>
                <DashboardLayout />
              </Suspense>
            </ProtectedRoute>
          }
        >
          <Route index element={<Dashboard />} />
          {/* Admin-only section: the backend applies the same
              `role:admin` middleware to every route below. */}
          <Route
            element={
              <RoleRoute roles={['admin']}>
                <Outlet />
              </RoleRoute>
            }
          >
            <Route path="/roles" element={<Roles />} />
            <Route path="/departments" element={<Departments />} />
            <Route path="/departments/create" element={<DepartmentForm />} />
            <Route
              path="/departments/:departmentId/edit"
              element={<DepartmentForm />}
            />
            <Route path="/exam-bodies" element={<ExamBodies />} />
            <Route path="/exam-bodies/create" element={<ExamBodyForm />} />
            <Route
              path="/exam-bodies/:examBodyId/edit"
              element={<ExamBodyForm />}
            />
            <Route path="/certification-levels" element={<CertificationLevels />} />
            <Route path="/certification-levels/create" element={<CertificationLevelForm />} />
            <Route
              path="/certification-levels/:levelId/edit"
              element={<CertificationLevelForm />}
            />
            <Route path="/staff" element={<Staff />} />
            <Route path="/staff/create" element={<StaffForm />} />
            <Route path="/staff/:staffId/edit" element={<StaffForm />} />
            <Route path="/activities" element={<Activities />} />
          </Route>
        </Route>
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
      <Toaster position="top-right" richColors closeButton />
    </AuthProvider>
  )
}
