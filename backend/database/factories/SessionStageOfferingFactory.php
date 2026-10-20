<?php

namespace Database\Factories;

use App\Enums\OfferingStatus;
use App\Models\AcademicSession;
use App\Models\CourseStage;
use App\Models\SessionStageOffering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionStageOffering>
 */
class SessionStageOfferingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_session_id' => AcademicSession::factory(),
            'course_stage_id' => CourseStage::factory(),
            'status' => OfferingStatus::Open,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    /**
     * An offering that exists but is explicitly withdrawn.
     */
    public function closed(): static
    {
        return $this->state(fn (): array => ['status' => OfferingStatus::Closed]);
    }
}
