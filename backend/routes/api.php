<?php

use App\Http\Controllers\AcademicSessions\AcademicSessionController;
use App\Http\Controllers\AcademicYears\AcademicYearController;
use App\Http\Controllers\Activities\ActivityController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CertificationLevels\CertificationLevelController;
use App\Http\Controllers\Courses\CourseController;
use App\Http\Controllers\CourseUnits\CourseUnitController;
use App\Http\Controllers\Curriculums\CurriculumController;
use App\Http\Controllers\Departments\DepartmentController;
use App\Http\Controllers\Enrolments\EnrolmentController;
use App\Http\Controllers\ExamBodies\ExamBodyController;
use App\Http\Controllers\ExamBodyGrades\ExamBodyGradeController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Students\StudentController;
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
| Every route below is tagged with its own `role:` middleware so each
| endpoint can carry an independent list of roles (e.g. role:admin,finance),
| and with its own `throttle:` tag for rate limiting. There is deliberately
| no shared role group — edit a single line to open a single route to more
| roles or to adjust its rate limit.
|
*/
Route::middleware(['auth:api', 'access.type'])->group(function () {
    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('roles.index');

    Route::get('users', [UserController::class, 'index'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('users.index');

    Route::get('departments', [DepartmentController::class, 'index'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('departments.index');
    Route::post('departments', [DepartmentController::class, 'store'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('departments.store');
    Route::get('departments/{department}', [DepartmentController::class, 'show'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('departments.show');
    Route::match(['PUT', 'PATCH'], 'departments/{department}', [DepartmentController::class, 'update'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('departments.update');
    Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('departments.destroy');

    Route::get('exam-bodies', [ExamBodyController::class, 'index'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('exam-bodies.index');
    Route::post('exam-bodies', [ExamBodyController::class, 'store'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('exam-bodies.store');
    Route::get('exam-bodies/{examBody}', [ExamBodyController::class, 'show'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('exam-bodies.show');
    Route::match(['PUT', 'PATCH'], 'exam-bodies/{examBody}', [ExamBodyController::class, 'update'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('exam-bodies.update');
    Route::delete('exam-bodies/{examBody}', [ExamBodyController::class, 'destroy'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('exam-bodies.destroy');

    Route::get('exam-body-grades', [ExamBodyGradeController::class, 'index'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('exam-body-grades.index');
    Route::post('exam-body-grades', [ExamBodyGradeController::class, 'store'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('exam-body-grades.store');
    Route::get('exam-body-grades/{examBodyGrade}', [ExamBodyGradeController::class, 'show'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('exam-body-grades.show');
    Route::match(['PUT', 'PATCH'], 'exam-body-grades/{examBodyGrade}', [ExamBodyGradeController::class, 'update'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('exam-body-grades.update');
    Route::delete('exam-body-grades/{examBodyGrade}', [ExamBodyGradeController::class, 'destroy'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('exam-body-grades.destroy');

    Route::get('certification-levels', [CertificationLevelController::class, 'index'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('certification-levels.index');
    Route::post('certification-levels', [CertificationLevelController::class, 'store'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('certification-levels.store');
    Route::get('certification-levels/{certificationLevel}', [CertificationLevelController::class, 'show'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('certification-levels.show');
    Route::match(['PUT', 'PATCH'], 'certification-levels/{certificationLevel}', [CertificationLevelController::class, 'update'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('certification-levels.update');
    Route::delete('certification-levels/{certificationLevel}', [CertificationLevelController::class, 'destroy'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('certification-levels.destroy');

    Route::get('curricula', [CurriculumController::class, 'index'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('curricula.index');
    Route::post('curricula', [CurriculumController::class, 'store'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('curricula.store');
    Route::get('curricula/{curriculum}', [CurriculumController::class, 'show'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('curricula.show');
    Route::match(['PUT', 'PATCH'], 'curricula/{curriculum}', [CurriculumController::class, 'update'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('curricula.update');
    Route::delete('curricula/{curriculum}', [CurriculumController::class, 'destroy'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('curricula.destroy');

    Route::get('courses', [CourseController::class, 'index'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('courses.index');
    Route::post('courses', [CourseController::class, 'store'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('courses.store');
    Route::get('courses/{course}', [CourseController::class, 'show'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('courses.show');
    Route::match(['PUT', 'PATCH'], 'courses/{course}', [CourseController::class, 'update'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('courses.update');
    Route::delete('courses/{course}', [CourseController::class, 'destroy'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('courses.destroy');

    Route::get('units/duplicates', [UnitController::class, 'duplicates'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('units.duplicates');
    Route::get('units', [UnitController::class, 'index'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('units.index');
    Route::post('units', [UnitController::class, 'store'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('units.store');
    Route::get('units/{unit}', [UnitController::class, 'show'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('units.show');
    Route::match(['PUT', 'PATCH'], 'units/{unit}', [UnitController::class, 'update'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('units.update');
    Route::delete('units/{unit}', [UnitController::class, 'destroy'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('units.destroy');

    Route::get('courses/{course}/units', [CourseUnitController::class, 'byCourse'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('course-units.by-course');
    Route::get('units/{unit}/assignments', [CourseUnitController::class, 'index'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('course-units.index');
    Route::post('units/{unit}/assignments', [CourseUnitController::class, 'store'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('course-units.store');
    Route::get('course-units/{courseUnit}', [CourseUnitController::class, 'show'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('course-units.show');
    Route::match(['PUT', 'PATCH'], 'course-units/{courseUnit}', [CourseUnitController::class, 'update'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('course-units.update');
    Route::delete('course-units/{courseUnit}', [CourseUnitController::class, 'destroy'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('course-units.destroy');

    Route::get('academic-years', [AcademicYearController::class, 'index'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('academic-years.index');
    Route::post('academic-years', [AcademicYearController::class, 'store'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('academic-years.store');
    Route::get('academic-years/{academicYear}', [AcademicYearController::class, 'show'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('academic-years.show');
    Route::match(['PUT', 'PATCH'], 'academic-years/{academicYear}', [AcademicYearController::class, 'update'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('academic-years.update');
    Route::delete('academic-years/{academicYear}', [AcademicYearController::class, 'destroy'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('academic-years.destroy');

    Route::get('academic-sessions', [AcademicSessionController::class, 'index'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('academic-sessions.index');
    Route::post('academic-sessions', [AcademicSessionController::class, 'store'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('academic-sessions.store');
    Route::get('academic-sessions/{academicSession}', [AcademicSessionController::class, 'show'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('academic-sessions.show');
    Route::match(['PUT', 'PATCH'], 'academic-sessions/{academicSession}', [AcademicSessionController::class, 'update'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('academic-sessions.update');
    Route::delete('academic-sessions/{academicSession}', [AcademicSessionController::class, 'destroy'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('academic-sessions.destroy');

    Route::get('staff', [StaffController::class, 'index'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('staff.index');
    Route::post('staff', [StaffController::class, 'store'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('staff.store');
    Route::get('staff/{staff}', [StaffController::class, 'show'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('staff.show');
    Route::match(['PUT', 'PATCH'], 'staff/{staff}', [StaffController::class, 'update'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('staff.update');
    Route::delete('staff/{staff}', [StaffController::class, 'destroy'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('staff.destroy');

    Route::get('students', [StudentController::class, 'index'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('students.index');
    Route::post('students', [StudentController::class, 'store'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('students.store');
    Route::get('students/{student}', [StudentController::class, 'show'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('students.show');
    Route::match(['PUT', 'PATCH'], 'students/{student}', [StudentController::class, 'update'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('students.update');
    Route::delete('students/{student}', [StudentController::class, 'destroy'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('students.destroy');

    Route::get('enrolments', [EnrolmentController::class, 'index'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('enrolments.index');
    Route::get('enrolments/{enrolment}', [EnrolmentController::class, 'show'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('enrolments.show');
    Route::delete('enrolments/{enrolment}', [EnrolmentController::class, 'destroy'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('enrolments.destroy');

    Route::get('activities', [ActivityController::class, 'index'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('activities.index');
});
