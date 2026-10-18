<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2000, 2099);
        $start = fake()->dateTimeBetween('-1 year', '+1 year')->format('Y-m-d');

        return [
            'name' => "{$year}/".($year + 1).' Academic Year',
            'start_date' => $start,
            'end_date' => (new \DateTime($start))->modify('+10 months')->format('Y-m-d'),
            'description' => fake()->optional()->sentence(),
            'is_active' => fake()->boolean(40),
        ];
    }
}
