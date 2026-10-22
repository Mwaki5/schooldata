<?php

use App\Enums\FeeSpan;
use App\Enums\Role;
use App\Models\Course;
use App\Models\CourseFeeAssignment;
use App\Models\Curriculum;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function courseFeeAssignmentsAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('course fee assignment endpoints require an access token', function () {
    $this->getJson('/api/course-fee-assignments')->assertUnauthorized();
    $this->postJson('/api/course-fee-assignments', [])->assertUnauthorized();
});

test('only admins manage course fee assignments', function () {
    $token = courseFeeAssignmentsAccessTokenFor(User::factory()->create(['role' => Role::Trainer]));

    $this->withToken($token)->getJson('/api/course-fee-assignments')->assertForbidden();
    $this->withToken($token)->postJson('/api/course-fee-assignments', [])->assertForbidden();
});

test('assignments are listed with their course, curriculum and fee structure', function () {
    $courseB = Course::factory()->create(['code' => 'BBB200', 'name' => 'Plumbing']);
    $courseA = Course::factory()->create(['code' => 'AAA100', 'name' => 'ICT']);
    $first = Curriculum::factory()->create(['name' => 'Foundation Curriculum']);
    $second = Curriculum::factory()->create(['name' => 'Advanced Curriculum']);
    $structure = FeeStructure::factory()->create(['name' => 'Year 1 Plan']);

    // Created out of order: the list is ordered by course, then
    // curriculum, then year, so course B (the earlier course) leads.
    CourseFeeAssignment::factory()->create([
        'course_id' => $courseA->id,
        'curriculum_id' => $second->id,
        'fee_structure_id' => $structure->id,
        'span' => FeeSpan::Year,
        'year_no' => 2,
        'session_no' => null,
    ]);
    CourseFeeAssignment::factory()->create([
        'course_id' => $courseA->id,
        'curriculum_id' => $first->id,
        'fee_structure_id' => $structure->id,
        'span' => FeeSpan::Session,
        'year_no' => 1,
        'session_no' => 2,
    ]);
    CourseFeeAssignment::factory()->create([
        'course_id' => $courseB->id,
        'curriculum_id' => $first->id,
        'fee_structure_id' => $structure->id,
        'span' => FeeSpan::Session,
        'year_no' => 1,
        'session_no' => 1,
    ]);

    $this->withToken(courseFeeAssignmentsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/course-fee-assignments')
        ->assertOk()
        ->assertJsonStructure([
            'course_fee_assignments' => [[
                'id', 'course_id', 'curriculum_id', 'fee_structure_id', 'span', 'year_no', 'session_no',
                'course' => ['id', 'code', 'name'],
                'curriculum' => ['id', 'name'],
                'fee_structure' => ['id', 'name'],
            ]],
            'meta',
        ])
        ->assertJsonCount(3, 'course_fee_assignments')
        ->assertJsonPath('course_fee_assignments.0.course.code', 'BBB200')
        ->assertJsonPath('course_fee_assignments.0.span', 'session')
        ->assertJsonPath('course_fee_assignments.0.session_no', 1)
        ->assertJsonPath('course_fee_assignments.1.curriculum.name', 'Foundation Curriculum')
        ->assertJsonPath('course_fee_assignments.1.session_no', 2)
        ->assertJsonPath('course_fee_assignments.2.curriculum.name', 'Advanced Curriculum')
        ->assertJsonPath('course_fee_assignments.2.span', 'year')
        ->assertJsonPath('course_fee_assignments.2.session_no', null)
        ->assertJsonPath('course_fee_assignments.2.fee_structure.name', 'Year 1 Plan');
});

test('the search narrows by course, curriculum or fee structure', function () {
    $ict = Course::factory()->create(['code' => 'ICT101', 'name' => 'Information Technology']);
    $cosmetology = Course::factory()->create(['code' => 'COS101', 'name' => 'Cosmetology']);
    $curriculum = Curriculum::factory()->create(['name' => 'KNEC 2025 Curriculum']);
    $otherCurriculum = Curriculum::factory()->create(['name' => 'NITA 2024 Curriculum']);
    $structure = FeeStructure::factory()->create(['name' => 'ICT Plan']);

    CourseFeeAssignment::factory()->create([
        'course_id' => $ict->id,
        'curriculum_id' => $curriculum->id,
        'fee_structure_id' => $structure->id,
    ]);
    CourseFeeAssignment::factory()->create([
        'course_id' => $cosmetology->id,
        'curriculum_id' => $otherCurriculum->id,
        'fee_structure_id' => FeeStructure::factory()->create(['name' => 'Salon Plan']),
    ]);

    $token = courseFeeAssignmentsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->getJson('/api/course-fee-assignments?search=ICT101')
        ->assertOk()
        ->assertJsonCount(1, 'course_fee_assignments')
        ->assertJsonPath('course_fee_assignments.0.course.code', 'ICT101');

    $this->withToken($token)
        ->getJson('/api/course-fee-assignments?search='.urlencode('NITA 2024'))
        ->assertOk()
        ->assertJsonCount(1, 'course_fee_assignments')
        ->assertJsonPath('course_fee_assignments.0.course.code', 'COS101');

    $this->withToken($token)
        ->getJson('/api/course-fee-assignments?search='.urlencode('Salon Plan'))
        ->assertOk()
        ->assertJsonCount(1, 'course_fee_assignments')
        ->assertJsonPath('course_fee_assignments.0.fee_structure.name', 'Salon Plan');
});

test('an assignment can be created with audit stamps', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $structure = FeeStructure::factory()->create();
    $creator = User::factory()->create();

    $this->withToken(courseFeeAssignmentsAccessTokenFor($creator))
        ->postJson('/api/course-fee-assignments', [
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
            'fee_structure_id' => $structure->id,
            'span' => 'session',
            'year_no' => 1,
            'session_no' => 2,
        ])
        ->assertCreated()
        ->assertJsonPath('course_fee_assignment.course_id', $course->id)
        ->assertJsonPath('course_fee_assignment.curriculum_id', $curriculum->id)
        ->assertJsonPath('course_fee_assignment.fee_structure_id', $structure->id)
        ->assertJsonPath('course_fee_assignment.span', 'session')
        ->assertJsonPath('course_fee_assignment.year_no', 1)
        ->assertJsonPath('course_fee_assignment.session_no', 2)
        ->assertJsonPath('course_fee_assignment.course.id', $course->id)
        ->assertJsonPath('course_fee_assignment.curriculum.id', $curriculum->id)
        ->assertJsonPath('course_fee_assignment.fee_structure.id', $structure->id)
        ->assertJsonPath('course_fee_assignment.created_by', $creator->id)
        ->assertJsonPath('course_fee_assignment.updated_by', $creator->id);

    $this->assertDatabaseHas('course_fee_assignments', [
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
        'fee_structure_id' => $structure->id,
        'span' => 'session',
        'year_no' => 1,
        'session_no' => 2,
        'created_by' => $creator->id,
    ]);
});

