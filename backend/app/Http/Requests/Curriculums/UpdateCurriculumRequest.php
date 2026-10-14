<?php

namespace App\Http\Requests\Curriculums;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurriculumRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('curricula', 'name')
                    ->whereNull('deleted_at')
                    ->where('exam_body_id', $this->input('exam_body_id'))
                    ->ignore($this->route('curriculum')->id),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
