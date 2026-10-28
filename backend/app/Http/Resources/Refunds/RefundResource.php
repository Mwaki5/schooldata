<?php

namespace App\Http\Resources\Refunds;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
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
            'refund_number' => $this->refund_number,
            'student_id' => $this->student_id,
            'student_name' => $this->studentName(),
            'student_login_id' => $this->student?->user?->login_id,
            'academic_session_id' => $this->academic_session_id,
            'academic_session_name' => $this->academicSession?->name,
            'payment_method' => $this->payment_method?->value,
            'payment_method_label' => $this->payment_method?->label(),
            'reference' => $this->reference,
            'amount' => (float) $this->amount,
            'entry_kind' => $this->entry_kind?->value,
            'reverses_refund_id' => $this->reverses_refund_id,
            'reversal_reason' => $this->isReversal() ? $this->reason : null,
            'reversed_by' => $this->whenLoaded('reversedBy', fn () => $this->reversedBy->first()?->id),
            'reverses' => $this->whenLoaded('reverses', fn () => $this->reverses?->refund_number),
            'reason' => $this->reason,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * The refunded student's composed name, falling back to the name
     * on their account when no profile name exists.
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
