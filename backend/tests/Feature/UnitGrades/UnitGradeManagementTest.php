<?php

use App\Enums\Role;
use App\Enums\UnitExamType;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\CourseUnit;
use App\Models\StudentProfile;
use App\Models\Unit;
use App\Models\UnitGrade;
use App\Models\UnitRegistration;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

function unitGradesAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

/**
 * Everything the grading console needs: an active session, a student who
 * registered one course unit for it, and an admin token.
 *
 * @return array{token: string, session: AcademicSession, profile: StudentProfile, unit: Unit, courseUnit: CourseUnit}
 */
function unitGradesRegisteredStudent(): array
{
    $year = AcademicYear::factory()->create([
        'name' => '2026/2027',
        'is_active' => true,
        'start_date' => '2026-09-01',
        'end_date' => '2027-08-31',
    ]);

    $session = AcademicSession::factory()->create([
        'academic_year_id' => $year->id,
        'name' => 'Term 1, 2026/2027',
        'is_active' => true,
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-18',
    ]);

    $studentUser = User::factory()->create(['role' => Role::Student, 'login_id' => 'ADM-2026-099']);
    $profile = StudentProfile::factory()->create(['user_id' => $studentUser->id]);
    $unit = Unit::factory()->create(['code' => 'U100', 'name' => 'Basics']);
    $link = CourseUnit::factory()->create(['unit_id' => $unit->id]);

    UnitRegistration::factory()->create([
        'academic_session_id' => $session->id,
        'course_unit_id' => $link->id,
        'student_id' => $profile->id,
    ]);

    return [
        'token' => unitGradesAccessTokenFor(User::factory()->create(['role' => Role::Admin])),
        'session' => $session,
        'profile' => $profile,
        'unit' => $unit,
        'courseUnit' => $link,
    ];
}

/**
 * A two-student class with marks, for export tests: every format must
 * carry both students and the computed averages.
 *
 * @return array{token: string, session: AcademicSession, courseUnit: CourseUnit}
 */
function unitGradesMarksheetClass(): array
{
    $setup = unitGradesRegisteredStudent();

    unitGradesAward($setup, $setup['courseUnit'], UnitExamType::Theory, 1, 58);
    unitGradesAward($setup, $setup['courseUnit'], UnitExamType::Theory, 2, 66);
    unitGradesAward($setup, $setup['courseUnit'], UnitExamType::Theory, 3, 72.5);
    unitGradesAward($setup, $setup['courseUnit'], UnitExamType::Practical, 1, 80);
    unitGradesAward($setup, $setup['courseUnit'], UnitExamType::Practical, 2, 74);

    $mateUser = User::factory()->create(['role' => Role::Student, 'login_id' => 'ADM-2026-098']);
    $mate = StudentProfile::factory()->create(['user_id' => $mateUser->id]);
    UnitRegistration::factory()->create([
        'academic_session_id' => $setup['session']->id,
        'course_unit_id' => $setup['courseUnit']->id,
        'student_id' => $mate->id,
    ]);

    UnitGrade::factory()->create([
        'academic_session_id' => $setup['session']->id,
        'student_id' => $mate->id,
        'course_unit_id' => $setup['courseUnit']->id,
        'exam_type' => UnitExamType::Theory,
        'exam_no' => 1,
        'mark' => 90,
    ]);

    return [
        'token' => $setup['token'],
        'session' => $setup['session'],
        'courseUnit' => $setup['courseUnit'],
    ];
}

/**
 * Record one mark directly — the API can only save whole form states.
 * Marks hang off the registered course-unit link, not the bare unit.
 */
function unitGradesAward(array $setup, CourseUnit $courseUnit, UnitExamType $type, int $no, float $mark = 75.0): UnitGrade
{
    return UnitGrade::factory()->create([
        'academic_session_id' => $setup['session']->id,
        'student_id' => $setup['profile']->id,
        'course_unit_id' => $courseUnit->id,
        'exam_type' => $type,
        'exam_no' => $no,
        'mark' => $mark,
    ]);
}

