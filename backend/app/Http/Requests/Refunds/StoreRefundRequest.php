<?php

namespace App\Http\Requests\Refunds;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRefundRequest extends FormRequest
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
            'payment_method' => ['required', 'string', Rule::in(PaymentMethod::values())],
            'reference' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100'],
        ];
    }
}
