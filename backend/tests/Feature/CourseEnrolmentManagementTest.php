<?php

use App\Enums\Role;
use App\Models\AcademicSession;
use App\Models\CertificationLevel;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\Curriculum;
use App\Models\StudentProfile;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function enrolmentsAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('enrolment endpoints require an access token', function () {
    $this->getJson('/api/enrolments')->assertUnauthorized();

    // The write endpoints moved into the student registration flow.
    $this->postJson('/api/enrolments', [])->assertStatus(405);
    $this->putJson('/api/enrolments/1', [])->assertStatus(405);
});

test('the enrolment list refuses non-admin tokens', function () {
    $token = enrolmentsAccessTokenFor(User::factory()->create(['role' => Role::Student]));

    $this->withToken($token)->getJson('/api/enrolments')->assertForbidden();
});

test('enrolments are listed newest first with every display relation', function () {
    $level = CertificationLevel::factory()->create(['name' => 'Level 3']);
    $course = Course::factory()->create(['code' => 'IT-201', 'name' => 'Networking']);
    $curriculum = Curriculum::factory()->create(['name' => 'NCC Curriculum']);
    $session = AcademicSession::factory()->create(['name' => 'Term 1 2026']);
    $student = StudentProfile::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Otieno',
        'user_id' => User::factory()->state(['role' => Role::Student, 'login_id' => 'ADM001']),
    ]);

    $shared = [
        'student_id' => $student->id,
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
        'academic_session_id' => $session->id,
        'certification_level_id' => $level->id,
    ];

    $older = CourseEnrolment::factory()->create([
        ...$shared,
        'enrolment_date' => '2026-01-10',
        'status' => 'COMPLETED',
    ]);
    $newer = CourseEnrolment::factory()->create([
        ...$shared,
        'enrolment_date' => '2026-06-01',
        'status' => 'ENROLLED',
    ]);

    $this->withToken(enrolmentsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/enrolments')
        ->assertOk()
        ->assertJsonStructure([
            'enrolments' => [[
                'id', 'student_id', 'student_name', 'student_login_id',
                'course_id', 'course_code', 'course_name',
                'curriculum_id', 'curriculum_name',
                'academic_session_id', 'academic_session_name',
                'certification_level_id', 'certification_level_name',
                'enrolment_date', 'status', 'remarks', 'created_by', 'updated_by',
            ]],
            'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
        ])
        ->assertJsonPath('enrolments.0.id', $newer->id)
        ->assertJsonPath('enrolments.1.id', $older->id)
        ->assertJsonPath('enrolments.0.student_name', 'Amina Otieno')
        ->assertJsonPath('enrolments.0.student_login_id', 'ADM001')
        ->assertJsonPath('enrolments.0.course_code', 'IT-201')
        ->assertJsonPath('enrolments.0.curriculum_name', 'NCC Curriculum')
        ->assertJsonPath('enrolments.0.academic_session_name', 'Term 1 2026')
        ->assertJsonPath('enrolments.0.certification_level_name', 'Level 3')
        ->assertJsonPath('enrolments.0.status', 'ENROLLED');
});

test('the enrolment list supports search, filters, sorting and pagination', function () {
    $networking = Course::factory()->create(['code' => 'IT-201', 'name' => 'Networking']);
    $safety = Course::factory()->create(['code' => 'HS-101', 'name' => 'Workplace Safety']);
    $curriculum = Curriculum::factory()->create();
    $level = CertificationLevel::factory()->create();

    $studentA = StudentProfile::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Otieno',
    ]);
    $studentB = StudentProfile::factory()->create([
        'first_name' => 'Brian',
        'middle_name' => null,
        'last_name' => 'Mwangi',
    ]);

    $january = CourseEnrolment::factory()->create([
        'student_id' => $studentA->id,
        'course_id' => $networking->id,
        'curriculum_id' => $curriculum->id,
        'certification_level_id' => $level->id,
        'enrolment_date' => '2026-01-10',
        'status' => 'ENROLLED',
    ]);
    $june = CourseEnrolment::factory()->create([
        'student_id' => $studentB->id,
        'course_id' => $safety->id,
        'curriculum_id' => $curriculum->id,
        'certification_level_id' => $level->id,
        'enrolment_date' => '2026-06-01',
        'status' => 'COMPLETED',
    ]);

    $token = enrolmentsAccessTokenFor(User::factory()->create());

    $this->withToken($token)->getJson('/api/enrolments?search=IT-201')
        ->assertOk()
        ->assertJsonCount(1, 'enrolments')
        ->assertJsonPath('enrolments.0.id', $january->id);

    $this->withToken($token)->getJson('/api/enrolments?search=Amina')
        ->assertOk()
        ->assertJsonCount(1, 'enrolments')
        ->assertJsonPath('enrolments.0.id', $january->id);

    $this->withToken($token)->getJson('/api/enrolments?status=COMPLETED')
        ->assertOk()
        ->assertJsonCount(1, 'enrolments')
        ->assertJsonPath('enrolments.0.id', $june->id);

    $this->withToken($token)->getJson('/api/enrolments?course_id='.$networking->id)
        ->assertOk()
        ->assertJsonCount(1, 'enrolments')
        ->assertJsonPath('enrolments.0.id', $january->id);

    $sorted = $this->withToken($token)->getJson('/api/enrolments?sort=enrolment_date&order=asc')
        ->assertOk();

    expect(collect($sorted->json('enrolments'))->pluck('id')->all())
        ->toBe([$january->id, $june->id])
        ->and($sorted->json('meta.total'))->toBe(2);

    $this->withToken($token)->getJson('/api/enrolments?sort=remarks')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort']);

    $this->withToken($token)->getJson('/api/enrolments?per_page=1&page=2')
        ->assertOk()
        ->assertJsonCount(1, 'enrolments')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.from', 2)
        ->assertJsonPath('meta.to', 2);
});

test('an enrolment is soft deleted and disappears from the api', function () {
    $token = enrolmentsAccessTokenFor(User::factory()->create());
    $enrolment = CourseEnrolment::factory()->create();

    $this->withToken($token)->deleteJson('/api/enrolments/'.$enrolment->id)->assertNoContent();

    $this->assertSoftDeleted('course_enrolments', ['id' => $enrolment->id]);

    $this->withToken($token)->getJson('/api/enrolments/'.$enrolment->id)->assertNotFound();

    $this->withToken($token)->getJson('/api/enrolments')
        ->assertOk()
        ->assertJsonMissing(['id' => $enrolment->id]);
});

test('a missing enrolment returns 404', function () {
    $this->withToken(enrolmentsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/enrolments/999')
        ->assertNotFound();
});