test('creating an assignment requires a course, curriculum, fee structure, span and year', function () {
    $this->withToken(courseFeeAssignmentsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/course-fee-assignments', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['course_id', 'curriculum_id', 'fee_structure_id', 'span', 'year_no']);
});

test('a per-session assignment requires its session number', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();

    $this->withToken(courseFeeAssignmentsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/course-fee-assignments', [
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
            'fee_structure_id' => FeeStructure::factory()->create()->id,
            'span' => 'session',
            'year_no' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['session_no']);
});

test('a per-year assignment drops any incoming session number', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $structure = FeeStructure::factory()->create();

    $this->withToken(courseFeeAssignmentsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/course-fee-assignments', [
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
            'fee_structure_id' => $structure->id,
            'span' => 'year',
            'year_no' => 2,
            'session_no' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('course_fee_assignment.year_no', 2)
        ->assertJsonPath('course_fee_assignment.session_no', null);

    $this->assertDatabaseHas('course_fee_assignments', [
        'course_id' => $course->id,
        'year_no' => 2,
        'session_no' => null,
    ]);
});

test('the span must be per session or per year', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();

    $this->withToken(courseFeeAssignmentsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/course-fee-assignments', [
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
            'fee_structure_id' => FeeStructure::factory()->create()->id,
            'span' => 'monthly',
            'year_no' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['span']);
});

