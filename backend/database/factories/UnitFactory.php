<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
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
            'code' => strtoupper(fake()->unique()->bothify('U####')),
            'name' => ucwords(fake()->unique()->words(3, true)),
            'description' => fake()->optional()->sentence(),
            'modules_taught' => fake()->optional()->numberBetween(1, 12),
            'taught_hours' => fake()->optional()->numberBetween(10, 80),
            'credit_factor' => fake()->optional()->randomFloat(2, 1, 5),
            'core' => fake()->boolean(80),
            'is_active' => fake()->boolean(80),
        ];
    }
}
