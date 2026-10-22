<?php

namespace App\Http\Controllers\Me;

use App\Enums\UnitExamType;
use App\Http\Controllers\Controller;
use App\Models\CourseUnit;
use App\Models\StudentProfile;
use App\Models\UnitGrade;
use App\Models\UnitRegistration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MyGradesController extends Controller
{
    /**
     * The signed-in student's own marks: one row per registered unit that
     * carries at least one recorded mark — the marksheet's "no blank
     * rows" rule applied to their own papers. Theory and practical slots
     * are laid out per exam number with each type's average, exactly
     * like the class sheet. Filters narrow by unit code/name and by the
     * module the unit is taught in.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'module' => ['sometimes', 'integer', 'min:1'],
        ]);

        $profile = $request->user()->studentProfile()->first();

        if ($profile === null) {
            return response()->json([
                'rows' => [],
                'modules' => [],
                'meta' => $this->meta(0),
            ]);
        }

        $registrations = $this->registrationsQuery($profile, $data)->get();

        // Every paper the student has ever sat, keyed by session + course
        // unit so each registration slots only its own marks.
        $grades = UnitGrade::query()
            ->where('student_id', $profile->id)
            ->get(['academic_session_id', 'course_unit_id', 'exam_type', 'exam_no', 'mark'])
            ->groupBy(fn (UnitGrade $grade): string => $this->registrationKey($grade->academic_session_id, $grade->course_unit_id));

        $rows = $registrations
            ->map(fn (UnitRegistration $registration): ?array => $this->rowFor($registration, $grades))
            ->filter()
            // Latest session first; the stable sorts make the unit code
            // the tiebreaker within one session.
            ->sortBy(fn (array $row): string => $row['unit']['code'] ?? '')
            ->sortByDesc(fn (array $row): int => $row['session']['id'])
            ->values();

        return response()->json([
            'rows' => $rows,
            'modules' => $this->modules($profile),
            'meta' => $this->meta($rows->count()),
        ]);
    }

    /**
     * The student's unit registrations, optionally narrowed to a unit
     * code/name search or to the module a unit is taught in.
     *
     * @param  array{search?: string, module?: int}  $data
     * @return Builder<UnitRegistration>
     */
    private function registrationsQuery(StudentProfile $profile, array $data): Builder
    {
        $query = UnitRegistration::query()
            ->where('student_id', $profile->id)
            ->with([
                'academicSession:id,name',
                'courseUnit:id,unit_id,course_id,modules_taught,unit_type',
                'courseUnit.unit:id,code,name',
                'courseUnit.course:id,name',
            ])
            ->orderByDesc('academic_session_id')
            ->orderBy('id');

        $search = trim((string) ($data['search'] ?? ''));

        if ($search !== '') {
            $query->whereHas('courseUnit.unit', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            });
        }

        if (isset($data['module'])) {
            $query->whereHas('courseUnit', function (Builder $query) use ($data): void {
                $query->where('modules_taught', (int) $data['module']);
            });
        }

        return $query;
    }

    /**
     * The distinct module numbers among the student's registered units,
     * for the module filter. Always computed from every registration, so
     * the select keeps its options while the rows are filtered down.
     *
     * @return array<int, int>
     */
    private function modules(StudentProfile $profile): array
    {
        return UnitRegistration::query()
            ->where('student_id', $profile->id)
            ->join('course_units', 'course_units.id', '=', 'unit_registrations.course_unit_id')
            ->pluck('course_units.modules_taught')
            ->filter(fn ($module): bool => $module !== null)
            ->map(fn ($module): int => (int) $module)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * One registration's row, or null when the unit carries no marks
     * yet — a registered-but-ungraded unit is not on the student's
     * sheet.
     *
     * @param  Collection<string, Collection<int, UnitGrade>>  $grades
     * @return array<string, mixed>|null
     */
    private function rowFor(UnitRegistration $registration, Collection $grades): ?array
    {
        $papers = $grades->get($this->registrationKey($registration->academic_session_id, $registration->course_unit_id));

        if ($papers === null) {
            return null;
        }

        $theory = [null, null, null];
        $practical = [null, null, null];

        foreach ($papers as $grade) {
            $index = $grade->exam_no - 1;

            if ($grade->exam_type === UnitExamType::Theory) {
                $theory[$index] = (float) $grade->mark;
            } else {
                $practical[$index] = (float) $grade->mark;
            }
        }

        /** @var CourseUnit|null $courseUnit */
        $courseUnit = $registration->courseUnit;

        return [
            'registration_id' => $registration->id,
            'session' => [
                'id' => $registration->academic_session_id,
                'name' => $registration->academicSession?->name,
            ],
            'unit' => [
                'id' => $courseUnit?->unit_id,
                'code' => $courseUnit?->unit?->code,
                'name' => $courseUnit?->unit?->name,
            ],
            'course' => $courseUnit?->course?->name,
            'modules_taught' => $courseUnit?->modules_taught,
            'unit_type' => $courseUnit?->unit_type?->value,
            'theory' => $theory,
            'theory_average' => $this->slotAverage($theory),
            'practical' => $practical,
            'practical_average' => $this->slotAverage($practical),
        ];
    }

    /**
     * The grouping key that pairs a registration with its papers: the
     * session and course unit every grade row repeats.
     */
    private function registrationKey(int $academicSessionId, int $courseUnitId): string
    {
        return sprintf('%d|%d', $academicSessionId, $courseUnitId);
    }

    /**
     * The list footer block: one page holding every row, so the shared
     * pagination component can render its summary.
     *
     * @return array{current_page: int, last_page: int, per_page: int, total: int}
     */
    private function meta(int $total): array
    {
        return [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => max($total, 1),
            'total' => $total,
        ];
    }

    /**
     * The average across the recorded slots of one paper type, or null
     * when none have been marked yet.
     *
     * @param  array<int, float|null>  $slots
     */
    private function slotAverage(array $slots): ?float
    {
        $recorded = array_filter($slots, fn (?float $mark): bool => $mark !== null);

        return count($recorded) === 0 ? null : round(array_sum($recorded) / count($recorded), 2);
    }
}
