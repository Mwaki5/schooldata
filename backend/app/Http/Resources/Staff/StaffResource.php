<?php

namespace App\Http\Resources\Staff;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
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
            'user_id' => $this->user_id,
            'employment_no' => $this->employment_no,
            'name' => $this->name,
            'first_name' => $this->user?->first_name,
            'middle_name' => $this->user?->middle_name,
            'last_name' => $this->user?->last_name,
            'gender' => $this->user?->gender,
            'date_of_birth' => $this->user?->date_of_birth?->toDateString(),
            'nationality' => $this->user?->nationality,
            'county' => $this->user?->county,
            'place_of_birth' => $this->user?->place_of_birth,
            'religion' => $this->user?->religion,
            'address' => $this->user?->address,
            'city' => $this->user?->city,
            'postal_code' => $this->user?->postal_code,
            'profile_picture' => $this->user?->profile_picture,
            'alternative_phone' => $this->user?->alternative_phone,
            'is_pwd' => $this->user?->is_pwd ?? false,
            'disability_type' => $this->user?->disability_type,
            'disability_description' => $this->user?->disability_description,
            'work_email' => $this->work_email,
            'work_phone' => $this->work_phone,
            'national_id' => $this->national_id,
            'kra_pin' => $this->kra_pin,
            'nhif_number' => $this->nhif_number,
            'nssf_number' => $this->nssf_number,
            'position' => $this->position,
            'department_id' => $this->department_id,
            'department_name' => $this->whenLoaded('department', fn () => $this->department?->name),
            'employment_type' => $this->employment_type,
            'employment_status' => $this->employment_status,
            'role' => $this->role,
            'date_joined' => $this->date_joined?->toDateString(),
            'confirmation_date' => $this->confirmation_date?->toDateString(),
            'contract_end_date' => $this->contract_end_date?->toDateString(),
            'termination_date' => $this->termination_date?->toDateString(),
            'termination_reason' => $this->termination_reason,
            'basic_salary' => $this->basic_salary,
            'highest_qualification' => $this->highest_qualification,
            'specialization' => $this->specialization,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
