<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\CourseUnit;
use App\Models\StudentProfile;
use App\Models\UnitRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitRegistration>
 */
class UnitRegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_session_id' => AcademicSession::factory(),
            'course_unit_id' => CourseUnit::factory(),
            'student_id' => StudentProfile::factory(),
            'approved_by' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    /**
     * A registration that a staff member has signed off.
     */
    public function approved(): static
    {
        return $this->state(fn (): array => [
            'approved_by' => User::factory(),
        ]);
    }
}