test('the unit grade endpoints require an access token', function () {
    $this->getJson('/api/unit-grades/units')->assertUnauthorized();
    $this->getJson('/api/unit-grades/students?course_unit_id=1')->assertUnauthorized();
    $this->getJson('/api/unit-grades/sheet?course_unit_id=1')->assertUnauthorized();
    $this->getJson('/api/unit-grades/sheet/export?course_unit_id=1&format=csv')->assertUnauthorized();
    $this->getJson('/api/unit-grades?student_id=1')->assertUnauthorized();
    $this->putJson('/api/unit-grades', [])->assertUnauthorized();
});

test('the unit grade endpoints refuse students and finance', function (Role $role) {
    $token = unitGradesAccessTokenFor(User::factory()->create(['role' => $role]));

    $this->withToken($token)->getJson('/api/unit-grades/units')->assertForbidden();
    $this->withToken($token)->getJson('/api/unit-grades/students?course_unit_id=1')->assertForbidden();
    $this->withToken($token)->getJson('/api/unit-grades/sheet?course_unit_id=1')->assertForbidden();
    $this->withToken($token)->getJson('/api/unit-grades/sheet/export?course_unit_id=1&format=csv')->assertForbidden();
    $this->withToken($token)->getJson('/api/unit-grades?student_id=1')->assertForbidden();
    $this->withToken($token)->putJson('/api/unit-grades', [])->assertForbidden();
})->with([
    'student' => Role::Student,
    'finance' => Role::Finance,
]);

test('the console shows the selected registered unit with its grading progress', function () {
    $setup = unitGradesRegisteredStudent();

    foreach ([1, 2, 3] as $no) {
        unitGradesAward($setup, $setup['courseUnit'], UnitExamType::Theory, $no);
    }
    unitGradesAward($setup, $setup['courseUnit'], UnitExamType::Practical, 1);
    unitGradesAward($setup, $setup['courseUnit'], UnitExamType::Practical, 2);

    $this->withToken($setup['token'])
        ->getJson("/api/unit-grades?student_id={$setup['profile']->id}&course_unit_id={$setup['courseUnit']->id}&academic_session_id={$setup['session']->id}")
        ->assertOk()
        ->assertJsonPath('session.id', $setup['session']->id)
        ->assertJsonPath('student.id', $setup['profile']->id)
        ->assertJsonPath('student.admission_no', 'ADM-2026-099')
        ->assertJsonPath('unit.course_unit_id', $setup['courseUnit']->id)
        ->assertJsonPath('unit.code', 'U100')
        ->assertJsonPath('unit.theory_count', 3)
        ->assertJsonPath('unit.practical_count', 2)
        ->assertJsonPath('unit.fully_graded', false)
        ->assertJsonCount(5, 'grades')
        ->assertJsonPath('grades.0.course_unit_id', $setup['courseUnit']->id);
});

test('a unit with three theory and three practical marks is fully graded', function () {
    $setup = unitGradesRegisteredStudent();

    foreach (UnitExamType::cases() as $type) {
        foreach ([1, 2, 3] as $no) {
            unitGradesAward($setup, $setup['courseUnit'], $type, $no);
        }
    }

    $this->withToken($setup['token'])
        ->getJson("/api/unit-grades?student_id={$setup['profile']->id}&course_unit_id={$setup['courseUnit']->id}&academic_session_id={$setup['session']->id}")
        ->assertOk()
        ->assertJsonPath('unit.fully_graded', true)
        ->assertJsonCount(6, 'grades');
});

test('the console defaults to the active session', function () {
    $setup = unitGradesRegisteredStudent();

    $this->withToken($setup['token'])
        ->getJson("/api/unit-grades?student_id={$setup['profile']->id}&course_unit_id={$setup['courseUnit']->id}")
        ->assertOk()
        ->assertJsonPath('session.id', $setup['session']->id)
        ->assertJsonPath('student.id', $setup['profile']->id)
        ->assertJsonPath('unit.code', 'U100');
});

