<?php

namespace Database\Factories;

use App\Enums\UnitExamType;
use App\Models\AcademicSession;
use App\Models\CourseUnit;
use App\Models\StudentProfile;
use App\Models\UnitGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitGrade>
 */
class UnitGradeFactory extends Factory
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
            'exam_type' => fake()->randomElement(UnitExamType::values()),
            'exam_no' => fake()->numberBetween(1, 3),
            'mark' => fake()->randomFloat(2, 0, 100),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
