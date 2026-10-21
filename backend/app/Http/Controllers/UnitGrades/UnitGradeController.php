<?php

namespace App\Http\Controllers\UnitGrades;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnitGrades\SaveUnitGradesRequest;
use App\Models\AcademicSession;
use App\Models\CourseUnit;
use App\Models\StudentProfile;
use App\Models\UnitGrade;
use App\Models\UnitRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnitGradeController extends Controller
{
    /**
     * The units grading can act on for one academic session: every unit
     * at least one student registered for it, searchable by code or
     * name. The session defaults to the running one, so the console's
     * first two picks — session, then unit — resolve from this alone.
     */
    public function units(Request $request): JsonResponse
    {
        $data = $request->validate([
            'academic_session_id' => ['sometimes', 'integer', 'exists:academic_sessions,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $session = $this->resolveSession($data);

        if ($session === null) {
            return response()->json(['session' => null, 'units' => []]);
        }

        $registeredIds = UnitRegistration::query()
            ->where('academic_session_id', $session->id)
            ->distinct()
            ->pluck('course_unit_id');

        $search = trim($data['search'] ?? '');

        $units = CourseUnit::query()
            ->join('units', 'units.id', '=', 'course_units.unit_id')
            ->whereNull('units.deleted_at')
            ->whereIn('course_units.id', $registeredIds)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('units.code', 'like', "%{$search}%")
                        ->orWhere('units.name', 'like', "%{$search}%");
                });
            })
            ->select('course_units.id')
            ->addSelect('units.code', 'units.name')
            ->orderBy('units.code')
            ->orderBy('units.name')
            ->limit(50)
            ->get()
            ->map(fn (CourseUnit $link): array => [
                'course_unit_id' => $link->id,
                'code' => $link->code,
                'name' => $link->name,
            ])
            ->values();

        return response()->json([
            'session' => $this->sessionPayload($session),
            'units' => $units,
        ]);
    }

    /**
     * The students who registered one course unit in the session: the
     * only students grading that unit may act on, searchable by name or
     * admission number like the students list.
     */
    public function students(Request $request): JsonResponse
    {
        $data = $request->validate([
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'course_unit_id' => ['required', 'integer', 'exists:course_units,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim($data['search'] ?? '');

        $students = UnitRegistration::query()
            ->where('academic_session_id', $data['academic_session_id'])
            ->where('course_unit_id', $data['course_unit_id'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->whereHas('student', function (Builder $studentQuery) use ($search): void {
                        $studentQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('login_id', 'like', "%{$search}%"));
                    });
                });
            })
            ->with('student.user:id,login_id')
            ->limit(50)
            ->get()
            ->sortBy(fn (UnitRegistration $registration): string => $registration->student?->last_name ?? '')
            ->map(fn (UnitRegistration $registration): ?array => $this->studentPayload($registration->student))
            ->filter()
            ->values();

        return response()->json(['students' => $students]);
    }

    /**
     * The grading payload for one session, course unit and student:
     * their recorded marks with progress, ready for the single-paper
     * entry form. Marks exist only for units the student registered —
     * the console's pickers keep that true before a save ever runs.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'academic_session_id' => ['sometimes', 'integer', 'exists:academic_sessions,id'],
            'course_unit_id' => ['required', 'integer', 'exists:course_units,id'],
            'student_id' => ['required', 'integer', 'exists:student_profiles,id'],
        ]);

        $session = $this->resolveSession($data);

        $student = StudentProfile::query()
            ->with('user:id,login_id')
            ->findOrFail($data['student_id']);

        $courseUnit = CourseUnit::query()
            ->with('unit:id,code,name')
            ->findOrFail($data['course_unit_id']);

        $grades = $session === null
            ? collect()
            : UnitGrade::query()
                ->where('academic_session_id', $session->id)
                ->where('student_id', $student->id)
                ->where('course_unit_id', $courseUnit->id)
                ->orderBy('exam_type')
                ->orderBy('exam_no')
                ->get(['id', 'course_unit_id', 'exam_type', 'exam_no', 'mark'])
                ->map(fn (UnitGrade $grade): array => $this->gradePayload($grade))
                ->values();

        $theory = $grades->where('exam_type', 'theory')->count();
        $practical = $grades->where('exam_type', 'practical')->count();

        return response()->json([
            'session' => $session === null ? null : $this->sessionPayload($session),
            'student' => $this->studentPayload($student),
            'unit' => [
                'course_unit_id' => $courseUnit->id,
                'code' => $courseUnit->unit?->code,
                'name' => $courseUnit->unit?->name,
                'theory_count' => $theory,
                'practical_count' => $practical,
                'fully_graded' => $theory >= 3 && $practical >= 3,
            ],
            'grades' => $grades,
        ]);
    }

    /**
     * Save the grading slots for one student, course unit and session.
     *
     * The payload is the full form state: slots left out are removed, so
     * clearing a mark is as easy as recording one. The course unit must
     * be one the student registered for in the session.
     */
    public function save(SaveUnitGradesRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $registered = UnitRegistration::query()
            ->where('academic_session_id', $data['academic_session_id'])
            ->where('student_id', $data['student_id'])
            ->where('course_unit_id', $data['course_unit_id'])
            ->exists();

        if (! $registered) {
            throw ValidationException::withMessages([
                'course_unit_id' => 'The student has not registered this unit for the selected session.',
            ]);
        }

        $grades = DB::transaction(function () use ($data, $user): array {
            UnitGrade::query()
                ->where('academic_session_id', $data['academic_session_id'])
                ->where('student_id', $data['student_id'])
                ->where('course_unit_id', $data['course_unit_id'])
                ->delete();

            return collect($data['marks'])
                ->map(fn (array $mark): array => $this->gradePayload(UnitGrade::query()->create([
                    'academic_session_id' => $data['academic_session_id'],
                    'student_id' => $data['student_id'],
                    'course_unit_id' => $data['course_unit_id'],
                    'exam_type' => $mark['exam_type'],
                    'exam_no' => $mark['exam_no'],
                    'mark' => $mark['mark'],
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ])))
                ->values()
                ->all();
        });

        return response()->json(['grades' => collect($grades)]);
    }

    /**
     * The session to grade for: the one asked for, else the running one.
     */
    private function resolveSession(array $data): ?AcademicSession
    {
        return isset($data['academic_session_id'])
            ? AcademicSession::query()->find($data['academic_session_id'])
            : AcademicSession::query()->where('is_active', true)->latest('start_date')->first();
    }

    /**
     * The API shape of one student, as both pickers and the console
     * header render it.
     */
    private function studentPayload(?StudentProfile $student): ?array
    {
        return $student === null
            ? null
            : [
                'id' => $student->id,
                'name' => User::composeName($student->first_name, $student->middle_name, $student->last_name),
                'admission_no' => $student->user?->login_id,
            ];
    }

    /**
     * The session segment every payload renders with.
     *
     * @return array{id: int, name: string, is_active: bool}
     */
    private function sessionPayload(AcademicSession $session): array
    {
        return [
            'id' => $session->id,
            'name' => $session->name,
            'is_active' => $session->is_active,
        ];
    }

    /**
     * The API shape of one recorded mark.
     */
    private function gradePayload(UnitGrade $grade): array
    {
        return [
            'id' => $grade->id,
            'course_unit_id' => $grade->course_unit_id,
            'exam_type' => $grade->exam_type->value,
            'exam_no' => $grade->exam_no,
            'mark' => (float) $grade->mark,
        ];
    }
}
