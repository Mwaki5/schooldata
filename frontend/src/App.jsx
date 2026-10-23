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
const ExamBodyGrades = lazy(() => import('@/pages/exam-body-grades/ExamBodyGrades'))
const ExamBodyGradeForm = lazy(() => import('@/pages/exam-body-grades/ExamBodyGradeForm'))
const FeeStructures = lazy(() => import('@/pages/fee-structures/FeeStructures'))
const FeeStructureForm = lazy(() => import('@/pages/fee-structures/FeeStructureForm'))
const FeeStructureItems = lazy(() => import('@/pages/fee-structures/FeeStructureItems'))
const CourseFeeAssignments = lazy(() => import('@/pages/course-fee-assignments/CourseFeeAssignments'))
const CourseFeeAssignmentForm = lazy(() => import('@/pages/course-fee-assignments/CourseFeeAssignmentForm'))
const CertificationLevels = lazy(() => import('@/pages/certification-levels/CertificationLevels'))
const CertificationLevelForm = lazy(() => import('@/pages/certification-levels/CertificationLevelForm'))
const Curricula = lazy(() => import('@/pages/curricula/Curricula'))
const CurriculumForm = lazy(() => import('@/pages/curricula/CurriculumForm'))
const Courses = lazy(() => import('@/pages/courses/Courses'))
const CourseForm = lazy(() => import('@/pages/courses/CourseForm'))
const CourseUnits = lazy(() => import('@/pages/units/CourseUnits'))
const Units = lazy(() => import('@/pages/units/Units'))
const UnitForm = lazy(() => import('@/pages/units/UnitForm'))
const UnitDetails = lazy(() => import('@/pages/units/UnitDetails'))
const CourseUnitList = lazy(() => import('@/pages/course-units/CourseUnits'))
const CourseUnitForm = lazy(() => import('@/pages/course-units/CourseUnitForm'))
const AcademicYears = lazy(() => import('@/pages/academic-years/AcademicYears'))
const AcademicYearForm = lazy(() => import('@/pages/academic-years/AcademicYearForm'))
const AcademicSessions = lazy(() => import('@/pages/academic-sessions/AcademicSessions'))
const AcademicSessionForm = lazy(() => import('@/pages/academic-sessions/AcademicSessionForm'))
const TermEvents = lazy(() => import('@/pages/term-events/TermEvents'))
const TermEventForm = lazy(() => import('@/pages/term-events/TermEventForm'))
const Staff = lazy(() => import('@/pages/staff/Staff'))
const StaffForm = lazy(() => import('@/pages/staff/StaffForm'))
const Students = lazy(() => import('@/pages/students/Students'))
const StudentForm = lazy(() => import('@/pages/students/StudentForm'))
const Enrolments = lazy(() => import('@/pages/enrolments/Enrolments'))
const Activities = lazy(() => import('@/pages/activities/Activities'))
const Profile = lazy(() => import('@/pages/profile/Profile'))
const MyGrades = lazy(() => import('@/pages/grades/Grades'))
const MyUnits = lazy(() => import('@/pages/my-units/MyUnits'))
const OpenSession = lazy(() => import('@/pages/session-offerings/OpenSession'))
const UnitGrades = lazy(() => import('@/pages/unit-grades/UnitGrades'))
const Marksheet = lazy(() => import('@/pages/marksheet/Marksheet'))

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
 * Gates a section behind one permission (`permission="departments.view"`).
 * Permissions decide access — roles bundle them and a user may hold
 * several — and the backend enforces the same names with its
 * `permission:` middleware, so this guard only keeps the UI honest:
 * unauthorised users are sent back to the dashboard.
 */
