<?php

namespace App\Http\Requests\CertificationLevels;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCertificationLevelRequest extends FormRequest
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
            'exam_body_id' => [
                'required',
                'integer',
                Rule::exists('exam_bodies', 'id')->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('certification_levels', 'code')
                    ->whereNull('deleted_at')
                    ->where('exam_body_id', $this->input('exam_body_id'))
                    ->ignore($this->route('certificationLevel')->id),
            ],
            'description' => ['required', 'string', 'max:2000'],
            'sequence' => ['required', 'integer', 'min:0', 'max:32767'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
