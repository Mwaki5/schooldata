<?php

namespace Database\Factories;

use App\Enums\UnitType;
use App\Models\Course;
use App\Models\CourseUnit;
use App\Models\Curriculum;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseUnit>
 */
class CourseUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'course_id' => Course::factory(),
            'curriculum_id' => Curriculum::factory(),
            'modules_taught' => fake()->optional()->numberBetween(1, 12),
            'taught_hours' => fake()->optional()->numberBetween(10, 80),
            'credit_factor' => fake()->optional()->randomFloat(2, 1, 5),
            'unit_type' => fake()->randomElement(UnitType::values()),
            'is_active' => fake()->boolean(80),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