test('the console requires a course unit to grade', function () {
    $setup = unitGradesRegisteredStudent();

    $this->withToken($setup['token'])
        ->getJson("/api/unit-grades?student_id={$setup['profile']->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['course_unit_id']);
});

test('the unit picker lists only units registered for the session', function () {
    $setup = unitGradesRegisteredStudent();

    // One unit nobody registered, and one registered only under another
    // session: neither may appear for the picked session.
    CourseUnit::factory()->create();
    UnitRegistration::factory()->create([
        'academic_session_id' => AcademicSession::factory()->create([
            'academic_year_id' => $setup['session']->academic_year_id,
            'is_active' => false,
        ]),
        'course_unit_id' => CourseUnit::factory(),
        'student_id' => $setup['profile']->id,
    ]);

    $this->withToken($setup['token'])
        ->getJson("/api/unit-grades/units?academic_session_id={$setup['session']->id}")
        ->assertOk()
        ->assertJsonPath('session.id', $setup['session']->id)
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('units.0.course_unit_id', $setup['courseUnit']->id)
        ->assertJsonPath('units.0.code', 'U100')
        ->assertJsonPath('units.0.name', 'Basics')
        // Every row carries its course + curriculum so the marksheet's
        // pickers can cascade off this one list.
        ->assertJsonPath('units.0.course_id', $setup['courseUnit']->course_id)
        ->assertJsonPath('units.0.course', $setup['courseUnit']->course->name)
        ->assertJsonPath('units.0.curriculum_id', $setup['courseUnit']->curriculum_id)
        ->assertJsonPath('units.0.curriculum', $setup['courseUnit']->curriculum->name);

    // Without an explicit session the picker falls back to the active one.
    $this->withToken($setup['token'])
        ->getJson('/api/unit-grades/units')
        ->assertOk()
        ->assertJsonCount(1, 'units');
});

test('the unit picker narrows by code or name', function () {
    $setup = unitGradesRegisteredStudent();

    $second = Unit::factory()->create(['code' => 'U200', 'name' => 'Wiring']);
    $secondLink = CourseUnit::factory()->create(['unit_id' => $second->id]);
    UnitRegistration::factory()->create([
        'academic_session_id' => $setup['session']->id,
        'course_unit_id' => $secondLink->id,
        'student_id' => $setup['profile']->id,
    ]);

    $this->withToken($setup['token'])
        ->getJson("/api/unit-grades/units?academic_session_id={$setup['session']->id}&search=U200")
        ->assertOk()
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('units.0.code', 'U200');

    $this->withToken($setup['token'])
        ->getJson("/api/unit-grades/units?academic_session_id={$setup['session']->id}&search=Basics")
        ->assertOk()
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('units.0.code', 'U100');
});

test('the unit picker falls back to an empty list with no active session', function () {
    $setup = unitGradesRegisteredStudent();
    AcademicSession::query()->update(['is_active' => false]);

    $this->withToken($setup['token'])
        ->getJson('/api/unit-grades/units')
        ->assertOk()
        ->assertJsonPath('session', null)
        ->assertJsonCount(0, 'units');
});

test('the student picker lists only students who registered the unit', function () {
    $setup = unitGradesRegisteredStudent();

    // Registered for another unit in the same session — not gradeable
    // here — plus a student who registered nothing at all.
    UnitRegistration::factory()->create([
        'academic_session_id' => $setup['session']->id,
        'course_unit_id' => CourseUnit::factory(),
        'student_id' => StudentProfile::factory(),
    ]);
    StudentProfile::factory()->create();

    $this->withToken($setup['token'])
        ->getJson("/api/unit-grades/students?academic_session_id={$setup['session']->id}&course_unit_id={$setup['courseUnit']->id}")
        ->assertOk()
        ->assertJsonCount(1, 'students')
        ->assertJsonPath('students.0.id', $setup['profile']->id)
        ->assertJsonPath('students.0.name', User::composeName(
            $setup['profile']->first_name,
            $setup['profile']->middle_name,
            $setup['profile']->last_name,
        ))
        ->assertJsonPath('students.0.admission_no', 'ADM-2026-099');
});

test('the student picker narrows by name or admission number', function () {
    $setup = unitGradesRegisteredStudent();
    $baseUrl = "/api/unit-grades/students?academic_session_id={$setup['session']->id}&course_unit_id={$setup['courseUnit']->id}";

    $this->withToken($setup['token'])
        ->getJson($baseUrl.'&search='.urlencode($setup['profile']->last_name))
        ->assertOk()
        ->assertJsonCount(1, 'students');

    $this->withToken($setup['token'])
        ->getJson($baseUrl.'&search=ADM-2026-099')
        ->assertOk()
        ->assertJsonCount(1, 'students');

    $this->withToken($setup['token'])
        ->getJson($baseUrl.'&search=no-such-student')
        ->assertOk()
        ->assertJsonCount(0, 'students');
});

test('the marks sheet lists registered students with papers and averages', function () {
    $setup = unitGradesRegisteredStudent();

    unitGradesAward($setup, $setup['courseUnit'], UnitExamType::Theory, 1, 58);
    unitGradesAward($setup, $setup['courseUnit'], UnitExamType::Theory, 2, 66);
    unitGradesAward($setup, $setup['courseUnit'], UnitExamType::Theory, 3, 72.5);
    unitGradesAward($setup, $setup['courseUnit'], UnitExamType::Practical, 1, 80);
    unitGradesAward($setup, $setup['courseUnit'], UnitExamType::Practical, 2, 74);

    // A classmate registered alongside, with a single theory paper.
    $mate = StudentProfile::factory()->create();
    UnitRegistration::factory()->create([
        'academic_session_id' => $setup['session']->id,
        'course_unit_id' => $setup['courseUnit']->id,
        'student_id' => $mate->id,
    ]);
    UnitGrade::factory()->create([
        'academic_session_id' => $setup['session']->id,
        'student_id' => $mate->id,
        'course_unit_id' => $setup['courseUnit']->id,
        'exam_type' => UnitExamType::Theory,
        'exam_no' => 1,
        'mark' => 90,
    ]);

    $this->withToken($setup['token'])
        ->getJson("/api/unit-grades/sheet?academic_session_id={$setup['session']->id}&course_unit_id={$setup['courseUnit']->id}")
        ->assertOk()
        ->assertJsonPath('session.id', $setup['session']->id)
        ->assertJsonPath('unit.code', 'U100')
        ->assertJsonCount(2, 'rows')
        ->assertJsonPath('rows.0.admission_no', 'ADM-2026-099')
        ->assertJsonPath('rows.0.theory.0', 58)
        ->assertJsonPath('rows.0.theory.1', 66)
        ->assertJsonPath('rows.0.theory.2', 72.5)
        ->assertJsonPath('rows.0.theory_average', 65.5)
        ->assertJsonPath('rows.0.practical.0', 80)
        ->assertJsonPath('rows.0.practical.1', 74)
        ->assertJsonPath('rows.0.practical.2', null)
        ->assertJsonPath('rows.0.practical_average', 77)
        ->assertJsonPath('rows.1.name', User::composeName($mate->first_name, $mate->middle_name, $mate->last_name))
        ->assertJsonPath('rows.1.theory.0', 90)
        ->assertJsonPath('rows.1.theory_average', 90)
        ->assertJsonPath('rows.1.practical_average', null);

    // Without an explicit session the sheet falls back to the active one.
    $this->withToken($setup['token'])
        ->getJson("/api/unit-grades/sheet?course_unit_id={$setup['courseUnit']->id}")
        ->assertOk()
        ->assertJsonCount(2, 'rows');
});

test('the marks sheet only lists students registered for the session', function () {
    $setup = unitGradesRegisteredStudent();

    // Registered under another session only: the database would refuse
    // marks for them here anyway (the composite foreign key).
    $other = StudentProfile::factory()->create();
    UnitRegistration::factory()->create([
        'academic_session_id' => AcademicSession::factory()->create([
            'academic_year_id' => $setup['session']->academic_year_id,
            'is_active' => false,
        ]),
        'course_unit_id' => $setup['courseUnit']->id,
        'student_id' => $other->id,
    ]);

    $this->withToken($setup['token'])
        ->getJson("/api/unit-grades/sheet?academic_session_id={$setup['session']->id}&course_unit_id={$setup['courseUnit']->id}")
        ->assertOk()
        ->assertJsonCount(1, 'rows')
        ->assertJsonPath('rows.0.student_id', $setup['profile']->id);
});

test('the marks sheet requires a course unit', function () {
    $setup = unitGradesRegisteredStudent();

    $this->withToken($setup['token'])
        ->getJson('/api/unit-grades/sheet')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['course_unit_id']);
});

