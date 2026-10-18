<?php

namespace App\Http\Requests\CourseUnits;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseUnitRequest extends FormRequest
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
     * The assignment's course and curriculum can be corrected, but the
     * same uniqueness rule applies as on creation: one delivery of a
     * unit per course + curriculum.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $assignment = $this->route('courseUnit');

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
                Rule::unique('course_units', 'curriculum_id')
                    ->whereNull('deleted_at')
                    ->where('unit_id', $assignment->unit_id)
                    ->where('course_id', $this->input('course_id'))
                    ->ignore($assignment->id),
            ],
            'core' => ['nullable', 'boolean'],
            'time_offered' => ['nullable', 'string', 'max:100'],
            'taught_hours' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'credit_factor' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
