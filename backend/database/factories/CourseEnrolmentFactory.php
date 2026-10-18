<?php

namespace Database\Factories;

use App\Enums\EnrolmentStatus;
use App\Models\AcademicSession;
use App\Models\CertificationLevel;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\Curriculum;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseEnrolment>
 */
class CourseEnrolmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => StudentProfile::factory(),
            'course_id' => Course::factory(),
            'curriculum_id' => Curriculum::factory(),
            'academic_session_id' => AcademicSession::factory(),
            'certification_level_id' => CertificationLevel::factory(),
            'enrolment_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'status' => fake()->randomElement([
                EnrolmentStatus::Enrolled->value,
                EnrolmentStatus::Completed->value,
                EnrolmentStatus::Dropped->value,
            ]),
            'remarks' => fake()->boolean(40) ? fake()->sentence() : null,
        ];
    }
}
