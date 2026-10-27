<?php

namespace App\Http\Requests\Invoices;

use App\Enums\InvoiceItemType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
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
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.type' => ['required', 'string', Rule::in(InvoiceItemType::values())],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
