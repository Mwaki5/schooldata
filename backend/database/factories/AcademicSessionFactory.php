<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicSession>
 */
class AcademicSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 year', '+1 year')->format('Y-m-d');

        return [
            'academic_year_id' => AcademicYear::factory(),
            'name' => 'Session '.fake()->unique()->numberBetween(10000, 99999),
            'start_date' => $start,
            'end_date' => (new \DateTime($start))->modify('+4 months')->format('Y-m-d'),
            'description' => fake()->optional()->sentence(),
            'is_active' => fake()->boolean(40),
        ];
    }
}
