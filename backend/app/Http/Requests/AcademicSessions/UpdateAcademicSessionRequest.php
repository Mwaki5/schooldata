<?php

namespace App\Http\Requests\AcademicSessions;

use App\Http\Requests\AcademicSessions\Concerns\ValidatesSessionScheduling;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicSessionRequest extends FormRequest
{
    use ValidatesSessionScheduling;

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
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('academic_sessions', 'name')->ignore($this->route('academicSession')->id),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
