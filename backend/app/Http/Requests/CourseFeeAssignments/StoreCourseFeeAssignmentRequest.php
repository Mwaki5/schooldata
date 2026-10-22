<?php

namespace App\Http\Requests\CourseFeeAssignments;

use App\Enums\FeeSpan;
use App\Models\CourseFeeAssignment;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseFeeAssignmentRequest extends FormRequest
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
     * A course fee assignment names the fee structure one course +
     * curriculum pair charges in a given year of study — and, when it
     * charges per session, which session of that year. The slot is what
     * an enrolment maps onto, so it may charge under exactly one live
     * assignment.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
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
                // session — the student would be charged twice.
                function (string $attribute, mixed $value, Closure $fail): void {
                    $otherSpan = $this->input('span') === FeeSpan::Session->value
                        ? FeeSpan::Year->value
                        : FeeSpan::Session->value;

                    // Live rows only; the model's scope excludes soft deletes.
                    $clashes = CourseFeeAssignment::query()
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
                    ->whereNull('deleted_at'),
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
