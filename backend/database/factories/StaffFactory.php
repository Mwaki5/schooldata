<?php

namespace Database\Factories;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employment_no' => strtoupper(fake()->unique()->bothify('EMP-####')),
            'name' => fake()->name(),
            'work_email' => fake()->unique()->safeEmail(),
            'work_phone' => fake()->optional()->numerify('07########'),
            'national_id' => fake()->optional()->numerify('#########'),
            'kra_pin' => fake()->optional()->numerify('A#########'),
            'position' => fake()->optional()->jobTitle(),
            'employment_type' => fake()->randomElement(['full_time', 'part_time', 'contract', 'intern']),
            'employment_status' => 'active',
            'role' => 'trainer',
            'date_joined' => fake()->optional()->dateTimeBetween('-5 years', 'now'),
            'basic_salary' => fake()->optional()->randomFloat(2, 30000, 250000),
            'highest_qualification' => fake()->optional()->randomElement([
                "Bachelor's Degree",
                "Master's Degree",
                'PhD',
                'Diploma',
            ]),
        ];
    }
}
