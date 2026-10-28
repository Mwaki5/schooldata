<?php

namespace Database\Factories;

use App\Enums\InvoiceEntryKind;
use App\Enums\InvoiceType;
use App\Models\AcademicSession;
use App\Models\Invoice;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_number' => 'INV-'.now()->format('Y').'-'.fake()->unique()->numerify('####'),
            'student_id' => StudentProfile::factory(),
            'academic_session_id' => AcademicSession::factory(),
            'invoice_type' => InvoiceType::Other,
            'description' => fake()->sentence(4),
            'amount' => fake()->randomFloat(2, 100, 100000),
            'entry_kind' => InvoiceEntryKind::Invoice,
        ];
    }

    /**
     * A reversal entry cancelling the given original invoice.
     */
    public function reversed(Invoice $original): static
    {
        return $this->state(fn (array $attributes): array => [
            'student_id' => $original->student_id,
            'academic_session_id' => $original->academic_session_id,
            'invoice_type' => $original->invoice_type,
            'amount' => -1 * (float) $original->amount,
            'entry_kind' => InvoiceEntryKind::Reversal,
            'reverses_invoice_id' => $original->id,
            'reversal_reason' => fake()->sentence(5),
        ]);
    }
}
