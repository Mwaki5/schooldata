<?php

namespace App\Http\Requests\TermEvents;

use App\Enums\TermEventName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTermEventRequest extends FormRequest
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
            'academic_session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')],
            'name' => [
                'required',
                'string',
                Rule::in(TermEventName::values()),
                // Each session carries one date range per event.
                Rule::unique('term_events', 'name')
                    ->where(
                        fn ($query) => $query->where('academic_session_id', $this->input('academic_session_id')),
                    )
                    ->ignore($this->route('termEvent')->id),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.in' => 'Choose one of the supported term events.',
            'name.unique' => 'This event already has a date range in the selected session.',
        ];
    }
}
