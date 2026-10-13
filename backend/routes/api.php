<?php

use App\Http\Controllers\Activities\ActivityController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CertificationLevels\CertificationLevelController;
use App\Http\Controllers\Departments\DepartmentController;
use App\Http\Controllers\ExamBodies\ExamBodyController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Staff\StaffController;
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

    Route::get('activities', [ActivityController::class, 'index'])
        ->middleware('role:admin')
        ->middleware('throttle:api')
        ->name('activities.index');
});
