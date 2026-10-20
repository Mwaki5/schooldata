<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\AcademicSessionRegistration;
use App\Models\CourseStage;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicSessionRegistration>
 */
class AcademicSessionRegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A module is one session of study and three modules make one academic
     * year, so year_no / session_no are derived from module_no.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $moduleNo = fake()->numberBetween(1, 12);

        return [
            'academic_session_id' => AcademicSession::factory(),
            'student_id' => StudentProfile::factory(),
            'course_stage_id' => CourseStage::factory(),
            'module_no' => $moduleNo,
            'year_no' => intdiv($moduleNo - 1, 3) + 1,
            'session_no' => (($moduleNo - 1) % 3) + 1,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
