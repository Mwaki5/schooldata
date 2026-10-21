<?php

namespace App\Http\Requests\CourseUnits;

use App\Enums\UnitType;
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
     * Only the delivery fields are editable: which unit is linked into
     * which course + curriculum pair is the link's identity, so moving
     * it means removing the link and creating another.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'modules_taught' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'taught_hours' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'credit_factor' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'unit_type' => ['nullable', 'string', Rule::enum(UnitType::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
