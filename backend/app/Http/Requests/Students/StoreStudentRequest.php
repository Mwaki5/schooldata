<?php

namespace App\Http\Requests\Students;

use App\Enums\EnrolmentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    /**
     * The profile statuses a student can hold.
     *
     * @var list<string>
     */
    public const STATUSES = ['active', 'inactive'];

    /**
     * The gender vocabulary shared with the identity fields.
     *
     * @var list<string>
     */
    public const GENDERS = ['male', 'female', 'other'];

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
            // The admission number doubles as the login id of the account.
            // Uniqueness is checked against live rows; the controller
            // reclaims a soft-deleted account that held the same number.
            'admission_no' => [
                'required',
                'string',
                'max:100',
                Rule::unique('users', 'login_id')->whereNull('deleted_at'),
            ],
            // Uniqueness on the account email is enforced by the database
            // translation in the controller, so a re-admitted student can
            // keep the email of their soft-deleted account.
            'email' => ['nullable', 'email', 'max:255'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', 'string', Rule::in(self::GENDERS)],
            'status' => ['nullable', 'string', Rule::in(self::STATUSES)],
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
            // transaction together with the account, profile and enrolment.
            'enrolment' => ['required', 'array'],
            'enrolment.course_id' => [
                'required',
                'integer',
                Rule::exists('courses', 'id')->whereNull('deleted_at'),
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
