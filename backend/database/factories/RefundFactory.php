<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\RefundEntryKind;
use App\Models\AcademicSession;
use App\Models\Refund;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'refund_number' => 'RFD-'.now()->format('Y').'-'.fake()->unique()->numerify('####'),
            'student_id' => StudentProfile::factory(),
            'academic_session_id' => AcademicSession::factory(),
            'payment_method' => PaymentMethod::Bank,
            'amount' => fake()->randomFloat(2, 100, 10000),
            'entry_kind' => RefundEntryKind::Refund,
            'reason' => fake()->sentence(5),
        ];
    }
}
