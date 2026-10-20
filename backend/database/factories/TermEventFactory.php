<?php

namespace Database\Factories;

use App\Enums\TermEventName;
use App\Models\AcademicSession;
use App\Models\TermEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TermEvent>
 */
class TermEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-6 months', '+6 months')->format('Y-m-d');

        return [
            'academic_session_id' => AcademicSession::factory(),
            'name' => fake()->randomElement(TermEventName::values()),
            'start_date' => $start,
            'end_date' => (new \DateTime($start))->modify('+'.fake()->numberBetween(1, 14).' days')->format('Y-m-d'),
            'is_active' => fake()->boolean(40),
        ];
    }
}
