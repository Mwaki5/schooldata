<?php

namespace Database\Factories;

use App\Models\CertificationLevel;
use App\Models\Course;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true));

        return [
            'code' => strtoupper(fake()->unique()->bothify('??###')),
            'initials' => strtoupper(fake()->unique()->bothify('??')),
            'name' => $name,
            'total_modules' => fake()->boolean(70) ? fake()->numberBetween(3, 24) : null,
            'entry_grade' => fake()->boolean(50) ? strtoupper(fake()->randomLetter()) : null,
            'description' => fake()->optional()->sentence(),
            'is_active' => fake()->boolean(80),
            'certification_level_id' => fake()->boolean(60) ? CertificationLevel::factory() : null,
            'department_id' => fake()->boolean(60) ? Department::factory() : null,
        ];
    }
}
