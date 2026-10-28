<?php

namespace App\Http\Resources\Payments;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Get the data that should be returned in the resource.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'student_id' => $this->student_id,
            'student_name' => $this->studentName(),
            'student_login_id' => $this->student?->user?->login_id,
            'academic_session_id' => $this->academic_session_id,
            'academic_session_name' => $this->academicSession?->name,
            'payment_method' => $this->payment_method?->value,
            'payment_method_label' => $this->payment_method?->label(),
            'reference' => $this->reference,
            'payment_date' => $this->payment_date?->toDateString(),
            'amount' => (float) $this->amount,
            'entry_kind' => $this->entry_kind?->value,
            'reverses_payment_id' => $this->reverses_payment_id,
            'reversal_reason' => $this->reversal_reason,
            // On a reversed original, the reversal that cancels it; on
            // a reversal row, the original it cancels. Both render on
            // the read-only payment screens, and `reversed_by` is what
            // disables the Reverse action on an already-reversed row.
            'reversed_by' => $this->whenLoaded('reversedBy', function (): ?array {
                $reversal = $this->reversedBy->last();

                return $reversal === null ? null : [
                    'id' => $reversal->id,
                    'receipt_number' => $reversal->receipt_number,
                    'reversal_reason' => $reversal->reversal_reason,
                    'created_at' => $reversal->created_at,
                ];
            }),
            'reverses' => $this->whenLoaded('reverses', function (): ?array {
                $original = $this->reverses;

                return $original === null ? null : [
                    'id' => $original->id,
                    'receipt_number' => $original->receipt_number,
                    'amount' => (float) $original->amount,
                ];
            }),
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * The paying student's composed name, falling back to the name on
     * their account when no profile name exists.
     */
    private function studentName(): string
    {
        if (! $this->student) {
            return '';
        }

        return User::composeName(
            $this->student->first_name,
            $this->student->middle_name,
            $this->student->last_name,
        ) ?: ($this->student->user?->name ?? '');
    }
}