function PermissionRoute({ permission, children }) {
  const { user, loading } = useAuth()

  if (loading) {
    return <PageLoader fullPage />
  }

  if (!user || !user.permissions?.includes(permission)) {
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
          {/* Any signed-in user may view their own profile data. */}
          <Route path="/profile" element={<Profile />} />
          {/* Student portal: the backend tags these routes with
              `permission:student-portal`. */}
          <Route
            element={
              <PermissionRoute permission="student-portal">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/my-units" element={<MyUnits />} />
            <Route path="/graded" element={<MyGrades />} />
          </Route>
          {/* Each module has one permission per verb: `.view` to read
              and `.create`/`.update`/`.delete` to write. This guard
              mirrors the view side; form routes name their own verb. */}
          <Route
            element={
              <PermissionRoute permission="roles.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/roles" element={<Roles />} />
          </Route>
          <Route
            element={
              <PermissionRoute permission="departments.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/departments" element={<Departments />} />
            <Route
              path="/departments/create"
              element={
                <PermissionRoute permission="departments.create">
                  <DepartmentForm />
                </PermissionRoute>
              }
            />
            <Route
              path="/departments/:departmentId/edit"
              element={
                <PermissionRoute permission="departments.update">
                  <DepartmentForm />
                </PermissionRoute>
              }
            />
          </Route>
          <Route
            element={
              <PermissionRoute permission="exam-bodies.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/exam-bodies" element={<ExamBodies />} />
            <Route
              path="/exam-bodies/create"
              element={
                <PermissionRoute permission="exam-bodies.create">
                  <ExamBodyForm />
                </PermissionRoute>
              }
            />
            <Route
              path="/exam-bodies/:examBodyId/edit"
              element={
                <PermissionRoute permission="exam-bodies.update">
                  <ExamBodyForm />
                </PermissionRoute>
              }
            />
          </Route>
          <Route
            element={
              <PermissionRoute permission="exam-body-grades.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/exam-body-grades" element={<ExamBodyGrades />} />
            <Route
              path="/exam-body-grades/create"
              element={
                <PermissionRoute permission="exam-body-grades.create">
                  <ExamBodyGradeForm />
                </PermissionRoute>
              }
            />
            <Route
              path="/exam-body-grades/:gradeId/edit"
              element={
                <PermissionRoute permission="exam-body-grades.update">
                  <ExamBodyGradeForm />
                </PermissionRoute>
              }
            />
          </Route>
          <Route
            element={
              <PermissionRoute permission="fee-structures.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/fee-structures" element={<FeeStructures />} />
            <Route
              path="/fee-structures/create"
              element={
                <PermissionRoute permission="fee-structures.create">
                  <FeeStructureForm />
                </PermissionRoute>
              }
            />
            <Route
              path="/fee-structures/:feeStructureId/items"
              element={<FeeStructureItems />}
            />
            <Route
              path="/fee-structures/:feeStructureId/edit"
              element={
                <PermissionRoute permission="fee-structures.update">
                  <FeeStructureForm />
                </PermissionRoute>
              }
            />
          </Route>
          <Route
            element={
              <PermissionRoute permission="course-fee-assignments.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route
              path="/course-fee-assignments"
              element={<CourseFeeAssignments />}
            />
            <Route
              path="/course-fee-assignments/create"
              element={
                <PermissionRoute permission="course-fee-assignments.create">
                  <CourseFeeAssignmentForm />
                </PermissionRoute>
              }
            />
            <Route
              path="/course-fee-assignments/:courseFeeAssignmentId/edit"
              element={
                <PermissionRoute permission="course-fee-assignments.update">
                  <CourseFeeAssignmentForm />
                </PermissionRoute>
              }
            />
          </Route>
          <Route
            element={
              <PermissionRoute permission="certification-levels.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/certification-levels" element={<CertificationLevels />} />
            <Route
              path="/certification-levels/create"
              element={
                <PermissionRoute permission="certification-levels.create">
                  <CertificationLevelForm />
                </PermissionRoute>
              }
            />
            <Route
              path="/certification-levels/:levelId/edit"
              element={
                <PermissionRoute permission="certification-levels.update">
                  <CertificationLevelForm />
                </PermissionRoute>
              }
            />
          </Route>
          <Route
            element={
              <PermissionRoute permission="curricula.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/curricula" element={<Curricula />} />
            <Route
              path="/curricula/create"
              element={
                <PermissionRoute permission="curricula.create">
                  <CurriculumForm />
                </PermissionRoute>
              }
            />
            <Route
              path="/curricula/:curriculumId/edit"
              element={
                <PermissionRoute permission="curricula.update">
                  <CurriculumForm />
                </PermissionRoute>
              }
            />
          </Route>
          <Route
            element={
              <PermissionRoute permission="courses.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/courses" element={<Courses />} />
            <Route
              path="/courses/create"
              element={
                <PermissionRoute permission="courses.create">
                  <CourseForm />
                </PermissionRoute>
              }
            />
            <Route
              path="/courses/:courseId/units"
              element={<CourseUnits />}
            />
            <Route
              path="/courses/:courseId/edit"
              element={
                <PermissionRoute permission="courses.update">
                  <CourseForm />
                </PermissionRoute>
              }
            />
          </Route>
          <Route
            element={
              <PermissionRoute permission="units.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/units" element={<Units />} />
            <Route
              path="/units/create"
              element={
                <PermissionRoute permission="units.create">
                  <UnitForm />
                </PermissionRoute>
              }
            />
            <Route path="/units/:unitId" element={<UnitDetails />} />
            <Route
              path="/units/:unitId/edit"
              element={
                <PermissionRoute permission="units.update">
                  <UnitForm />
                </PermissionRoute>
              }
            />
          </Route>
          <Route
            element={
              <PermissionRoute permission="course-units.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/course-units" element={<CourseUnitList />} />
            <Route
              path="/course-units/create"
              element={
                <PermissionRoute permission="course-units.create">
                  <CourseUnitForm />
                </PermissionRoute>
              }
            />
            <Route
              path="/course-units/:courseUnitId/edit"
              element={
                <PermissionRoute permission="course-units.update">
                  <CourseUnitForm />
                </PermissionRoute>
              }
            />
          </Route>
          <Route
            element={
              <PermissionRoute permission="academic-years.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/academic-years" element={<AcademicYears />} />
            <Route
              path="/academic-years/create"
              element={
                <PermissionRoute permission="academic-years.create">
                  <AcademicYearForm />
                </PermissionRoute>
              }
            />
            <Route
              path="/academic-years/:yearId/edit"
              element={
                <PermissionRoute permission="academic-years.update">
                  <AcademicYearForm />
                </PermissionRoute>
              }
            />
          </Route>
          <Route
            element={
              <PermissionRoute permission="academic-sessions.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/academic-sessions" element={<AcademicSessions />} />
            <Route
              path="/academic-sessions/create"
              element={
                <PermissionRoute permission="academic-sessions.create">
                  <AcademicSessionForm />
                </PermissionRoute>
              }
            />
            <Route
              path="/academic-sessions/:sessionId/edit"
              element={
                <PermissionRoute permission="academic-sessions.update">
                  <AcademicSessionForm />
                </PermissionRoute>
              }
            />
          </Route>
          <Route
            element={
              <PermissionRoute permission="session-offerings.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/session-offerings" element={<OpenSession />} />
          </Route>
          <Route
            element={
              <PermissionRoute permission="term-events.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/term-events" element={<TermEvents />} />
            <Route
              path="/term-events/create"
              element={
                <PermissionRoute permission="term-events.create">
                  <TermEventForm />
                </PermissionRoute>
              }
            />
            <Route
              path="/term-events/:eventId/edit"
              element={
                <PermissionRoute permission="term-events.update">
                  <TermEventForm />
                </PermissionRoute>
              }
            />
          </Route>
          <Route
            element={
              <PermissionRoute permission="staff.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/staff" element={<Staff />} />
            <Route
              path="/staff/create"
              element={
                <PermissionRoute permission="staff.create">
                  <StaffForm />
                </PermissionRoute>
              }
            />
            <Route
              path="/staff/:staffId/edit"
              element={
                <PermissionRoute permission="staff.update">
                  <StaffForm />
                </PermissionRoute>
              }
            />
          </Route>
          <Route
            element={
              <PermissionRoute permission="students.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/students" element={<Students />} />
            <Route
              path="/students/create"
              element={
                <PermissionRoute permission="students.create">
                  <StudentForm />
                </PermissionRoute>
              }
            />
            <Route
              path="/students/:studentId/edit"
              element={
                <PermissionRoute permission="students.update">
                  <StudentForm />
                </PermissionRoute>
              }
            />
          </Route>
          <Route
            element={
              <PermissionRoute permission="enrolments.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/enrolments" element={<Enrolments />} />
          </Route>
          <Route
            element={
              <PermissionRoute permission="activities.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/activities" element={<Activities />} />
          </Route>
          {/* Grading console: the backend tags the reads with
              `permission:unit-grades.view` and awarding marks with
              `permission:unit-grades.update`. */}
          <Route
            element={
              <PermissionRoute permission="unit-grades.view">
                <Outlet />
              </PermissionRoute>
            }
          >
            <Route path="/unit-grades" element={<UnitGrades />} />
            <Route path="/marksheet" element={<Marksheet />} />
          </Route>
        </Route>
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
      <Toaster position="top-right" richColors closeButton />
    </AuthProvider>
  )
}
