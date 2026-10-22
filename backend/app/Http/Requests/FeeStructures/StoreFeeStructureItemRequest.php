<?php

namespace App\Http\Requests\FeeStructures;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeeStructureItemRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('fee_structure_items', 'name')
                    ->where('fee_structure_id', $this->route('feeStructure')->id)
                    ->whereNull('deleted_at'),
            ],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }
}
