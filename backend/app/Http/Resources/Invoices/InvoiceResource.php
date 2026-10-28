<?php

namespace App\Http\Resources\Invoices;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Get the data that should be returned in the resource.
     *
     * `paid`, `outstanding` and `status` are never stored on the row:
     * the controllers attach the figures the StudentLedgerService
     * derived for it, so what a client reads always reflects the live
     * ledger.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'student_id' => $this->student_id,
            // The billed student's display name and admission number,
            // rendered for the list and the invoice head alike.
            'student_name' => $this->studentName(),
            'student_login_id' => $this->student?->user?->login_id,
            'academic_session_id' => $this->academic_session_id,
            'academic_session_name' => $this->academicSession?->name,
            'invoice_type' => $this->invoice_type?->value,
            'fee_structure_id' => $this->fee_structure_id,
            'fee_structure_name' => $this->feeStructure?->name,
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'paid' => (float) ($this->paid ?? 0),
            'outstanding' => (float) ($this->outstanding ?? 0),
            'status' => $this->status,
            'entry_kind' => $this->entry_kind?->value,
            'reverses_invoice_id' => $this->reverses_invoice_id,
            'reversal_reason' => $this->reversal_reason,
            // On a reversed original, the reversal that cancels it; on
            // a reversal row, the original it cancels. Both render on
            // the read-only invoice screen.
            'reversed_by' => $this->whenLoaded('reversedBy', function (): ?array {
                $reversal = $this->reversedBy->last();

                return $reversal === null ? null : [
                    'id' => $reversal->id,
                    'invoice_number' => $reversal->invoice_number,
                    'reversal_reason' => $reversal->reversal_reason,
                    'created_at' => $reversal->created_at,
                ];
            }),
            'reverses' => $this->whenLoaded('reverses', function (): ?array {
                $original = $this->reverses;

                return $original === null ? null : [
                    'id' => $original->id,
                    'invoice_number' => $original->invoice_number,
                    'amount' => (float) $original->amount,
                ];
            }),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * The billed student's composed name, falling back to the name on
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
