<?php

namespace Database\Factories;

use App\Models\CertificationLevel;
use App\Models\ExamBody;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificationLevel>
 */
class CertificationLevelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Foundation Certificate',
            'Certificate',
            'Diploma',
            'Advanced Diploma',
            'Bachelor Degree',
            'Masters Degree',
            'Doctorate',
        ]);

        return [
            'exam_body_id' => ExamBody::factory(),
            'name' => $name,
            'code' => strtoupper(str_replace(' ', '', $name)),
            'description' => fake()->sentence(),
            'sequence' => fake()->numberBetween(1, 10),
            'is_active' => fake()->boolean(80),
        ];
    }
}
