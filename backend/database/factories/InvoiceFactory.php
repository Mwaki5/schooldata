<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
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
            'status' => InvoiceStatus::Pending,
            'description' => fake()->sentence(4),
        ];
    }
}
