<?php

namespace App\Http\Requests\Me;

use Illuminate\Foundation\Http\FormRequest;

class RegisterSessionUnitsRequest extends FormRequest
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
     * Link existence is not checked here: the controller accepts only the
     * course units actually offered to the student for the running
     * session, so a non-existent or foreign id fails with one clear
     * message.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'course_units' => ['required', 'array', 'min:1', 'max:100'],
            'course_units.*' => ['integer', 'distinct'],
        ];
    }
}
