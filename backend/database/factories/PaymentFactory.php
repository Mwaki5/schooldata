<?php

namespace Database\Factories;

use App\Enums\PaymentEntryKind;
use App\Enums\PaymentMethod;
use App\Models\AcademicSession;
use App\Models\Payment;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_number' => 'RCP-'.now()->format('Y').'-'.fake()->unique()->numerify('####'),
            'student_id' => StudentProfile::factory(),
            'academic_session_id' => AcademicSession::factory(),
            'payment_method' => PaymentMethod::Cash,
            'payment_date' => now()->toDateString(),
            'amount' => fake()->randomFloat(2, 100, 100000),
            'entry_kind' => PaymentEntryKind::Payment,
        ];
    }

    /**
     * A reversal entry cancelling the given original payment.
     */
    public function reversed(Payment $original): static
    {
        return $this->state(fn (array $attributes): array => [
            'student_id' => $original->student_id,
            'academic_session_id' => $original->academic_session_id,
            'payment_method' => $original->payment_method,
            'reference' => 'REV: '.($original->reference ?? $original->receipt_number),
            'amount' => -1 * (float) $original->amount,
            'entry_kind' => PaymentEntryKind::PaymentReversal,
            'reverses_payment_id' => $original->id,
            'reversal_reason' => fake()->sentence(5),
        ]);
    }
}
