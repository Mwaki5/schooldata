<?php

namespace App\Http\Requests\Invoices;

use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
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
     * The billed student and session are the invoice's anchor and are
     * never edited here — a wrong anchor is corrected by voiding the
     * invoice and raising a new one, so the payload carries the status,
     * the note and a full replacement of the lines.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(InvoiceStatus::values())],
            'description' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.type' => ['required', 'string', Rule::in(InvoiceItemType::values())],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
