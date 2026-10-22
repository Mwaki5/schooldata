<?php

use App\Enums\Role;
use App\Models\Course;
use App\Models\CourseUnit;
use App\Models\Curriculum;
use App\Models\StudentProfile;
use App\Models\Unit;
use App\Models\UnitGrade;
use App\Models\UnitRegistration;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function myGradesAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

/**
 * One registered unit of the given student: a fresh session, the
 * catalogue unit plus its course-unit link (module, hours, type), and
 * the registration row that puts it on the student's sheet. Unit fields
 * (code, name) go on the unit; delivery fields go on the link.
 */
function myGradesRegistration(StudentProfile $student, array $overrides = []): UnitRegistration
{
    $unitFields = array_intersect_key($overrides, array_flip(['code', 'name']));
    $linkFields = array_intersect_key($overrides, array_flip(['modules_taught', 'taught_hours', 'credit_factor', 'unit_type', 'is_active']));

    $unit = Unit::factory()->create($unitFields);

    $courseUnit = CourseUnit::factory()->create(array_merge([
        'unit_id' => $unit->id,
        'course_id' => Course::factory(),
        'curriculum_id' => Curriculum::factory(),
    ], $linkFields));

    return UnitRegistration::factory()->create([
        'course_unit_id' => $courseUnit->id,
        'student_id' => $student->id,
    ]);
}

/**
 * Record one mark for a student on a registration's unit in the
 * registration's session, exactly like the grading console would.
 */
function myGradesMark(UnitRegistration $registration, string $examType, int $examNo, float $mark): UnitGrade
{
    return UnitGrade::factory()->create([
        'academic_session_id' => $registration->academic_session_id,
        'course_unit_id' => $registration->course_unit_id,
        'student_id' => $registration->student_id,
        'exam_type' => $examType,
        'exam_no' => $examNo,
        'mark' => $mark,
    ]);
}

test('my grades requires an access token', function () {
    $this->getJson('/api/me/grades')->assertUnauthorized();
});

test('my grades refuses non-student tokens', function () {
    $this->withToken(myGradesAccessTokenFor(User::factory()->create(['role' => Role::Admin])))
        ->getJson('/api/me/grades')
        ->assertForbidden();

    $this->withToken(myGradesAccessTokenFor(User::factory()->create(['role' => Role::Trainer])))
        ->getJson('/api/me/grades')
        ->assertForbidden();
});

