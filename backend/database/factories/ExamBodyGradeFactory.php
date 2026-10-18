<?php

namespace Database\Factories;

use App\Models\ExamBody;
use App\Models\ExamBodyGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamBodyGrade>
 */
class ExamBodyGradeFactory extends Factory
{
    /**
     * A realistic grading scale, so bands created together form a
     * consistent, non-overlapping range of scores.
     *
     * @var array<string, array{0: float, 1: float}>
     */
    private const BANDS = [
        'A' => [70.0, 100.0],
        'B' => [60.0, 69.99],
        'C' => [50.0, 59.99],
        'D' => [40.0, 49.99],
        'E' => [30.0, 39.99],
        'F' => [0.0, 29.99],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $grade = fake()->randomElement(array_keys(self::BANDS));
        [$start, $end] = self::BANDS[$grade];

        return [
            // Each band defaults to its own body, so the per-body unique
            // grade name never collides across factory-created rows.
            'exam_body_id' => ExamBody::factory(),
            'grade' => $grade,
            'grade_start' => $start,
            'grade_end' => $end,
            'remark' => fake()->optional()->sentence(),
            'is_active' => fake()->boolean(80),
            'sequence' => fake()->numberBetween(1, 10),
        ];
    }
}
