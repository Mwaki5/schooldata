<?php

namespace App\Http\Requests\CourseFeeAssignments;

use App\Enums\FeeSpan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseFeeAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A per-year fee spans its whole year, so any incoming session
     * number is dropped before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('span') !== FeeSpan::Session->value) {
            $this->merge(['session_no' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The assignment may be corrected or moved to another slot, but the
     * destination slot may still charge under only one live assignment
     * (this one excluded).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $assignmentId = (int) $this->route('courseFeeAssignment')->id;

        return [
            'course_id' => [
                'required',
                'integer',
                Rule::exists('courses', 'id')->whereNull('deleted_at'),
            ],
            'curriculum_id' => [
                'required',
                'integer',
                Rule::exists('curricula', 'id')->whereNull('deleted_at'),
            ],
            'fee_structure_id' => [
                'required',
                'integer',
                Rule::exists('fee_structures', 'id')->whereNull('deleted_at'),
            ],
            'span' => ['required', 'string', Rule::enum(FeeSpan::class)],
            'year_no' => [
                'required',
                'integer',
                'min:1',
                'max:255',
                Rule::unique('course_fee_assignments', 'year_no')
                    ->where('course_id', $this->input('course_id'))
                    ->where('curriculum_id', $this->input('curriculum_id'))
                    ->where('session_no', $this->input('session_no'))
                    ->whereNull('deleted_at')
                    ->ignore($assignmentId),
            ],
            'session_no' => [
                'nullable',
                'integer',
                'min:1',
                'max:255',
                'required_if:span,'.FeeSpan::Session->value,
            ],
        ];
    }
}
