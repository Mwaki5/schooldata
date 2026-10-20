<?php

namespace App\Http\Requests\Students;

use App\Enums\EnrolmentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
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
            'admission_no' => [
                'required',
                'string',
                'max:100',
                Rule::unique('users', 'login_id')
                    ->whereNull('deleted_at')
                    ->ignore($this->route('student')?->user_id ?? 0),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', 'string', Rule::in(StoreStudentRequest::GENDERS)],
            'status' => ['nullable', 'string', Rule::in(StoreStudentRequest::STATUSES)],
            'phone' => ['nullable', 'string', 'max:50'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date'],
            'county' => ['nullable', 'string', 'max:100'],
            'place_of_residence' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:5000'],
            'profile_picture' => ['nullable', 'string', 'max:2048'],
            'alternative_phone' => ['nullable', 'string', 'max:50'],
            'is_pwd' => ['nullable', 'boolean'],
            'disability_type' => ['nullable', 'string', 'max:100'],
            'disability_description' => ['nullable', 'string', 'max:5000'],
            'sponsored' => ['nullable', 'boolean'],
            'sponsored_by' => ['nullable', 'string', 'max:150'],
            'index_no' => ['nullable', 'string', 'max:50'],
            'exam_year' => ['nullable', 'digits:4'],
            'previous_school' => ['nullable', 'string', 'max:150'],
            // The enrolment segment rides on the student form and lands in
            // course_enrolments; the controller splits it off inside one
            // transaction together with the profile, account and enrolment.
            'enrolment' => ['required', 'array'],
            'enrolment.course_id' => [
                'required',
                'integer',
                Rule::exists('courses', 'id')->whereNull('deleted_at'),
            ],
            'enrolment.course_stage_id' => [
                'nullable',
                'integer',
                Rule::exists('course_stages', 'id'),
            ],
            'enrolment.curriculum_id' => [
                'required',
                'integer',
                Rule::exists('curricula', 'id')->whereNull('deleted_at'),
            ],
            'enrolment.academic_session_id' => [
                'nullable',
                'integer',
                Rule::exists('academic_sessions', 'id'),
            ],
            'enrolment.certification_level_id' => [
                'required',
                'integer',
                Rule::exists('certification_levels', 'id')->whereNull('deleted_at'),
            ],
            'enrolment.enrolment_date' => ['required', 'date'],
            'enrolment.status' => ['nullable', Rule::enum(EnrolmentStatus::class)],
            'enrolment.remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
