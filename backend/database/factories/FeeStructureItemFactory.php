<?php

namespace Database\Factories;

use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeStructureItem>
 */
class FeeStructureItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fee_structure_id' => FeeStructure::factory(),
            'name' => fake()->unique()->randomElement([
                'Tuition',
                'Examination Fees',
                'Library Levy',
                'Laboratory Levy',
                'Registration Fees',
                'Medical Levy',
                'Identity Card',
                'Sports Levy',
            ]),
            'amount' => fake()->randomFloat(2, 500, 60000),
        ];
    }
}
