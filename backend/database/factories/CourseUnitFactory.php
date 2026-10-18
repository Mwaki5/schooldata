<?php

namespace Database\Factories;

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
            'core' => fake()->boolean(80),
            'time_offered' => fake()->randomElement([
                'Year 1 Term 1',
                'Year 1 Term 2',
                'Year 2 Term 1',
                'Year 2 Term 2',
            ]),
            'taught_hours' => fake()->numberBetween(10, 120),
            'credit_factor' => fake()->randomElement([1.0, 1.5, 2.0, 2.5, 3.0]),
            'is_active' => fake()->boolean(80),
        ];
    }
}
