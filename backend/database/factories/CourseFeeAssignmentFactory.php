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
        $span = fake()->randomElement(FeeSpan::values());

        return [
            'course_id' => Course::factory(),
            'curriculum_id' => Curriculum::factory(),
            'fee_structure_id' => FeeStructure::factory(),
            'span' => $span,
            'year_no' => fake()->numberBetween(1, 3),
            // A per-year fee spans every session of its year; a
            // per-session fee pins to one of them.
            'session_no' => $span === FeeSpan::Session->value
                ? fake()->numberBetween(1, 2)
                : null,
        ];
    }
}
