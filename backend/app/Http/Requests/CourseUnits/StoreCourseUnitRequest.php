<?php

namespace App\Http\Requests\CourseUnits;

use App\Enums\UnitType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseUnitRequest extends FormRequest
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
     * A course unit links one catalogue unit into one course +
     * curriculum pair: the pair owns the teaching load (module, hours,
     * credit) and the unit's role there (basic / core / common, active),
     * so the same unit may be linked into several courses differently.
     * Each unit is linked into a pair once.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->whereNull('deleted_at'),
                Rule::unique('course_units', 'unit_id')
                    ->where('course_id', $this->input('course_id'))
                    ->where('curriculum_id', $this->input('curriculum_id')),
            ],
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
            'modules_taught' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'taught_hours' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'credit_factor' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'unit_type' => ['nullable', 'string', Rule::enum(UnitType::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
