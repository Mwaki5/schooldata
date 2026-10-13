<?php

namespace Database\Factories;

use App\Models\ExamBody;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamBody>
 */
class ExamBodyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Examination Council',
            'code' => strtoupper(fake()->unique()->bothify('???')),
            'website' => fake()->optional()->url(),
            'email' => fake()->optional()->companyEmail(),
            'phone' => fake()->optional()->numerify('+## ### ######'),
            'address' => fake()->optional()->address(),
            'description' => fake()->optional()->sentence(),
            'is_active' => fake()->boolean(80),
        ];
    }
}