test('the csv export streams the whole class with a matching filename', function () {
    $setup = unitGradesMarksheetClass();
    $query = http_build_query([
        'academic_session_id' => $setup['session']->id,
        'course_unit_id' => $setup['courseUnit']->id,
        'format' => 'csv',
    ]);

    $response = $this->withToken($setup['token'])
        ->get("/api/unit-grades/sheet/export?{$query}")
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload('marksheet-u100-term-1-2026-2027.csv');

    $csv = $response->streamedContent();

    // Header row (fputcsv quotes labels containing spaces), then every
    // student — never a page of them.
    expect($csv)->toContain('"ADM NO."')
        ->toContain('"Theory Average"')
        ->toContain('ADM-2026-099')
        ->toContain('ADM-2026-098')
        ->toContain('65.5');
});

test('the xlsx export is a real spreadsheet file', function () {
    $setup = unitGradesMarksheetClass();
    $query = http_build_query([
        'academic_session_id' => $setup['session']->id,
        'course_unit_id' => $setup['courseUnit']->id,
        'format' => 'xlsx',
    ]);

    $response = $this->withToken($setup['token'])
        ->get("/api/unit-grades/sheet/export?{$query}")
        ->assertOk()
        ->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        )
        ->assertDownload('marksheet-u100-term-1-2026-2027.xlsx');

    $xlsx = $response->streamedContent();

    // A zip container with the workbook sheet inside.
    expect(Str::startsWith($xlsx, 'PK'))->toBeTrue();

    $zipPath = tempnam(sys_get_temp_dir(), 'marksheet-test-');
    file_put_contents($zipPath, $xlsx);

    $zip = new ZipArchive;
    expect($zip->open($zipPath))->toBeTrue();

    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    unlink($zipPath);

    expect($sheet)
        ->toContain('ADM-2026-099')
        ->toContain('Theory Average');
});

