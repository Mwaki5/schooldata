<?php

namespace App\Http\Requests\AcademicSessions\Concerns;

use App\Models\AcademicSession;
use App\Models\AcademicYear;
use Carbon\Carbon;
use Illuminate\Validation\Validator;

trait ValidatesSessionScheduling
{
    /**
     * Scheduling rules shared by the store / update requests: a session
     * must sit inside its academic year's dates and must never touch or
     * overlap another session of the same year. Dates are inclusive, so
     * sharing a boundary day counts as a collision.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->validateSessionScheduling($validator);
        }];
    }

    /**
     * Cross-field scheduling checks that plain validation rules cannot
     * express (they need the owning year and the sibling sessions).
     */
    private function validateSessionScheduling(Validator $validator): void
    {
        // The base rules own missing / malformed / reversed dates.
        if ($validator->errors()->hasAny(['academic_year_id', 'start_date', 'end_date'])) {
            return;
        }

        $year = AcademicYear::find($this->input('academic_year_id'));

        if ($year === null) {
            return; // The exists rule will already have failed.
        }

        if ($year->start_date === null || $year->end_date === null) {
            $validator->errors()->add(
                'academic_year_id',
                'The academic year must have a start and end date before sessions can be scheduled under it.',
            );

            return;
        }

        $start = Carbon::parse($this->input('start_date'))->startOfDay();
        $end = Carbon::parse($this->input('end_date'))->startOfDay();
        $yearStart = Carbon::parse($year->start_date)->startOfDay();
        $yearEnd = Carbon::parse($year->end_date)->startOfDay();

        if ($start->lt($yearStart)) {
            $validator->errors()->add(
                'start_date',
                'The session cannot start before the academic year begins ('.$yearStart->toDateString().').',
            );
        }

        if ($end->gt($yearEnd)) {
            $validator->errors()->add(
                'end_date',
                'The session cannot end after the academic year ends ('.$yearEnd->toDateString().').',
            );
        }

        /** @var AcademicSession|null $current */
        $current = $this->route('academicSession');

        $collision = AcademicSession::query()
            ->where('academic_year_id', $year->id)
            ->when($current !== null, fn ($query) => $query->whereKeyNot($current->id))
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->first();

        if ($collision !== null) {
            $validator->errors()->add(
                'start_date',
                'The session overlaps "'.$collision->name.'" ('.$collision->start_date->toDateString().' – '.$collision->end_date->toDateString().') in the same academic year.',
            );
        }
    }
}