test('the referenced records must exist and be live', function () {
    $token = courseFeeAssignmentsAccessTokenFor(User::factory()->create());
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $structure = FeeStructure::factory()->create();
    $payload = [
        'span' => 'session',
        'year_no' => 1,
        'session_no' => 1,
    ];

    $this->withToken($token)
        ->postJson('/api/course-fee-assignments', [
            'course_id' => 9999,
            'curriculum_id' => $curriculum->id,
            'fee_structure_id' => $structure->id,
            ...$payload,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['course_id']);

    $this->withToken($token)
        ->postJson('/api/course-fee-assignments', [
            'course_id' => $course->id,
            'curriculum_id' => 9999,
            'fee_structure_id' => $structure->id,
            ...$payload,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['curriculum_id']);

    $this->withToken($token)
        ->postJson('/api/course-fee-assignments', [
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
            'fee_structure_id' => 9999,
            ...$payload,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fee_structure_id']);

    // A soft-deleted fee structure is no longer assignable.
    $structure->delete();

    $this->withToken($token)
        ->postJson('/api/course-fee-assignments', [
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
            'fee_structure_id' => $structure->id,
            ...$payload,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fee_structure_id']);
});

test('a slot charges under only one live assignment', function () {
    $course = Course::factory()->create();
    $otherCourse = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $structure = FeeStructure::factory()->create();
    $token = courseFeeAssignmentsAccessTokenFor(User::factory()->create());

    $payload = [
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
        'fee_structure_id' => $structure->id,
        'span' => 'session',
        'year_no' => 1,
        'session_no' => 1,
    ];

    $this->withToken($token)
        ->postJson('/api/course-fee-assignments', $payload)
        ->assertCreated();

    // The same slot again would make the enrolment lookup ambiguous.
    $this->withToken($token)
        ->postJson('/api/course-fee-assignments', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['year_no']);

    // Another year, and another session of the same year, are new slots.
    $this->withToken($token)
        ->postJson('/api/course-fee-assignments', [...$payload, 'year_no' => 2])
        ->assertCreated();

    $this->withToken($token)
        ->postJson('/api/course-fee-assignments', [...$payload, 'session_no' => 2])
        ->assertCreated();

    // A per-year fee of another structure covers its whole year and
    // coexists with the session-specific fees of that year.
    $this->withToken($token)
        ->postJson('/api/course-fee-assignments', [
            ...$payload,
            'fee_structure_id' => FeeStructure::factory()->create()->id,
            'span' => 'year',
            'session_no' => null,
        ])
        ->assertCreated();

    // The same slot in another course is a different charge.
    $this->withToken($token)
        ->postJson('/api/course-fee-assignments', [...$payload, 'course_id' => $otherCourse->id])
        ->assertCreated();
});

test('a structure may not charge the same year both per year and per session', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $structure = FeeStructure::factory()->create();
    $otherStructure = FeeStructure::factory()->create();
    $token = courseFeeAssignmentsAccessTokenFor(User::factory()->create());

    $sessionPayload = [
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
        'fee_structure_id' => $structure->id,
        'span' => 'session',
        'year_no' => 1,
        'session_no' => 1,
    ];

    $this->withToken($token)
        ->postJson('/api/course-fee-assignments', [
            ...$sessionPayload,
            'span' => 'year',
            'session_no' => null,
        ])
        ->assertCreated();

    // The same structure charging per session on top of its year fee
    // would charge the student twice in that session.
    $this->withToken($token)
        ->postJson('/api/course-fee-assignments', $sessionPayload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fee_structure_id']);

    // Another structure may still charge the same year per session.
    $this->withToken($token)
        ->postJson('/api/course-fee-assignments', [
            ...$sessionPayload,
            'fee_structure_id' => $otherStructure->id,
        ])
        ->assertCreated();

    // Repeating the same structure across the year's sessions is the
    // point of per-session fees — here for the session-charged one.
    $this->withToken($token)
        ->postJson('/api/course-fee-assignments', [
            ...$sessionPayload,
            'fee_structure_id' => $otherStructure->id,
            'session_no' => 2,
        ])
        ->assertCreated();
});

test('a per-year fee is refused while the structure already charges per session', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $structure = FeeStructure::factory()->create();
    $token = courseFeeAssignmentsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->postJson('/api/course-fee-assignments', [
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
            'fee_structure_id' => $structure->id,
            'span' => 'session',
            'year_no' => 1,
            'session_no' => 1,
        ])
        ->assertCreated();

    $this->withToken($token)
        ->postJson('/api/course-fee-assignments', [
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
            'fee_structure_id' => $structure->id,
            'span' => 'year',
            'year_no' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fee_structure_id']);
});

test('an update may switch its own span but not onto a structures opposite span', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $structure = FeeStructure::factory()->create();
    $otherStructure = FeeStructure::factory()->create();
    $token = courseFeeAssignmentsAccessTokenFor(User::factory()->create());

    $annual = CourseFeeAssignment::factory()->create([
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
        'fee_structure_id' => $structure->id,
        'span' => FeeSpan::Year,
        'year_no' => 1,
        'session_no' => null,
    ]);
    $termly = CourseFeeAssignment::factory()->create([
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
        'fee_structure_id' => $otherStructure->id,
        'span' => FeeSpan::Session,
        'year_no' => 1,
        'session_no' => 1,
    ]);

    // Moving onto a structure that already charges this year as a year
    // fee is refused — the session slot itself is free, so the clash is
    // the opposite-span coverage, not the slot.
    $this->withToken($token)
        ->putJson('/api/course-fee-assignments/'.$termly->id, [
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
            'fee_structure_id' => $structure->id,
            'span' => 'session',
            'year_no' => 1,
            'session_no' => 2,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fee_structure_id']);

    // Switching its own span is not a clash with itself.
    $this->withToken($token)
        ->putJson('/api/course-fee-assignments/'.$annual->id, [
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
            'fee_structure_id' => $structure->id,
            'span' => 'session',
            'year_no' => 1,
            'session_no' => 3,
        ])
        ->assertOk()
        ->assertJsonPath('course_fee_assignment.span', 'session')
        ->assertJsonPath('course_fee_assignment.session_no', 3);
});

test('an assignment can be corrected, stamped with the updating user', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $oldStructure = FeeStructure::factory()->create();
    $newStructure = FeeStructure::factory()->create();
    $creator = User::factory()->create();
    $updater = User::factory()->create();
    $assignment = CourseFeeAssignment::factory()->create([
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
        'fee_structure_id' => $oldStructure->id,
        'span' => FeeSpan::Session,
        'year_no' => 1,
        'session_no' => 1,
        'created_by' => $creator->id,
    ]);

    // Switching the span to per year clears the session pin.
    $this->withToken(courseFeeAssignmentsAccessTokenFor($updater))
        ->putJson('/api/course-fee-assignments/'.$assignment->id, [
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
            'fee_structure_id' => $newStructure->id,
            'span' => 'year',
            'year_no' => 1,
            'session_no' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('course_fee_assignment.fee_structure_id', $newStructure->id)
        ->assertJsonPath('course_fee_assignment.span', 'year')
        ->assertJsonPath('course_fee_assignment.session_no', null)
        ->assertJsonPath('course_fee_assignment.created_by', $creator->id)
        ->assertJsonPath('course_fee_assignment.updated_by', $updater->id);

    $this->assertDatabaseHas('course_fee_assignments', [
        'id' => $assignment->id,
        'fee_structure_id' => $newStructure->id,
        'span' => 'year',
        'session_no' => null,
        'updated_by' => $updater->id,
    ]);
});

test('updating an assignment validates its slot excluding itself', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $assignment = CourseFeeAssignment::factory()->create([
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
        'span' => FeeSpan::Session,
        'year_no' => 1,
        'session_no' => 1,
    ]);
    CourseFeeAssignment::factory()->create([
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
        'span' => FeeSpan::Session,
        'year_no' => 1,
        'session_no' => 2,
    ]);
    $token = courseFeeAssignmentsAccessTokenFor(User::factory()->create());

    // Keeping its own slot is not a duplicate of itself.
    $this->withToken($token)
        ->putJson('/api/course-fee-assignments/'.$assignment->id, [
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
            'fee_structure_id' => $assignment->fee_structure_id,
            'span' => 'session',
            'year_no' => 1,
            'session_no' => 1,
        ])
        ->assertOk();

    // Moving onto a slot that already charges is refused.
    $this->withToken($token)
        ->putJson('/api/course-fee-assignments/'.$assignment->id, [
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
            'fee_structure_id' => $assignment->fee_structure_id,
            'span' => 'session',
            'year_no' => 1,
            'session_no' => 2,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['year_no']);
});

test('an assignment is soft deleted and its slot becomes reusable', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();
    $structure = FeeStructure::factory()->create();
    $token = courseFeeAssignmentsAccessTokenFor(User::factory()->create());
    $assignment = CourseFeeAssignment::factory()->create([
        'course_id' => $course->id,
        'curriculum_id' => $curriculum->id,
        'fee_structure_id' => $structure->id,
        'span' => FeeSpan::Session,
        'year_no' => 1,
        'session_no' => 1,
    ]);

    $this->withToken($token)
        ->deleteJson('/api/course-fee-assignments/'.$assignment->id)
        ->assertNoContent();

    $this->assertSoftDeleted('course_fee_assignments', ['id' => $assignment->id]);

    $this->withToken($token)
        ->getJson('/api/course-fee-assignments')
        ->assertOk()
        ->assertJsonCount(0, 'course_fee_assignments');

    $this->withToken($token)
        ->postJson('/api/course-fee-assignments', [
            'course_id' => $course->id,
            'curriculum_id' => $curriculum->id,
            'fee_structure_id' => $structure->id,
            'span' => 'year',
            'year_no' => 1,
        ])
        ->assertCreated();
});

test('assignment payloads carry the summed fee amount of their structure', function () {
    $structure = FeeStructure::factory()->create();
    FeeStructureItem::factory()->create([
        'fee_structure_id' => $structure->id,
        'name' => 'Tuition',
        'amount' => 45000,
    ]);
    FeeStructureItem::factory()->create([
        'fee_structure_id' => $structure->id,
        'name' => 'Examination Fees',
        'amount' => 5000,
    ]);
    $assignment = CourseFeeAssignment::factory()->create([
        'fee_structure_id' => $structure->id,
    ]);
    $emptyStructure = FeeStructure::factory()->create();
    CourseFeeAssignment::factory()->create([
        'fee_structure_id' => $emptyStructure->id,
    ]);
    $token = courseFeeAssignmentsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->getJson('/api/course-fee-assignments')
        ->assertOk()
        ->assertJsonPath('course_fee_assignments.0.fee_structure.total', 50000)
        ->assertJsonPath('course_fee_assignments.1.fee_structure.total', 0);

    $this->withToken($token)
        ->getJson('/api/course-fee-assignments/'.$assignment->id)
        ->assertOk()
        ->assertJsonPath('course_fee_assignment.fee_structure.total', 50000);
});

test('a missing assignment returns 404', function () {
    $token = courseFeeAssignmentsAccessTokenFor(User::factory()->create());

    $this->withToken($token)->getJson('/api/course-fee-assignments/9999')->assertNotFound();
    $this->withToken($token)->deleteJson('/api/course-fee-assignments/9999')->assertNotFound();
});