test('the pdf export produces a document with the class in it', function () {
    $setup = unitGradesMarksheetClass();
    $query = http_build_query([
        'academic_session_id' => $setup['session']->id,
        'course_unit_id' => $setup['courseUnit']->id,
        'format' => 'pdf',
    ]);

    $response = $this->withToken($setup['token'])
        ->get("/api/unit-grades/sheet/export?{$query}")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('marksheet-u100-term-1-2026-2027.pdf');

    $pdf = $response->streamedContent();

    expect(Str::startsWith($pdf, '%PDF-1.4'))->toBeTrue();
    expect($pdf)->toContain('%%EOF')
        ->toContain('ADM-2026-099')
        ->toContain('MARKSHEET');
});

test('the export refuses an unknown format', function () {
    $setup = unitGradesRegisteredStudent();

    $this->withToken($setup['token'])
        ->getJson("/api/unit-grades/sheet/export?course_unit_id={$setup['courseUnit']->id}&format=docx")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['format']);
});

test('the export refuses when there is no session to export', function () {
    $setup = unitGradesRegisteredStudent();
    AcademicSession::query()->update(['is_active' => false]);

    $this->withToken($setup['token'])
        ->getJson("/api/unit-grades/sheet/export?course_unit_id={$setup['courseUnit']->id}&format=csv")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['academic_session_id']);
});

