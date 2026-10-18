<?php

namespace App\Http\Requests\Units;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
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
     * The code stays unique inside the (course, curriculum) pair the
     * unit ends up under, ignoring the record itself, so teaching loads
     * can be edited without tripping the pair's uniqueness.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $unit = $this->route('unit');

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
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('units', 'code')
                    ->where('course_id', $this->input('course_id') ?? $unit?->course_id)
                    ->where('curriculum_id', $this->input('curriculum_id') ?? $unit?->curriculum_id)
                    ->whereNull('deleted_at')
                    ->ignore($unit?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'modules_taught' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'taught_hours' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'credit_factor' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'core' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
