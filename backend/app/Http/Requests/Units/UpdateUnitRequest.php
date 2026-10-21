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
     * The code stays unique across the live catalogue ignoring the
     * record itself, so the other fields can be edited without tripping
     * the catalogue's uniqueness.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $unit = $this->route('unit');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('units', 'code')
                    ->whereNull('deleted_at')
                    ->ignore($unit?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
