<?php

namespace App\Http\Requests\Staff;

use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employment_no' => [
                'required',
                'string',
                'max:50',
                Rule::unique('staff', 'employment_no')
                    ->whereNull('deleted_at')
                    ->ignore($this->route('staff')->id),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', 'string', Rule::in(StoreStaffRequest::GENDERS)],
            'date_of_birth' => ['nullable', 'date'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'county' => ['nullable', 'string', 'max:100'],
            'place_of_birth' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:5000'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'profile_picture' => ['nullable', 'string', 'max:2048'],
            'alternative_phone' => ['nullable', 'string', 'max:50'],
            'is_pwd' => ['nullable', 'boolean'],
            'disability_type' => ['nullable', 'string', 'max:100'],
            'disability_description' => ['nullable', 'string', 'max:5000'],
            'work_email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('staff', 'work_email')
                    ->whereNull('deleted_at')
                    ->ignore($this->route('staff')->id),
            ],
            'work_phone' => ['nullable', 'string', 'max:50'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'kra_pin' => ['nullable', 'string', 'max:50'],
            'nhif_number' => ['nullable', 'string', 'max:50'],
            'nssf_number' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employment_type' => ['nullable', 'string', Rule::in(StoreStaffRequest::EMPLOYMENT_TYPES)],
            'employment_status' => ['nullable', 'string', Rule::in(StoreStaffRequest::EMPLOYMENT_STATUSES)],
            // Any seeded or custom role the system knows may ride along
            // with a staff profile and mirrors onto its account; the
            // student role cannot, since students are not staff.
            'role' => ['required', 'string', 'max:50', Rule::exists('roles', 'name'), Rule::notIn([Role::Student->value])],
            'date_joined' => ['nullable', 'date'],
            'confirmation_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date'],
            'termination_reason' => ['nullable', 'string', 'max:5000'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'highest_qualification' => ['nullable', 'string', 'max:100'],
            'specialization' => ['nullable', 'string', 'max:255'],
        ];
    }
}
