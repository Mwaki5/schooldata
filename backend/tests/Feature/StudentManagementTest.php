<?php

use App\Enums\Role;
use App\Enums\TermEventName;
use App\Models\AcademicSession;
use App\Models\CertificationLevel;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\CourseStage;
use App\Models\Curriculum;
use App\Models\SessionStageOffering;
use App\Models\StudentProfile;
use App\Models\TermEvent;
use App\Models\User;
use App\Services\Offerings\CourseStageGenerator;
use Tymon\JWTAuth\Facades\JWTAuth;

function studentsAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

/**
 * Give the course its generated ladder and open its first stage in the
 * session, so the registration path has a valid target.
 */
function openStudentRegistrationStage(Course $course, AcademicSession $session): CourseStage
{
    app(CourseStageGenerator::class)->generate($course);

    $stage = $course->stages()->where('module_no', 1)->firstOrFail();

    TermEvent::factory()->create([
        'academic_session_id' => $session->id,
        'name' => TermEventName::SessionRegistration,
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addWeek()->toDateString(),
        'is_active' => true,
    ]);

    SessionStageOffering::factory()
        ->for($session, 'academicSession')
        ->for($stage, 'courseStage')
        ->create();

    return $stage;
}

test('student endpoints require an access token', function () {
    $this->getJson('/api/students')->assertUnauthorized();
    $this->postJson('/api/students', [
        'admission_no' => 'ADM100',
        'first_name' => 'Amina',
        'last_name' => 'Otieno',
    ])->assertUnauthorized();
});

test('the student endpoints refuse non-admin tokens', function () {
    $token = studentsAccessTokenFor(User::factory()->create(['role' => Role::Student]));

    $this->withToken($token)->getJson('/api/students')->assertForbidden();
    $this->withToken($token)->postJson('/api/students', [])->assertForbidden();
});

test('students are listed by name with their account loaded', function () {
    StudentProfile::factory()->create([
        'first_name' => 'Zara',
        'middle_name' => null,
        'last_name' => 'Kamau',
        'user_id' => User::factory()->state(['role' => Role::Student, 'login_id' => 'ADM001']),
    ]);
    StudentProfile::factory()->create([
        'first_name' => 'Aaron',
        'middle_name' => null,
        'last_name' => 'Ochieng',
    ]);

    $this->withToken(studentsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/students')
        ->assertOk()
        ->assertJsonStructure([
            'students' => [[
                'id', 'user_id', 'login_id', 'email', 'name', 'first_name', 'last_name',
                'gender', 'status', 'phone', 'is_pwd', 'created_by', 'updated_by',
            ]],
            'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
        ])
        ->assertJsonPath('students.0.name', 'Aaron Ochieng')
        ->assertJsonPath('students.1.name', 'Zara Kamau')
        ->assertJsonPath('students.1.login_id', 'ADM001');
});

test('the student list supports search, filters, sorting and pagination', function () {
    $active = StudentProfile::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Wanjiku',
        'gender' => 'female',
        'status' => 'active',
        'user_id' => User::factory()->state(['role' => Role::Student, 'login_id' => 'ADM001']),
    ]);
    StudentProfile::factory()->create([
        'first_name' => 'Brian',
        'middle_name' => null,
        'last_name' => 'Mwangi',
        'gender' => 'male',
        'status' => 'inactive',
    ]);

    $token = studentsAccessTokenFor(User::factory()->create());

    $this->withToken($token)->getJson('/api/students?search=Amina')
        ->assertOk()
        ->assertJsonCount(1, 'students')
        ->assertJsonPath('students.0.id', $active->id);

    $this->withToken($token)->getJson('/api/students?search=ADM001')
        ->assertOk()
        ->assertJsonCount(1, 'students')
        ->assertJsonPath('students.0.id', $active->id);

    $this->withToken($token)->getJson('/api/students?status=inactive&gender=male')
        ->assertOk()
        ->assertJsonCount(1, 'students')
        ->assertJsonPath('students.0.first_name', 'Brian');

    $sorted = $this->withToken($token)->getJson('/api/students?sort=name&order=desc')
        ->assertOk();

    expect(collect($sorted->json('students'))->pluck('first_name')->all())
        ->toBe(['Brian', 'Amina'])
        ->and($sorted->json('meta.total'))->toBe(2);

    $this->withToken($token)->getJson('/api/students?sort=password')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort']);

    $this->withToken($token)->getJson('/api/students?per_page=1&page=2')
        ->assertOk()
        ->assertJsonCount(1, 'students')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.from', 2)
        ->assertJsonPath('meta.to', 2);
});

