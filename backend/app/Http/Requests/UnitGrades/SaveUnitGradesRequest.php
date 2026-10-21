<?php

namespace App\Http\Requests\UnitGrades;

use App\Enums\UnitExamType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUnitGradesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * RBAC is enforced by the role middleware on the route: only admins and
     * trainers reach this request.
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
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'student_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'course_unit_id' => ['required', 'integer', 'exists:course_units,id'],
            // The full form state: up to six slots (two types × three papers),
            // each with its mark. Empty inputs are simply left out. Each
            // (exam_type, exam_no) slot may appear only once — the distinct
            // rule compares leaves, so the composite check is a closure.
            'marks' => [
                'required',
                'array',
                function (string $attribute, array $value, callable $fail): void {
                    $slots = collect($value)->map(function ($mark): string {
                        if (! is_array($mark)) {
                            return uniqid('invalid:', true);
                        }

                        return ($mark['exam_type'] ?? '').':'.($mark['exam_no'] ?? '');
                    });

                    if ($slots->duplicates()->isNotEmpty()) {
                        $fail('Each theory or practical paper may only appear once.');
                    }
                },
            ],
            'marks.*.exam_type' => ['required', Rule::in(UnitExamType::values())],
            'marks.*.exam_no' => ['required', Rule::in([1, 2, 3])],
            'marks.*.mark' => ['required', 'numeric', 'between:0,100'],
        ];
    }
}
