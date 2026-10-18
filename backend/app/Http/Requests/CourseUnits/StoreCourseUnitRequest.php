<?php

namespace App\Http\Requests\CourseUnits;

use App\Models\CourseUnit;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCourseUnitRequest extends FormRequest
{
    /**
     * The largest number of course × curriculum combinations a single
     * batch assignment may create.
     */
    private const MAX_BATCH = 50;

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
     * Accepts a batch so one unit can be assigned to several courses
     * and curricula in a single, transactional request. Each entry
     * carries its own teaching load; the unit's global code travels
     * with the master record, not per delivery.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assignments' => ['required', 'array', 'min:1', 'max:'.self::MAX_BATCH],
            'assignments.*' => ['required', 'array'],
            'assignments.*.course_id' => [
                'required',
                'integer',
                Rule::exists('courses', 'id')->whereNull('deleted_at'),
            ],
            'assignments.*.curriculum_id' => [
                'required',
                'integer',
                Rule::exists('curricula', 'id')->whereNull('deleted_at'),
                // One delivery of this unit per course + curriculum. The
                // database unique index is the final safeguard; this rule
                // answers with a field-level message instead of a 500.
                function (string $attribute, mixed $value, Closure $fail): void {
                    $courseId = $this->rowInput($attribute, 'course_id');

                    if ($courseId === null || ! is_numeric($value)) {
                        return;
                    }

                    $duplicate = CourseUnit::query()
                        ->where('unit_id', $this->route('unit')->id)
                        ->where('course_id', (int) $courseId)
                        ->where('curriculum_id', (int) $value)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($duplicate) {
                        $fail('This unit is already assigned to that course under the selected curriculum.');
                    }
                },
            ],
            'assignments.*.core' => ['nullable', 'boolean'],
            'assignments.*.time_offered' => ['nullable', 'string', 'max:100'],
            'assignments.*.taught_hours' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'assignments.*.credit_factor' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'assignments.*.is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Batch-wide checks that need every row in hand: the same course +
     * curriculum combination twice in one request would pass the
     * per-row rules above (neither row exists in the database yet).
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $seen = [];

                foreach ($this->input('assignments', []) as $index => $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $courseId = $row['course_id'] ?? null;
                    $curriculumId = $row['curriculum_id'] ?? null;

                    if ($courseId === null || $curriculumId === null) {
                        continue;
                    }

                    $key = $courseId.'-'.$curriculumId;

                    if (isset($seen[$key])) {
                        $validator->errors()->add(
                            'assignments',
                            'Rows '.($seen[$key] + 1).' and '.((int) $index + 1).' assign this unit to the same course and curriculum. Remove one of them.',
                        );

                        return;
                    }

                    $seen[$key] = (int) $index;
                }
            },
        ];
    }

    /**
     * Resolve one field of the same batch row as `$attribute`.
     *
     * `$this->input('assignments.*.course_id')` would expand to every
     * row, so the concrete index is spliced back in per rule.
     */
    private function rowInput(string $attribute, string $field): ?int
    {
        if (! preg_match('/^assignments\.(\d+)\./', $attribute, $matches)) {
            return null;
        }

        $value = $this->input("assignments.{$matches[1]}.$field");

        return is_numeric($value) ? (int) $value : null;
    }
}
