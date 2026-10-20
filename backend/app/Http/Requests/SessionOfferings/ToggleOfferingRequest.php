<?php

namespace App\Http\Requests\SessionOfferings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ToggleOfferingRequest extends FormRequest
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
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'course_stage_id' => ['required', 'integer', 'exists:course_stages,id'],
            'open' => ['required', 'boolean'],
        ];
    }
}
