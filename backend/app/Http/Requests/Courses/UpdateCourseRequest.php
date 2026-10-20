<?php

namespace App\Http\Requests\Courses;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('courses', 'code')
                    ->whereNull('deleted_at')
                    ->ignore($this->route('course')->id),
            ],
            'initials' => ['required', 'string', 'max:20'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('courses', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($this->route('course')->id),
            ],
            'total_modules' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'entry_grade' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'certification_level_id' => [
                'nullable',
                'integer',
                Rule::exists('certification_levels', 'id')->whereNull('deleted_at'),
            ],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id'),
            ],
        ];
    }
}
