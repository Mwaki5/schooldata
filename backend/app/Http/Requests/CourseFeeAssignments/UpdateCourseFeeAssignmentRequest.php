<?php

namespace App\Http\Requests\CourseFeeAssignments;

use App\Enums\FeeSpan;
use App\Models\CourseFeeAssignment;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseFeeAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A per-year fee spans its whole year, so any incoming session
     * number is dropped before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('span') !== FeeSpan::Session->value) {
            $this->merge(['session_no' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The assignment may be corrected or moved to another slot, but the
     * destination slot may still charge under only one live assignment
     * (this one excluded).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $assignmentId = (int) $this->route('courseFeeAssignment')->id;

        return [
            'course_id' => [
                'required',
                'integer',
                Rule::exists('courses', 'id')->whereNull('deleted_at'),
            ],
            'curriculum_id' => [
                'required',
                'integer',
                Rule::exists('curricula', 'id')->whereNull('deleted_at'),
            ],
            'fee_structure_id' => [
                'required',
                'integer',
                Rule::exists('fee_structures', 'id')->whereNull('deleted_at'),
                // Money guard: the same structure may not cover this
                // course, curriculum and year both as a year fee and per
                // session — the student would be charged twice. The row
                // itself is excluded, so switching its own span stays
                // possible.
                function (string $attribute, mixed $value, Closure $fail) use ($assignmentId): void {
                    $otherSpan = $this->input('span') === FeeSpan::Session->value
                        ? FeeSpan::Year->value
                        : FeeSpan::Session->value;

                    // Live rows only; the model's scope excludes soft deletes.
                    $clashes = CourseFeeAssignment::query()
                        ->where('id', '!=', $assignmentId)
                        ->where('course_id', $this->input('course_id'))
                        ->where('curriculum_id', $this->input('curriculum_id'))
                        ->where('year_no', $this->input('year_no'))
                        ->where('fee_structure_id', $this->input('fee_structure_id'))
                        ->where('span', $otherSpan)
                        ->exists();

                    if ($clashes) {
                        $fail(
                            $this->input('span') === FeeSpan::Session->value
                                ? 'This fee structure already charges this course, curriculum and year as a per-year fee; charging it per session as well would double-charge students.'
                                : 'This fee structure already charges this course, curriculum and year per session; charging it as a per-year fee as well would double-charge students.'
                        );
                    }
                },
            ],
            'span' => ['required', 'string', Rule::enum(FeeSpan::class)],
            'year_no' => [
                'required',
                'integer',
                'min:1',
                'max:255',
                Rule::unique('course_fee_assignments', 'year_no')
                    ->where('course_id', $this->input('course_id'))
                    ->where('curriculum_id', $this->input('curriculum_id'))
                    ->where('session_no', $this->input('session_no'))
                    ->whereNull('deleted_at')
                    ->ignore($assignmentId),
            ],
            'session_no' => [
                'nullable',
                'integer',
                'min:1',
                'max:255',
                'required_if:span,'.FeeSpan::Session->value,
            ],
        ];
    }
}
