<?php

use App\Http\Controllers\AcademicSessions\AcademicSessionController;
use App\Http\Controllers\AcademicYears\AcademicYearController;
use App\Http\Controllers\Activities\ActivityController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordUpdateController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CertificationLevels\CertificationLevelController;
use App\Http\Controllers\CourseFeeAssignments\CourseFeeAssignmentController;
use App\Http\Controllers\Courses\CourseController;
use App\Http\Controllers\CourseUnits\CourseUnitController;
use App\Http\Controllers\Curriculums\CurriculumController;
use App\Http\Controllers\Departments\DepartmentController;
use App\Http\Controllers\Enrolments\EnrolmentController;
use App\Http\Controllers\ExamBodies\ExamBodyController;
use App\Http\Controllers\ExamBodyGrades\ExamBodyGradeController;
use App\Http\Controllers\FeeStructures\FeeStructureController;
use App\Http\Controllers\FeeStructures\FeeStructureItemController;
use App\Http\Controllers\Me\DashboardController;
use App\Http\Controllers\Me\MyGradesController;
use App\Http\Controllers\Me\MyUnitsController;
use App\Http\Controllers\Me\ReportSessionController;
use App\Http\Controllers\Me\SessionUnitsController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\SessionOfferings\SessionOfferingController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Students\StudentController;
use App\Http\Controllers\TermEvents\TermEventController;
use App\Http\Controllers\UnitGrades\UnitGradeController;
use App\Http\Controllers\Units\UnitController;
use App\Http\Controllers\Users\UserController;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('throttle:auth')
    ->name('register');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:auth')
    ->name('login');

Route::post('/refresh', [AuthenticatedSessionController::class, 'refresh'])
    ->middleware('throttle:session')
    ->name('refresh');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('throttle:session')
    ->name('logout');

Route::get('/user', function (Request $request): JsonResponse {
    return response()->json(['user' => new UserResource($request->user())]);
})->middleware(['auth:api', 'access.type', 'throttle:api'])->name('user');

