<?php

namespace App\Http\Requests\ExamBodyGrades;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExamBodyGradeRequest extends FormRequest
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
            'grade' => [
                'required',
                'string',
                'max:50',
                Rule::unique('exam_body_grades', 'grade')
                    ->whereNull('deleted_at')
                    ->where('exam_body_id', $this->input('exam_body_id')),
            ],
            'grade_start' => ['required', 'numeric', 'between:0,100'],
            'grade_end' => ['required', 'numeric', 'between:0,100', 'gte:grade_start'],
            'remark' => ['nullable', 'string', 'max:2000'],
            'sequence' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
