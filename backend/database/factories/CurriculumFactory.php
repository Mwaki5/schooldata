<?php

namespace Database\Factories;

use App\Models\Curriculum;
use App\Models\ExamBody;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Curriculum>
 */
class CurriculumFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Foundation Curriculum',
            'Intermediate Curriculum',
            'Advanced Curriculum',
            'Professional Curriculum',
            'Technical Curriculum',
        ]);

        return [
            'exam_body_id' => ExamBody::factory(),
            'name' => $name,
            'is_active' => fake()->boolean(80),
        ];
    }
}
