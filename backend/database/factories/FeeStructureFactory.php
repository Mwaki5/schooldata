<?php

namespace Database\Factories;

use App\Models\FeeStructure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeStructure>
 */
class FeeStructureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Fee Plan',
            'is_active' => fake()->boolean(80),
        ];
    }
}
