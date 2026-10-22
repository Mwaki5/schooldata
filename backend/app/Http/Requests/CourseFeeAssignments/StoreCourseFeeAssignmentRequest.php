<?php

namespace App\Http\Requests\CourseFeeAssignments;

use App\Enums\FeeSpan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseFeeAssignmentRequest extends FormRequest
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
     * A course fee assignment names the fee structure one course +
     * curriculum pair charges, and the span it repeats on. The pair is
     * what an enrolment carries, so it may charge under exactly one
     * live assignment.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'course_id' => [
                'required',
                'integer',
                Rule::exists('courses', 'id')->whereNull('deleted_at'),
                Rule::unique('course_fee_assignments', 'course_id')
                    ->where('curriculum_id', $this->input('curriculum_id'))
                    ->whereNull('deleted_at'),
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
