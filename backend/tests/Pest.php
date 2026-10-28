<?php

use App\Enums\EnrolmentStatus;
use App\Models\AcademicSession;
use App\Models\CourseEnrolment;
use App\Models\CourseFeeAssignment;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A short-lived access token for the given user, the way the API
 * issues them.
 */
function financeAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

/**
 * The one active academic session every finance write is stamped
 * with.
 */
function financeActiveSession(): AcademicSession
{
    return AcademicSession::factory()->create(['is_active' => true]);
}

/**
 * A student the given session's fee structure can legally bill: an
 * ENROLLED enrolment on a course + curriculum pair, with a course fee
 * assignment pointing that pair at a live structure of the given
 * total. Returns [student, feeStructure].
 *
 * @return array{0: StudentProfile, 1: FeeStructure}
 */
function financeBillableStudent(AcademicSession $session, float $total = 45000): array
{
    $student = StudentProfile::factory()->create();

    $feeStructure = FeeStructure::factory()->create(['is_active' => true]);
    FeeStructureItem::factory()->for($feeStructure)->create(['name' => 'Tuition', 'amount' => $total]);

    $enrolment = CourseEnrolment::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'status' => EnrolmentStatus::Enrolled,
    ]);

    CourseFeeAssignment::factory()->create([
        'course_id' => $enrolment->course_id,
        'curriculum_id' => $enrolment->curriculum_id,
        'fee_structure_id' => $feeStructure->id,
    ]);

    return [$student, $feeStructure];
}
