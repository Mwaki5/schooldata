<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFeeInvoiceRequest extends FormRequest
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
     * No amount rule exists here on purpose: the amount of a fee
     * invoice is decided by the backend from the fee structure, and a
     * client-sent amount is never read.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'fee_structure_id' => ['required', 'integer', 'exists:fee_structures,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'confirm_duplicate' => ['nullable', 'boolean'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100'],
        ];
    }
}