test('my grades lists registered units with marks and averages, latest session first', function () {
    $user = User::factory()->create(['role' => Role::Student]);
    $student = StudentProfile::factory()->create(['user_id' => $user->id]);

    // Created first, so its auto-incremented session id is the older one.
    $older = myGradesRegistration($student, [
        'code' => 'DFDT 1101',
        'name' => 'Fashion Design I',
        'modules_taught' => 1,
        'unit_type' => 'core',
    ]);
    $newer = myGradesRegistration($student, [
        'code' => 'DFDT 1102',
        'name' => 'Pattern Making',
        'modules_taught' => 2,
        'unit_type' => 'basic',
    ]);

    myGradesMark($older, 'theory', 1, 58);
    myGradesMark($older, 'theory', 2, 66);
    myGradesMark($older, 'theory', 3, 72.5);
    myGradesMark($older, 'practical', 1, 80);
    myGradesMark($newer, 'theory', 1, 70);

    $this->withToken(myGradesAccessTokenFor($user))
        ->getJson('/api/me/grades')
        ->assertOk()
        ->assertJsonStructure([
            'rows' => [[
                'registration_id',
                'session' => ['id', 'name'],
                'unit' => ['id', 'code', 'name'],
                'course',
                'modules_taught',
                'unit_type',
                'theory',
                'theory_average',
                'practical',
                'practical_average',
            ]],
            'modules',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonCount(2, 'rows')
        // The latest session's unit comes first, its theory and practical
        // slots slotted per exam number with the averages beside them.
        ->assertJsonPath('rows.0.unit.code', 'DFDT 1102')
        ->assertJsonPath('rows.0.theory.0', 70)
        ->assertJsonPath('rows.0.theory.1', null)
        ->assertJsonPath('rows.0.theory_average', 70)
        ->assertJsonPath('rows.0.practical_average', null)
        ->assertJsonPath('rows.1.session.id', $older->academic_session_id)
        ->assertJsonPath('rows.1.unit.code', 'DFDT 1101')
        ->assertJsonPath('rows.1.unit.name', 'Fashion Design I')
        ->assertJsonPath('rows.1.modules_taught', 1)
        ->assertJsonPath('rows.1.unit_type', 'core')
        ->assertJsonPath('rows.1.theory.0', 58)
        ->assertJsonPath('rows.1.theory.1', 66)
        ->assertJsonPath('rows.1.theory.2', 72.5)
        ->assertJsonPath('rows.1.theory_average', 65.5)
        ->assertJsonPath('rows.1.practical.0', 80)
        ->assertJsonPath('rows.1.practical.2', null)
        ->assertJsonPath('rows.1.practical_average', 80)
        ->assertJsonPath('meta.total', 2);
});

test('my grades skips registered units without any recorded mark', function () {
    $user = User::factory()->create(['role' => Role::Student]);
    $student = StudentProfile::factory()->create(['user_id' => $user->id]);

    $graded = myGradesRegistration($student, ['code' => 'DFDT 1101']);
    myGradesMark($graded, 'theory', 1, 55);

    // Registered this session but not yet marked — nothing to show.
    myGradesRegistration($student, ['code' => 'DFDT 1102']);

    $this->withToken(myGradesAccessTokenFor($user))
        ->getJson('/api/me/grades')
        ->assertOk()
        ->assertJsonCount(1, 'rows')
        ->assertJsonPath('rows.0.unit.code', 'DFDT 1101');
});

test('my grades never leaks another student\'s marks', function () {
    $user = User::factory()->create(['role' => Role::Student]);
    StudentProfile::factory()->create(['user_id' => $user->id]);

    $theirs = myGradesRegistration(StudentProfile::factory()->create(), ['code' => 'DFDT 1101']);
    myGradesMark($theirs, 'theory', 1, 91);

    $this->withToken(myGradesAccessTokenFor($user))
        ->getJson('/api/me/grades')
        ->assertOk()
        ->assertJsonCount(0, 'rows')
        ->assertJsonCount(0, 'modules');
});

test('my grades search narrows rows by unit code or name', function () {
    $user = User::factory()->create(['role' => Role::Student]);
    $student = StudentProfile::factory()->create(['user_id' => $user->id]);

    $design = myGradesRegistration($student, ['code' => 'DFDT 1101', 'name' => 'Fashion Design I']);
    $pattern = myGradesRegistration($student, ['code' => 'DFDT 1201', 'name' => 'Pattern Making']);
    myGradesMark($design, 'theory', 1, 60);
    myGradesMark($pattern, 'theory', 1, 75);

    $this->withToken(myGradesAccessTokenFor($user))
        ->getJson('/api/me/grades?search=pattern')
        ->assertOk()
        ->assertJsonCount(1, 'rows')
        ->assertJsonPath('rows.0.unit.code', 'DFDT 1201');

    $this->withToken(myGradesAccessTokenFor($user))
        ->getJson('/api/me/grades?search='.urlencode('DFDT 11'))
        ->assertOk()
        ->assertJsonCount(1, 'rows')
        ->assertJsonPath('rows.0.unit.code', 'DFDT 1101');
});

test('my grades module filter narrows rows to units taught in that module', function () {
    $user = User::factory()->create(['role' => Role::Student]);
    $student = StudentProfile::factory()->create(['user_id' => $user->id]);

    $first = myGradesRegistration($student, ['code' => 'DFDT 1101', 'modules_taught' => 1]);
    $second = myGradesRegistration($student, ['code' => 'DFDT 1201', 'modules_taught' => 2]);
    myGradesMark($first, 'theory', 1, 60);
    myGradesMark($second, 'theory', 1, 75);

    $this->withToken(myGradesAccessTokenFor($user))
        ->getJson('/api/me/grades?module=2')
        ->assertOk()
        ->assertJsonCount(1, 'rows')
        ->assertJsonPath('rows.0.unit.code', 'DFDT 1201')
        ->assertJsonPath('rows.0.modules_taught', 2);
});

test('my grades lists every module the student has registered units for', function () {
    $user = User::factory()->create(['role' => Role::Student]);
    $student = StudentProfile::factory()->create(['user_id' => $user->id]);

    myGradesRegistration($student, ['code' => 'DFDT 1301', 'modules_taught' => 3]);
    myGradesRegistration($student, ['code' => 'DFDT 1101', 'modules_taught' => 1]);
    myGradesRegistration($student, ['code' => 'DFDT 1102', 'modules_taught' => null]);

    // The select covers every registered module, graded or not, sorted
    // ascending with untaught-module counts dropped.
    $this->withToken(myGradesAccessTokenFor($user))
        ->getJson('/api/me/grades')
        ->assertOk()
        ->assertJsonPath('modules', [1, 3]);
});

test('a student without a profile row gets an empty sheet', function () {
    $user = User::factory()->create(['role' => Role::Student]);

    $this->withToken(myGradesAccessTokenFor($user))
        ->getJson('/api/me/grades')
        ->assertOk()
        ->assertJsonCount(0, 'rows')
        ->assertJsonCount(0, 'modules');
});
