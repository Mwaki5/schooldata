<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseStage>
 */
class CourseStageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The default module number sits outside the generated 1..n ladder so a
     * factory-created stage never collides with stages the course already
     * carries. Tests that need a specific position set it explicitly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $moduleNo = fake()->numberBetween(100, 999);

        return [
            'course_id' => Course::factory(),
            'module_no' => $moduleNo,
            'year_no' => 99,
            'session_no' => 1,
            'code' => "Y99S{$moduleNo}",
        ];
    }
}
