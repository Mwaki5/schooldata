<?php

namespace App\Http\Requests\AcademicYears;

use App\Models\AcademicYear;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAcademicYearRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('academic_years', 'name')->ignore($this->route('academicYear')->id),
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Shrinking the year below sessions that already exist would leave
     * them outside the academic year's dates, breaking the invariant the
     * session rules enforce — so the new range must still contain every
     * existing session.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                return;
            }

            $start = $this->input('start_date') === null
                ? null
                : Carbon::parse($this->input('start_date'))->startOfDay();
            $end = $this->input('end_date') === null
                ? null
                : Carbon::parse($this->input('end_date'))->startOfDay();

            if ($start === null || $end === null) {
                return; // Dates stay optional on years; only real ranges are checked.
            }

            /** @var AcademicYear $academicYear */
            $academicYear = $this->route('academicYear');

            $tooEarly = $academicYear->sessions()
                ->whereNotNull('start_date')
                ->where('start_date', '<', $start->toDateString())
                ->get();

            $tooLate = $academicYear->sessions()
                ->whereNotNull('end_date')
                ->where('end_date', '>', $end->toDateString())
                ->get();

            if ($tooEarly->isNotEmpty()) {
                $validator->errors()->add(
                    'start_date',
                    'The year cannot start after existing sessions: "'.$tooEarly->pluck('name')->implode('", "').'" already begin before it.',
                );
            }

            if ($tooLate->isNotEmpty()) {
                $validator->errors()->add(
                    'end_date',
                    'The year cannot end before existing sessions: "'.$tooLate->pluck('name')->implode('", "').'" already end after it.',
                );
            }
        }];
    }
}
