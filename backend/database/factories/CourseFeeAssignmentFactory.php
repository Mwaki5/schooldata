<?php

namespace Database\Factories;

use App\Enums\FeeSpan;
use App\Models\Course;
use App\Models\CourseFeeAssignment;
use App\Models\Curriculum;
use App\Models\FeeStructure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseFeeAssignment>
 */
class CourseFeeAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'curriculum_id' => Curriculum::factory(),
            'fee_structure_id' => FeeStructure::factory(),
            'span' => fake()->randomElement(FeeSpan::values()),
        ];
    }
}