test('creating a student provisions the account and auto-creates the profile', function () {
    $creator = User::factory()->create();
    $course = Course::factory()->create(['code' => 'IT-201', 'name' => 'Networking']);
    $curriculum = Curriculum::factory()->create(['name' => 'NCC Curriculum']);
    $session = AcademicSession::factory()->create(['name' => 'Term 2 2026']);
    $level = CertificationLevel::factory()->create(['name' => 'Level 4']);
    $stage = openStudentRegistrationStage($course, $session);

    $response = $this->withToken(studentsAccessTokenFor($creator))
        ->postJson('/api/students', [
            'admission_no' => 'ADM100',
            'first_name' => 'Amina',
            'middle_name' => 'Wanjiku',
            'last_name' => 'Otieno',
            'gender' => 'female',
            'email' => 'amina@school.test',
            'phone' => '+254700000001',
            'date_of_birth' => '2004-05-17',
            'is_pwd' => false,
            'sponsored' => true,
            'sponsored_by' => 'County Bursary Fund',
            'index_no' => '6300123456',
            'exam_year' => '2025',
            'previous_school' => 'Central Primary School',
            'enrolment' => [
                'course_id' => $course->id,
                'curriculum_id' => $curriculum->id,
                'academic_session_id' => $session->id,
                'certification_level_id' => $level->id,
                'enrolment_date' => '2026-03-01',
                'remarks' => 'Term two intake',
            ],
        ]);

    $response->assertCreated()
        ->assertJsonPath('student.name', 'Amina Wanjiku Otieno')
        ->assertJsonPath('student.login_id', 'ADM100')
        ->assertJsonPath('student.email', 'amina@school.test')
        ->assertJsonPath('student.gender', 'female')
        ->assertJsonPath('student.status', 'active')
        ->assertJsonPath('student.created_by', $creator->id)
        ->assertJsonPath('student.updated_by', $creator->id)
        ->assertJsonPath('student.sponsored', true)
        ->assertJsonPath('student.sponsored_by', 'County Bursary Fund')
        ->assertJsonPath('student.index_no', '6300123456')
        ->assertJsonPath('student.exam_year', '2025')
        ->assertJsonPath('student.previous_school', 'Central Primary School')
        ->assertJsonPath('student.enrolment.course_id', $course->id)
        ->assertJsonPath('student.enrolment.course_stage_id', $stage->id)
        ->assertJsonPath('student.enrolment.course_stage_code', 'Y1S1')
        ->assertJsonPath('student.enrolment.curriculum_id', $curriculum->id)
        ->assertJsonPath('student.enrolment.academic_session_id', $session->id)
        ->assertJsonPath('student.enrolment.certification_level_id', $level->id)
        ->assertJsonPath('student.enrolment.enrolment_date', '2026-03-01')
        ->assertJsonPath('student.enrolment.status', 'ENROLLED')
        ->assertJsonPath('student.enrolment.remarks', 'Term two intake')
        ->assertJsonPath('student.enrolment.course_label', 'IT-201 — Networking')
        ->assertJsonPath('student.enrolment.curriculum_label', 'NCC Curriculum')
        ->assertJsonPath('student.enrolment.academic_session_label', 'Term 2 2026')
        ->assertJsonPath('student.enrolment.certification_level_label', 'Level 4');

    expect($response->json('temporary_password'))->toBeString()->toHaveLength(16);

    $user = User::where('login_id', 'ADM100')->firstOrFail();

    expect($user->role)->toBe(Role::Student);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => 'amina@school.test',
        'name' => 'Amina Wanjiku Otieno',
        'role' => 'student',
        'must_reset_password' => true,
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('student_profiles', [
        'user_id' => $user->id,
        'first_name' => 'Amina',
        'middle_name' => 'Wanjiku',
        'last_name' => 'Otieno',
        'phone' => '+254700000001',
        'status' => 'active',
        'sponsored' => true,
        'sponsored_by' => 'County Bursary Fund',
        'index_no' => '6300123456',
        'exam_year' => '2025',
        'previous_school' => 'Central Primary School',
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);

    // The same transaction filed the enrolment under the new profile.
    $this->assertDatabaseHas('course_enrolments', [
        'student_id' => $response->json('student.id'),
        'course_id' => $course->id,
        'course_stage_id' => $stage->id,
        'curriculum_id' => $curriculum->id,
        'academic_session_id' => $session->id,
        'certification_level_id' => $level->id,
        'status' => 'ENROLLED',
        'remarks' => 'Term two intake',
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);

    expect(CourseEnrolment::find($response->json('student.enrolment.id'))->enrolment_date->toDateString())
        ->toBe('2026-03-01');
});

test('a blank email receives a non-routable placeholder address', function () {
    $this->withToken(studentsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/students', [
            'admission_no' => 'ADM101',
            'first_name' => 'Brian',
            'last_name' => 'Mwangi',
            'enrolment' => [
                'course_id' => Course::factory()->create()->id,
                'curriculum_id' => Curriculum::factory()->create()->id,
                'certification_level_id' => CertificationLevel::factory()->create()->id,
                'enrolment_date' => '2026-03-01',
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('student.email', 'adm101@student.invalid');

    $this->assertDatabaseHas('users', [
        'login_id' => 'ADM101',
        'email' => 'adm101@student.invalid',
    ]);
});

test('creating a student requires the core fields', function () {
    $this->withToken(studentsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/students', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'admission_no', 'first_name', 'last_name',
            'enrolment.course_id', 'enrolment.curriculum_id',
            'enrolment.certification_level_id', 'enrolment.enrolment_date',
        ]);
});

test('a live admission number cannot be reused', function () {
    User::factory()->create(['login_id' => 'ADM100']);

    $this->withToken(studentsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/students', [
            'admission_no' => 'ADM100',
            'first_name' => 'Amina',
            'last_name' => 'Otieno',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['admission_no']);
});

test('an unknown gender is rejected', function () {
    $this->withToken(studentsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/students', [
            'admission_no' => 'ADM102',
            'first_name' => 'Amina',
            'last_name' => 'Otieno',
            'gender' => 'alien',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['gender']);
});

test('an unknown status is rejected', function () {
    $this->withToken(studentsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/students', [
            'admission_no' => 'ADM103',
            'first_name' => 'Amina',
            'last_name' => 'Otieno',
            'status' => 'archived',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('a deleted student frees their admission number for re-admission', function () {
    $token = studentsAccessTokenFor(User::factory()->create());

    $student = StudentProfile::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Otieno',
        'user_id' => User::factory()->state(['role' => Role::Student, 'login_id' => 'ADM200']),
    ]);
    $accountId = $student->user_id;

    $this->withToken($token)->deleteJson('/api/students/'.$student->id)->assertNoContent();

    $response = $this->withToken($token)
        ->postJson('/api/students', [
            'admission_no' => 'ADM200',
            'first_name' => 'Amina',
            'middle_name' => null,
            'last_name' => 'Otieno',
            'enrolment' => [
                'course_id' => Course::factory()->create()->id,
                'curriculum_id' => Curriculum::factory()->create()->id,
                'certification_level_id' => CertificationLevel::factory()->create()->id,
                'enrolment_date' => '2026-03-01',
            ],
        ]);

    $response->assertCreated()
        ->assertJsonPath('student.user_id', $accountId);

    expect(User::withTrashed()->where('login_id', 'ADM200')->count())->toBe(1);

    // The restored account signs in again and the old row released its link.
    expect(User::find($accountId))->not->toBeNull();
    $this->assertDatabaseHas('student_profiles', ['id' => $student->id, 'user_id' => null]);
});

test('a student update moves the display name and status to the account', function () {
    $updater = User::factory()->create();
    $student = StudentProfile::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => 'Grace',
        'last_name' => 'Wanjiku',
        'user_id' => User::factory()->state(['role' => Role::Student, 'login_id' => 'ADM300']),
        'created_by' => $updater->id,
    ]);

    $this->withToken(studentsAccessTokenFor($updater))
        ->putJson('/api/students/'.$student->id, [
            'admission_no' => 'ADM300',
            'first_name' => 'Amina',
            'middle_name' => 'Grace',
            'last_name' => 'Otieno',
            'status' => 'inactive',
            'enrolment' => [
                'course_id' => Course::factory()->create()->id,
                'curriculum_id' => Curriculum::factory()->create()->id,
                'certification_level_id' => CertificationLevel::factory()->create()->id,
                'enrolment_date' => '2026-03-01',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('student.first_name', 'Amina')
        ->assertJsonPath('student.last_name', 'Otieno')
        ->assertJsonPath('student.name', 'Amina Grace Otieno')
        ->assertJsonPath('student.status', 'inactive')
        ->assertJsonPath('student.created_by', $updater->id)
        ->assertJsonPath('student.updated_by', $updater->id);

    $this->assertDatabaseHas('student_profiles', [
        'id' => $student->id,
        'last_name' => 'Otieno',
        'status' => 'inactive',
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $student->user_id,
        'name' => 'Amina Grace Otieno',
        'login_id' => 'ADM300',
        'status' => 'inactive',
    ]);
});

test('updating a student validates the admission number excluding itself', function () {
    $student = StudentProfile::factory()->create([
        'user_id' => User::factory()->state(['role' => Role::Student, 'login_id' => 'ADM300']),
    ]);
    User::factory()->create(['login_id' => 'ADM301']);

    $this->withToken(studentsAccessTokenFor(User::factory()->create()))
        ->putJson('/api/students/'.$student->id, [
            'admission_no' => 'ADM300',
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'enrolment' => [
                'course_id' => Course::factory()->create()->id,
                'curriculum_id' => Curriculum::factory()->create()->id,
                'certification_level_id' => CertificationLevel::factory()->create()->id,
                'enrolment_date' => '2026-03-01',
            ],
        ])
        ->assertOk();

    $this->withToken(studentsAccessTokenFor(User::factory()->create()))
        ->putJson('/api/students/'.$student->id, [
            'admission_no' => 'ADM301',
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'enrolment' => [
                'course_id' => Course::factory()->create()->id,
                'curriculum_id' => Curriculum::factory()->create()->id,
                'certification_level_id' => CertificationLevel::factory()->create()->id,
                'enrolment_date' => '2026-03-01',
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['admission_no']);
});

test('a student is deleted with their account and enrolments', function () {
    $token = studentsAccessTokenFor(User::factory()->create());

    $student = StudentProfile::factory()->create([
        'user_id' => User::factory()->state(['role' => Role::Student, 'login_id' => 'ADM400']),
    ]);
    $enrolment = CourseEnrolment::factory()->create(['student_id' => $student->id]);
    $accountId = $student->user_id;

    $this->withToken($token)->deleteJson('/api/students/'.$student->id)->assertNoContent();

    $this->assertSoftDeleted('student_profiles', ['id' => $student->id]);
    $this->assertSoftDeleted('users', ['id' => $accountId]);
    $this->assertSoftDeleted('course_enrolments', ['id' => $enrolment->id]);

    $this->withToken($token)->getJson('/api/students/'.$student->id)->assertNotFound();

    $this->withToken($token)->getJson('/api/students')
        ->assertOk()
        ->assertJsonMissing(['id' => $student->id]);
});

test('a missing student returns 404', function () {
    $this->withToken(studentsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/students/999')
        ->assertNotFound();
});

test('a malformed exam year is rejected', function () {
    $this->withToken(studentsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/students', [
            'admission_no' => 'ADM104',
            'first_name' => 'Amina',
            'last_name' => 'Otieno',
            'exam_year' => 'twenty',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['exam_year']);
});

test('a student can be registered without an academic session', function () {
    $response = $this->withToken(studentsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/students', [
            'admission_no' => 'ADM105',
            'first_name' => 'Amina',
            'last_name' => 'Otieno',
            'enrolment' => [
                'course_id' => Course::factory()->create()->id,
                'curriculum_id' => Curriculum::factory()->create()->id,
                'certification_level_id' => CertificationLevel::factory()->create()->id,
                'enrolment_date' => '2026-03-01',
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('student.enrolment.academic_session_id', null);

    $this->assertDatabaseHas('course_enrolments', [
        'student_id' => $response->json('student.id'),
        'academic_session_id' => null,
    ]);
});

test('the enrolment segment must reference live records', function () {
    $deletedCourse = Course::factory()->create();
    $deletedCourse->delete();

    $this->withToken(studentsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/students', [
            'admission_no' => 'ADM106',
            'first_name' => 'Amina',
            'last_name' => 'Otieno',
            'enrolment' => [
                'course_id' => $deletedCourse->id,
                'curriculum_id' => Curriculum::factory()->create()->id,
                'certification_level_id' => CertificationLevel::factory()->create()->id,
                'enrolment_date' => '2026-03-01',
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['enrolment.course_id']);
});

test('an unknown enrolment status is rejected', function () {
    $this->withToken(studentsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/students', [
            'admission_no' => 'ADM107',
            'first_name' => 'Amina',
            'last_name' => 'Otieno',
            'enrolment' => [
                'course_id' => Course::factory()->create()->id,
                'curriculum_id' => Curriculum::factory()->create()->id,
                'certification_level_id' => CertificationLevel::factory()->create()->id,
                'enrolment_date' => '2026-03-01',
                'status' => 'ON_HOLD',
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['enrolment.status']);
});

test('a student update also updates their enrolment', function () {
    $creator = User::factory()->create();
    $updater = User::factory()->create();

    $student = StudentProfile::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Otieno',
        'user_id' => User::factory()->state(['role' => Role::Student, 'login_id' => 'ADM302']),
    ]);
    $enrolment = CourseEnrolment::factory()->create([
        'student_id' => $student->id,
        'status' => 'ENROLLED',
        'remarks' => 'Term two intake',
        'created_by' => $creator->id,
    ]);

    $this->withToken(studentsAccessTokenFor($updater))
        ->putJson('/api/students/'.$student->id, [
            'admission_no' => 'ADM302',
            'first_name' => 'Amina',
            'last_name' => 'Otieno',
            'enrolment' => [
                'course_id' => $enrolment->course_id,
                'curriculum_id' => $enrolment->curriculum_id,
                'academic_session_id' => $enrolment->academic_session_id,
                'certification_level_id' => $enrolment->certification_level_id,
                'enrolment_date' => $enrolment->enrolment_date->toDateString(),
                'status' => 'COMPLETED',
                'remarks' => 'Finished all units',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('student.enrolment.id', $enrolment->id)
        ->assertJsonPath('student.enrolment.status', 'COMPLETED')
        ->assertJsonPath('student.enrolment.remarks', 'Finished all units');

    $this->assertDatabaseHas('course_enrolments', [
        'id' => $enrolment->id,
        'status' => 'COMPLETED',
        'remarks' => 'Finished all units',
        'created_by' => $creator->id,
        'updated_by' => $updater->id,
    ]);

    // The update moved onto the existing row instead of filing a second one.
    expect(CourseEnrolment::count())->toBe(1);
});

test('a registration for a stage with no offering is rejected', function () {
    $creator = User::factory()->create();
    $course = Course::factory()->create(['total_modules' => 3]);
    $curriculum = Curriculum::factory()->create();
    $session = AcademicSession::factory()->create();
    $level = CertificationLevel::factory()->create();

    app(CourseStageGenerator::class)->generate($course);

    $this->withToken(studentsAccessTokenFor($creator))
        ->postJson('/api/students', [
            'admission_no' => 'ADM500',
            'first_name' => 'Amina',
            'last_name' => 'Otieno',
            'enrolment' => [
                'course_id' => $course->id,
                'curriculum_id' => $curriculum->id,
                'academic_session_id' => $session->id,
                'certification_level_id' => $level->id,
                'enrolment_date' => '2026-09-01',
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'enrolment.course_stage_id' => 'Y1S1 is not open for this session.',
        ]);

    $this->assertDatabaseMissing('users', ['login_id' => 'ADM500']);
    $this->assertDatabaseMissing('student_profiles', ['user_id' => null, 'first_name' => 'Amina']);
    expect(CourseEnrolment::count())->toBe(0);
});

test('a registration is rejected when the session has no registration event', function () {
    $creator = User::factory()->create();
    $course = Course::factory()->create(['total_modules' => 3]);
    $curriculum = Curriculum::factory()->create();
    $session = AcademicSession::factory()->create();
    $level = CertificationLevel::factory()->create();

    app(CourseStageGenerator::class)->generate($course);

    $stage = $course->stages()->where('module_no', 1)->firstOrFail();

    SessionStageOffering::factory()
        ->for($session, 'academicSession')
        ->for($stage, 'courseStage')
        ->create();

    $this->withToken(studentsAccessTokenFor($creator))
        ->postJson('/api/students', [
            'admission_no' => 'ADM502',
            'first_name' => 'Amina',
            'last_name' => 'Otieno',
            'enrolment' => [
                'course_id' => $course->id,
                'curriculum_id' => $curriculum->id,
                'academic_session_id' => $session->id,
                'certification_level_id' => $level->id,
                'enrolment_date' => '2026-09-01',
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'enrolment.course_stage_id' => 'Registration dates are not configured for this session.',
        ]);

    $this->assertDatabaseMissing('users', ['login_id' => 'ADM502']);
    expect(CourseEnrolment::count())->toBe(0);
});

test('a registration is rejected with a clear message when the event window has ended', function () {
    $creator = User::factory()->create();
    $course = Course::factory()->create(['total_modules' => 3]);
    $curriculum = Curriculum::factory()->create();
    $session = AcademicSession::factory()->create();
    $level = CertificationLevel::factory()->create();

    openStudentRegistrationStage($course, $session);

    $this->travelTo(now()->addDays(9));

    $this->withToken(studentsAccessTokenFor($creator))
        ->postJson('/api/students', [
            'admission_no' => 'ADM503',
            'first_name' => 'Amina',
            'last_name' => 'Otieno',
            'enrolment' => [
                'course_id' => $course->id,
                'curriculum_id' => $curriculum->id,
                'academic_session_id' => $session->id,
                'certification_level_id' => $level->id,
                'enrolment_date' => '2026-09-01',
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'enrolment.course_stage_id' => 'Registration for this session has closed.',
        ]);

    $this->assertDatabaseMissing('users', ['login_id' => 'ADM503']);
    expect(CourseEnrolment::count())->toBe(0);

    $this->travelBack();
});

test('moving a student to a later session is rejected when the next stage is closed', function () {
    $updater = User::factory()->create();
    $student = StudentProfile::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Otieno',
        'user_id' => User::factory()->state(['role' => Role::Student, 'login_id' => 'ADM501']),
    ]);

    $course = Course::factory()->create(['total_modules' => 3]);
    $stageOne = $course->stages()->create([
        'module_no' => 1,
        'year_no' => 1,
        'session_no' => 1,
        'code' => 'Y1S1',
    ]);
    $course->stages()->create([
        'module_no' => 2,
        'year_no' => 1,
        'session_no' => 2,
        'code' => 'Y1S2',
    ]);

    $firstSession = AcademicSession::factory()->create([
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-18',
    ]);
    $secondSession = AcademicSession::factory()->create([
        'start_date' => '2027-01-04',
        'end_date' => '2027-04-09',
    ]);

    $enrolment = CourseEnrolment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'course_stage_id' => $stageOne->id,
        'academic_session_id' => $firstSession->id,
        'status' => 'ENROLLED',
    ]);

    $this->withToken(studentsAccessTokenFor($updater))
        ->putJson('/api/students/'.$student->id, [
            'admission_no' => 'ADM501',
            'first_name' => 'Amina',
            'last_name' => 'Otieno',
            'enrolment' => [
                'course_id' => $course->id,
                'curriculum_id' => $enrolment->curriculum_id,
                'academic_session_id' => $secondSession->id,
                'certification_level_id' => $enrolment->certification_level_id,
                'enrolment_date' => '2027-01-04',
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'enrolment.course_stage_id' => 'Y1S2 is not open for this session.',
        ]);

    $this->assertDatabaseHas('course_enrolments', [
        'id' => $enrolment->id,
        'academic_session_id' => $firstSession->id,
        'course_stage_id' => $stageOne->id,
    ]);
});
