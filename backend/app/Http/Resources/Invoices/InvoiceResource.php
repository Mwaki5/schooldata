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
            'status' => $this->status->value,
            'description' => $this->description,
            // The sum of the invoice's live line amounts, eager-loaded
            // as `total_amount` by the endpoints that read invoices.
            'total' => (float) ($this->total_amount ?? 0),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
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
