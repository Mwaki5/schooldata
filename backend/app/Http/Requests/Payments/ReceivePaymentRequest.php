<?php

namespace App\Http\Requests\Payments;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceivePaymentRequest extends FormRequest
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
     * The date is the server's day by default; only a holder of
     * `payments.backdate` may send one into the past, and the service
     * refuses the write for anyone else.
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
            'notes' => ['nullable', 'string', 'max:500'],
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100'],
        ];
    }
}