test('a trainer can award marks with audit stamps', function () {
    $setup = unitGradesRegisteredStudent();
    $trainer = User::factory()->create(['role' => Role::Trainer]);

    $this->withToken(unitGradesAccessTokenFor($trainer))
        ->putJson('/api/unit-grades', [
            'academic_session_id' => $setup['session']->id,
            'student_id' => $setup['profile']->id,
            'course_unit_id' => $setup['courseUnit']->id,
            'marks' => [
                ['exam_type' => 'theory', 'exam_no' => 1, 'mark' => 62.5],
                ['exam_type' => 'practical', 'exam_no' => 3, 'mark' => 80],
            ],
        ])
        ->assertOk()
        ->assertJsonCount(2, 'grades');

    $this->assertDatabaseHas('unit_grades', [
        'academic_session_id' => $setup['session']->id,
        'course_unit_id' => $setup['courseUnit']->id,
        'student_id' => $setup['profile']->id,
        'exam_type' => 'theory',
        'exam_no' => 1,
        'mark' => 62.5,
        'created_by' => $trainer->id,
        'updated_by' => $trainer->id,
    ]);
});

test('saving replaces the unit previous marks', function () {
    $setup = unitGradesRegisteredStudent();
    unitGradesAward($setup, $setup['courseUnit'], UnitExamType::Theory, 1, 40);
    unitGradesAward($setup, $setup['courseUnit'], UnitExamType::Theory, 2, 50);
    unitGradesAward($setup, $setup['courseUnit'], UnitExamType::Practical, 1, 60);

    $this->withToken($setup['token'])
        ->putJson('/api/unit-grades', [
            'academic_session_id' => $setup['session']->id,
            'student_id' => $setup['profile']->id,
            'course_unit_id' => $setup['courseUnit']->id,
            'marks' => [
                ['exam_type' => 'theory', 'exam_no' => 2, 'mark' => 55],
            ],
        ])
        ->assertOk()
        ->assertJsonCount(1, 'grades');

    $this->assertDatabaseCount('unit_grades', 1);
    $this->assertDatabaseHas('unit_grades', [
        'exam_type' => 'theory',
        'exam_no' => 2,
        'mark' => 55,
    ]);
});

test('invalid grading slots are rejected', function (array $marks, string $errorKey) {
    $setup = unitGradesRegisteredStudent();

    $this->withToken($setup['token'])
        ->putJson('/api/unit-grades', [
            'academic_session_id' => $setup['session']->id,
            'student_id' => $setup['profile']->id,
            'course_unit_id' => $setup['courseUnit']->id,
            'marks' => $marks,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$errorKey]);

    $this->assertDatabaseCount('unit_grades', 0);
})->with([
    'unknown exam type' => [[['exam_type' => 'viva', 'exam_no' => 1, 'mark' => 50]], 'marks.0.exam_type'],
    'exam number out of range' => [[['exam_type' => 'theory', 'exam_no' => 4, 'mark' => 50]], 'marks.0.exam_no'],
    'mark above 100' => [[['exam_type' => 'theory', 'exam_no' => 1, 'mark' => 150]], 'marks.0.mark'],
    'duplicate slot' => [
        [
            ['exam_type' => 'theory', 'exam_no' => 1, 'mark' => 50],
            ['exam_type' => 'theory', 'exam_no' => 1, 'mark' => 60],
        ],
        'marks',
    ],
]);

test('marks cannot be awarded for a unit the student has not registered', function () {
    $setup = unitGradesRegisteredStudent();
    $foreignLink = CourseUnit::factory()->create();

    $this->withToken($setup['token'])
        ->putJson('/api/unit-grades', [
            'academic_session_id' => $setup['session']->id,
            'student_id' => $setup['profile']->id,
            'course_unit_id' => $foreignLink->id,
            'marks' => [
                ['exam_type' => 'theory', 'exam_no' => 1, 'mark' => 50],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['course_unit_id']);

    $this->assertDatabaseCount('unit_grades', 0);
});

test('the database refuses marks for a unit the student never registered', function () {
    $setup = unitGradesRegisteredStudent();
    $foreignLink = CourseUnit::factory()->create();

    $this->expectException(QueryException::class);

    unitGradesAward($setup, $foreignLink, UnitExamType::Theory, 1);
});

test('removing a unit registration removes the unit marks with it', function () {
    $setup = unitGradesRegisteredStudent();
    unitGradesAward($setup, $setup['courseUnit'], UnitExamType::Theory, 1);
    unitGradesAward($setup, $setup['courseUnit'], UnitExamType::Practical, 2);

    UnitRegistration::query()->delete();

    $this->assertDatabaseCount('unit_grades', 0);
});
