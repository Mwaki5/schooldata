<?php

namespace App\Http\Requests\Invoices;

use App\Enums\InvoiceType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOtherInvoiceRequest extends FormRequest
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
            'student_id' => ['required', 'integer', 'exists:student_profiles,id'],
            // The structured fee invoice has its own endpoint and its
            // own rules, so it is not offerable here.
            'invoice_type' => ['required', 'string', Rule::in(InvoiceType::otherValues())],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100'],
        ];
    }
}
