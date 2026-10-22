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
     * Get the validation rules that apply to the request.
     *
     * The assignment may be corrected or moved to another pair, but the
     * destination pair may still charge under only one live assignment
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
                Rule::unique('course_fee_assignments', 'course_id')
                    ->where('curriculum_id', $this->input('curriculum_id'))
                    ->whereNull('deleted_at')
                    ->ignore($assignmentId),
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
        ];
    }
}