/*
|--------------------------------------------------------------------------
| Authenticated API routes
|--------------------------------------------------------------------------
|
| Every route below is tagged with its own `permission:` middleware so each
| endpoint carries the exact ability that decides access — permissions,
| never role names. Roles bundle these permissions and a user may hold
| several, so granting access is a matter of assigning a role (or a
| permission) rather than editing routes, and with its own `throttle:` tag
| for rate limiting. There is deliberately no shared permission group —
| edit a single line to widen a single endpoint.
|
*/
Route::middleware(['auth:api', 'access.type'])->group(function () {
    // Self-service: every signed-in user may change their own password,
    // so unlike the routes below this one carries no permission gate.
    Route::match(['PUT', 'PATCH'], 'profile/password', [PasswordUpdateController::class, 'update'])
        ->middleware('throttle:api')
        ->name('profile.password.update');

    // The student's own portal: dashboard, units, session registration
    // and marks for the signed-in account only — one permission covers
    // the whole self-service area.
    Route::get('me/dashboard', [DashboardController::class, '__invoke'])
        ->middleware('permission:student-portal')
        ->middleware('throttle:api')
        ->name('me.dashboard');

    Route::get('me/units', MyUnitsController::class)
        ->middleware('permission:student-portal')
        ->middleware('throttle:api')
        ->name('me.units');

    Route::post('me/report-session', ReportSessionController::class)
        ->middleware('permission:student-portal')
        ->middleware('throttle:api')
        ->name('me.report-session');

    Route::get('me/session-units', [SessionUnitsController::class, 'index'])
        ->middleware('permission:student-portal')
        ->middleware('throttle:api')
        ->name('me.session-units.index');

    Route::post('me/session-units', [SessionUnitsController::class, 'store'])
        ->middleware('permission:student-portal')
        ->middleware('throttle:api')
        ->name('me.session-units.store');

    Route::get('me/grades', MyGradesController::class)
        ->middleware('permission:student-portal')
        ->middleware('throttle:api')
        ->name('me.grades');

    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('permission:roles.view')
        ->middleware('throttle:api')
        ->name('roles.index');

    Route::get('users', [UserController::class, 'index'])
        ->middleware('permission:users.view')
        ->middleware('throttle:api')
        ->name('users.index');

    Route::get('departments', [DepartmentController::class, 'index'])
        ->middleware('permission:departments.view')
        ->middleware('throttle:api')
        ->name('departments.index');
    Route::post('departments', [DepartmentController::class, 'store'])
        ->middleware('permission:departments.manage')
        ->middleware('throttle:api')
        ->name('departments.store');
    Route::get('departments/{department}', [DepartmentController::class, 'show'])
        ->middleware('permission:departments.view')
        ->middleware('throttle:api')
        ->name('departments.show');
    Route::match(['PUT', 'PATCH'], 'departments/{department}', [DepartmentController::class, 'update'])
        ->middleware('permission:departments.manage')
        ->middleware('throttle:api')
        ->name('departments.update');
    Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])
        ->middleware('permission:departments.manage')
        ->middleware('throttle:api')
        ->name('departments.destroy');

    Route::get('exam-bodies', [ExamBodyController::class, 'index'])
        ->middleware('permission:exam-bodies.view')
        ->middleware('throttle:api')
        ->name('exam-bodies.index');
    Route::post('exam-bodies', [ExamBodyController::class, 'store'])
        ->middleware('permission:exam-bodies.manage')
        ->middleware('throttle:api')
        ->name('exam-bodies.store');
    Route::get('exam-bodies/{examBody}', [ExamBodyController::class, 'show'])
        ->middleware('permission:exam-bodies.view')
        ->middleware('throttle:api')
        ->name('exam-bodies.show');
    Route::match(['PUT', 'PATCH'], 'exam-bodies/{examBody}', [ExamBodyController::class, 'update'])
        ->middleware('permission:exam-bodies.manage')
        ->middleware('throttle:api')
        ->name('exam-bodies.update');
    Route::delete('exam-bodies/{examBody}', [ExamBodyController::class, 'destroy'])
        ->middleware('permission:exam-bodies.manage')
        ->middleware('throttle:api')
        ->name('exam-bodies.destroy');

    Route::get('exam-body-grades', [ExamBodyGradeController::class, 'index'])
        ->middleware('permission:exam-body-grades.view')
        ->middleware('throttle:api')
        ->name('exam-body-grades.index');
    Route::post('exam-body-grades', [ExamBodyGradeController::class, 'store'])
        ->middleware('permission:exam-body-grades.manage')
        ->middleware('throttle:api')
        ->name('exam-body-grades.store');
    Route::get('exam-body-grades/{examBodyGrade}', [ExamBodyGradeController::class, 'show'])
        ->middleware('permission:exam-body-grades.view')
        ->middleware('throttle:api')
        ->name('exam-body-grades.show');
    Route::match(['PUT', 'PATCH'], 'exam-body-grades/{examBodyGrade}', [ExamBodyGradeController::class, 'update'])
        ->middleware('permission:exam-body-grades.manage')
        ->middleware('throttle:api')
        ->name('exam-body-grades.update');
    Route::delete('exam-body-grades/{examBodyGrade}', [ExamBodyGradeController::class, 'destroy'])
        ->middleware('permission:exam-body-grades.manage')
        ->middleware('throttle:api')
        ->name('exam-body-grades.destroy');

    // Unit grading: award theory / practical marks for a student's
    // registered units; three of each type fully grade a unit. The
    // console picks run session → unit → student → paper, so the two
    // lookups feed it before the payload endpoint. Reading the console
    // needs view; awarding marks needs manage.
    Route::get('unit-grades/units', [UnitGradeController::class, 'units'])
        ->middleware('permission:unit-grades.view')
        ->middleware('throttle:api')
        ->name('unit-grades.units');
    Route::get('unit-grades/sheet/export', [UnitGradeController::class, 'export'])
        ->middleware('permission:unit-grades.view')
        ->middleware('throttle:api')
        ->name('unit-grades.sheet.export');
    Route::get('unit-grades/students', [UnitGradeController::class, 'students'])
        ->middleware('permission:unit-grades.view')
        ->middleware('throttle:api')
        ->name('unit-grades.students');
    Route::get('unit-grades/sheet', [UnitGradeController::class, 'sheet'])
        ->middleware('permission:unit-grades.view')
        ->middleware('throttle:api')
        ->name('unit-grades.sheet');
    Route::get('unit-grades', [UnitGradeController::class, 'index'])
        ->middleware('permission:unit-grades.view')
        ->middleware('throttle:api')
        ->name('unit-grades.index');
    Route::put('unit-grades', [UnitGradeController::class, 'save'])
        ->middleware('permission:unit-grades.manage')
        ->middleware('throttle:api')
        ->name('unit-grades.save');

    Route::get('certification-levels', [CertificationLevelController::class, 'index'])
        ->middleware('permission:certification-levels.view')
        ->middleware('throttle:api')
        ->name('certification-levels.index');
    Route::post('certification-levels', [CertificationLevelController::class, 'store'])
        ->middleware('permission:certification-levels.manage')
        ->middleware('throttle:api')
        ->name('certification-levels.store');
    Route::get('certification-levels/{certificationLevel}', [CertificationLevelController::class, 'show'])
        ->middleware('permission:certification-levels.view')
        ->middleware('throttle:api')
        ->name('certification-levels.show');
    Route::match(['PUT', 'PATCH'], 'certification-levels/{certificationLevel}', [CertificationLevelController::class, 'update'])
        ->middleware('permission:certification-levels.manage')
        ->middleware('throttle:api')
        ->name('certification-levels.update');
    Route::delete('certification-levels/{certificationLevel}', [CertificationLevelController::class, 'destroy'])
        ->middleware('permission:certification-levels.manage')
        ->middleware('throttle:api')
        ->name('certification-levels.destroy');

    // Fee structures: the named fee plans the school charges against.
    // The Fees module's first building block; finance shares these with
    // the admins who set them up.
    Route::get('fee-structures', [FeeStructureController::class, 'index'])
        ->middleware('permission:fee-structures.view')
        ->middleware('throttle:api')
        ->name('fee-structures.index');
    Route::post('fee-structures', [FeeStructureController::class, 'store'])
        ->middleware('permission:fee-structures.manage')
        ->middleware('throttle:api')
        ->name('fee-structures.store');
    Route::get('fee-structures/{feeStructure}', [FeeStructureController::class, 'show'])
        ->middleware('permission:fee-structures.view')
        ->middleware('throttle:api')
        ->name('fee-structures.show');
    Route::match(['PUT', 'PATCH'], 'fee-structures/{feeStructure}', [FeeStructureController::class, 'update'])
        ->middleware('permission:fee-structures.manage')
        ->middleware('throttle:api')
        ->name('fee-structures.update');
    Route::delete('fee-structures/{feeStructure}', [FeeStructureController::class, 'destroy'])
        ->middleware('permission:fee-structures.manage')
        ->middleware('throttle:api')
        ->name('fee-structures.destroy');

    // Fee structure items: the named charges nested under their structure
    // so the parent is always known, and their live amounts sum into the
    // structure's total.
    Route::get('fee-structures/{feeStructure}/items', [FeeStructureItemController::class, 'index'])
        ->middleware('permission:fee-structures.view')
        ->middleware('throttle:api')
        ->name('fee-structures.items.index');
    Route::post('fee-structures/{feeStructure}/items', [FeeStructureItemController::class, 'store'])
        ->middleware('permission:fee-structures.manage')
        ->middleware('throttle:api')
        ->name('fee-structures.items.store');
    Route::match(['PUT', 'PATCH'], 'fee-structures/{feeStructure}/items/{feeStructureItem}', [FeeStructureItemController::class, 'update'])
        ->middleware('permission:fee-structures.manage')
        ->middleware('throttle:api')
        ->name('fee-structures.items.update');
    Route::delete('fee-structures/{feeStructure}/items/{feeStructureItem}', [FeeStructureItemController::class, 'destroy'])
        ->middleware('permission:fee-structures.manage')
        ->middleware('throttle:api')
        ->name('fee-structures.items.destroy');

    // Course fee assignments: which fee structure a course + curriculum
    // pair charges in a given year and session, and over what span. The
    // pair is what an enrolment carries, so a student's fee is later
    // located from their enrolment alone — one live assignment per slot.
    Route::get('course-fee-assignments', [CourseFeeAssignmentController::class, 'index'])
        ->middleware('permission:course-fee-assignments.view')
        ->middleware('throttle:api')
        ->name('course-fee-assignments.index');
    Route::post('course-fee-assignments', [CourseFeeAssignmentController::class, 'store'])
        ->middleware('permission:course-fee-assignments.manage')
        ->middleware('throttle:api')
        ->name('course-fee-assignments.store');
    Route::get('course-fee-assignments/{courseFeeAssignment}', [CourseFeeAssignmentController::class, 'show'])
        ->middleware('permission:course-fee-assignments.view')
        ->middleware('throttle:api')
        ->name('course-fee-assignments.show');
    Route::match(['PUT', 'PATCH'], 'course-fee-assignments/{courseFeeAssignment}', [CourseFeeAssignmentController::class, 'update'])
        ->middleware('permission:course-fee-assignments.manage')
        ->middleware('throttle:api')
        ->name('course-fee-assignments.update');
    Route::delete('course-fee-assignments/{courseFeeAssignment}', [CourseFeeAssignmentController::class, 'destroy'])
        ->middleware('permission:course-fee-assignments.manage')
        ->middleware('throttle:api')
        ->name('course-fee-assignments.destroy');

    Route::get('curricula', [CurriculumController::class, 'index'])
        ->middleware('permission:curricula.view')
        ->middleware('throttle:api')
        ->name('curricula.index');
    Route::post('curricula', [CurriculumController::class, 'store'])
        ->middleware('permission:curricula.manage')
        ->middleware('throttle:api')
        ->name('curricula.store');
    Route::get('curricula/{curriculum}', [CurriculumController::class, 'show'])
        ->middleware('permission:curricula.view')
        ->middleware('throttle:api')
        ->name('curricula.show');
    Route::match(['PUT', 'PATCH'], 'curricula/{curriculum}', [CurriculumController::class, 'update'])
        ->middleware('permission:curricula.manage')
        ->middleware('throttle:api')
        ->name('curricula.update');
    Route::delete('curricula/{curriculum}', [CurriculumController::class, 'destroy'])
        ->middleware('permission:curricula.manage')
        ->middleware('throttle:api')
        ->name('curricula.destroy');

    Route::get('courses', [CourseController::class, 'index'])
        ->middleware('permission:courses.view')
        ->middleware('throttle:api')
        ->name('courses.index');
    Route::post('courses', [CourseController::class, 'store'])
        ->middleware('permission:courses.manage')
        ->middleware('throttle:api')
        ->name('courses.store');
    Route::get('courses/{course}', [CourseController::class, 'show'])
        ->middleware('permission:courses.view')
        ->middleware('throttle:api')
        ->name('courses.show');
    Route::match(['PUT', 'PATCH'], 'courses/{course}', [CourseController::class, 'update'])
        ->middleware('permission:courses.manage')
        ->middleware('throttle:api')
        ->name('courses.update');
    Route::delete('courses/{course}', [CourseController::class, 'destroy'])
        ->middleware('permission:courses.manage')
        ->middleware('throttle:api')
        ->name('courses.destroy');

    Route::get('units', [UnitController::class, 'index'])
        ->middleware('permission:units.view')
        ->middleware('throttle:api')
        ->name('units.index');
    Route::post('units', [UnitController::class, 'store'])
        ->middleware('permission:units.manage')
        ->middleware('throttle:api')
        ->name('units.store');
    Route::get('units/{unit}', [UnitController::class, 'show'])
        ->middleware('permission:units.view')
        ->middleware('throttle:api')
        ->name('units.show');
    Route::match(['PUT', 'PATCH'], 'units/{unit}', [UnitController::class, 'update'])
        ->middleware('permission:units.manage')
        ->middleware('throttle:api')
        ->name('units.update');
    Route::delete('units/{unit}', [UnitController::class, 'destroy'])
        ->middleware('permission:units.manage')
        ->middleware('throttle:api')
        ->name('units.destroy');

    // The course-unit links: how catalogue units are delivered inside
    // each course — the wizard's second step and the standalone
    // Course Units page.
    Route::get('course-units', [CourseUnitController::class, 'index'])
        ->middleware('permission:course-units.view')
        ->middleware('throttle:api')
        ->name('course-units.index');
    Route::post('course-units', [CourseUnitController::class, 'store'])
        ->middleware('permission:course-units.manage')
        ->middleware('throttle:api')
        ->name('course-units.store');
    Route::get('course-units/{courseUnit}', [CourseUnitController::class, 'show'])
        ->middleware('permission:course-units.view')
        ->middleware('throttle:api')
        ->name('course-units.show');
    Route::match(['PUT', 'PATCH'], 'course-units/{courseUnit}', [CourseUnitController::class, 'update'])
        ->middleware('permission:course-units.manage')
        ->middleware('throttle:api')
        ->name('course-units.update');
    Route::delete('course-units/{courseUnit}', [CourseUnitController::class, 'destroy'])
        ->middleware('permission:course-units.manage')
        ->middleware('throttle:api')
        ->name('course-units.destroy');

    Route::get('courses/{course}/units', [CourseUnitController::class, 'byCourse'])
        ->middleware('permission:course-units.view')
        ->middleware('throttle:api')
        ->name('course-units.by-course');

    Route::get('academic-years', [AcademicYearController::class, 'index'])
        ->middleware('permission:academic-years.view')
        ->middleware('throttle:api')
        ->name('academic-years.index');
    Route::post('academic-years', [AcademicYearController::class, 'store'])
        ->middleware('permission:academic-years.manage')
        ->middleware('throttle:api')
        ->name('academic-years.store');
    Route::get('academic-years/{academicYear}', [AcademicYearController::class, 'show'])
        ->middleware('permission:academic-years.view')
        ->middleware('throttle:api')
        ->name('academic-years.show');
    Route::match(['PUT', 'PATCH'], 'academic-years/{academicYear}', [AcademicYearController::class, 'update'])
        ->middleware('permission:academic-years.manage')
        ->middleware('throttle:api')
        ->name('academic-years.update');
    Route::delete('academic-years/{academicYear}', [AcademicYearController::class, 'destroy'])
        ->middleware('permission:academic-years.manage')
        ->middleware('throttle:api')
        ->name('academic-years.destroy');

    Route::get('academic-sessions', [AcademicSessionController::class, 'index'])
        ->middleware('permission:academic-sessions.view')
        ->middleware('throttle:api')
        ->name('academic-sessions.index');
    Route::post('academic-sessions', [AcademicSessionController::class, 'store'])
        ->middleware('permission:academic-sessions.manage')
        ->middleware('throttle:api')
        ->name('academic-sessions.store');
    Route::get('academic-sessions/{academicSession}', [AcademicSessionController::class, 'show'])
        ->middleware('permission:academic-sessions.view')
        ->middleware('throttle:api')
        ->name('academic-sessions.show');
    Route::match(['PUT', 'PATCH'], 'academic-sessions/{academicSession}', [AcademicSessionController::class, 'update'])
        ->middleware('permission:academic-sessions.manage')
        ->middleware('throttle:api')
        ->name('academic-sessions.update');
    Route::delete('academic-sessions/{academicSession}', [AcademicSessionController::class, 'destroy'])
        ->middleware('permission:academic-sessions.manage')
        ->middleware('throttle:api')
        ->name('academic-sessions.destroy');

    // Opening a session is gated by permission, so per-user and per-role
    // grants flow through Spatie's tables.
    Route::get('session-offerings', [SessionOfferingController::class, 'index'])
        ->middleware('permission:session-offerings.view')
        ->middleware('throttle:api')
        ->name('session-offerings.index');
    Route::patch('session-offerings/stage', [SessionOfferingController::class, 'toggle'])
        ->middleware('permission:session-offerings.manage')
        ->middleware('throttle:api')
        ->name('session-offerings.toggle');

    Route::get('term-events', [TermEventController::class, 'index'])
        ->middleware('permission:term-events.view')
        ->middleware('throttle:api')
        ->name('term-events.index');
    Route::post('term-events', [TermEventController::class, 'store'])
        ->middleware('permission:term-events.manage')
        ->middleware('throttle:api')
        ->name('term-events.store');
    Route::get('term-events/{termEvent}', [TermEventController::class, 'show'])
        ->middleware('permission:term-events.view')
        ->middleware('throttle:api')
        ->name('term-events.show');
    Route::match(['PUT', 'PATCH'], 'term-events/{termEvent}', [TermEventController::class, 'update'])
        ->middleware('permission:term-events.manage')
        ->middleware('throttle:api')
        ->name('term-events.update');
    Route::delete('term-events/{termEvent}', [TermEventController::class, 'destroy'])
        ->middleware('permission:term-events.manage')
        ->middleware('throttle:api')
        ->name('term-events.destroy');

    Route::get('staff', [StaffController::class, 'index'])
        ->middleware('permission:staff.view')
        ->middleware('throttle:api')
        ->name('staff.index');
    Route::post('staff', [StaffController::class, 'store'])
        ->middleware('permission:staff.manage')
        ->middleware('throttle:api')
        ->name('staff.store');
    Route::get('staff/{staff}', [StaffController::class, 'show'])
        ->middleware('permission:staff.view')
        ->middleware('throttle:api')
        ->name('staff.show');
    Route::match(['PUT', 'PATCH'], 'staff/{staff}', [StaffController::class, 'update'])
        ->middleware('permission:staff.manage')
        ->middleware('throttle:api')
        ->name('staff.update');
    Route::delete('staff/{staff}', [StaffController::class, 'destroy'])
        ->middleware('permission:staff.manage')
        ->middleware('throttle:api')
        ->name('staff.destroy');

    Route::get('students', [StudentController::class, 'index'])
        ->middleware('permission:students.view')
        ->middleware('throttle:api')
        ->name('students.index');
    Route::post('students', [StudentController::class, 'store'])
        ->middleware('permission:students.manage')
        ->middleware('throttle:api')
        ->name('students.store');
    Route::get('students/{student}', [StudentController::class, 'show'])
        ->middleware('permission:students.view')
        ->middleware('throttle:api')
        ->name('students.show');
    Route::match(['PUT', 'PATCH'], 'students/{student}', [StudentController::class, 'update'])
        ->middleware('permission:students.manage')
        ->middleware('throttle:api')
        ->name('students.update');
    Route::delete('students/{student}', [StudentController::class, 'destroy'])
        ->middleware('permission:students.manage')
        ->middleware('throttle:api')
        ->name('students.destroy');

    Route::get('enrolments', [EnrolmentController::class, 'index'])
        ->middleware('permission:enrolments.view')
        ->middleware('throttle:api')
        ->name('enrolments.index');
    Route::get('enrolments/{enrolment}', [EnrolmentController::class, 'show'])
        ->middleware('permission:enrolments.view')
        ->middleware('throttle:api')
        ->name('enrolments.show');
    Route::delete('enrolments/{enrolment}', [EnrolmentController::class, 'destroy'])
        ->middleware('permission:enrolments.manage')
        ->middleware('throttle:api')
        ->name('enrolments.destroy');

    Route::get('activities', [ActivityController::class, 'index'])
        ->middleware('permission:activities.view')
        ->middleware('throttle:api')
        ->name('activities.index');
});
