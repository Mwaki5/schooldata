<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\Units\UnitResource;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\Curriculum;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyUnitsController extends Controller
{
    /**
     * Every unit offered by the courses the signed-in student is enrolled
     * in, grouped by course + curriculum so the page can render one
     * section per enrolment.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Queried through the relation rather than as a magic property so
        // lazy-loading prevention never trips on the auth user.
        $profile = $request->user()->studentProfile()->first();

        if ($profile === null) {
            return response()->json(['groups' => []]);
        }

        // One group per course + curriculum pair; re-enrolments in the
        // same pair collapse into a single group.
        $pairs = CourseEnrolment::query()
            ->where('student_id', $profile->id)
            ->get(['course_id', 'curriculum_id'])
            ->unique(fn (CourseEnrolment $enrolment): string => $enrolment->course_id.'-'.$enrolment->curriculum_id)
            ->values();

        $courses = Course::query()
            ->whereIn('id', $pairs->pluck('course_id'))
            ->get(['id', 'initials', 'name'])
            ->keyBy('id');

        $curricula = Curriculum::query()
            ->whereIn('id', $pairs->pluck('curriculum_id'))
            ->get(['id', 'name'])
            ->keyBy('id');

        // One query for every unit the student may see, split by pair in
        // memory. Only active units are offered, so retired ones stay off
        // the student's page.
        $unitsByPair = Unit::query()
            ->where('is_active', true)
            ->whereIn('course_id', $pairs->pluck('course_id'))
            ->whereIn('curriculum_id', $pairs->pluck('curriculum_id'))
            ->orderBy('code')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Unit $unit): string => $unit->course_id.'-'.$unit->curriculum_id);

        $groups = $pairs
            ->map(function (CourseEnrolment $enrolment) use ($courses, $curricula, $unitsByPair): ?array {
                $course = $courses->get($enrolment->course_id);
                $curriculum = $curricula->get($enrolment->curriculum_id);

                if ($course === null || $curriculum === null) {
                    return null;
                }

                $units = $unitsByPair->get($enrolment->course_id.'-'.$enrolment->curriculum_id, collect());

                // Inside the course + curriculum group, the units are
                // sectioned by the module number they are taught in;
                // units without a module come last.
                $modules = $units
                    ->groupBy(fn (Unit $unit): string => $unit->modules_taught === null ? 'none' : (string) $unit->modules_taught)
                    ->map(fn ($moduleUnits, string $key): array => [
                        'modules_taught' => $key === 'none' ? null : (int) $key,
                        'total' => $moduleUnits->count(),
                        'units' => UnitResource::collection($moduleUnits->values()),
                    ])
                    ->sortBy(fn (array $module): int => $module['modules_taught'] ?? PHP_INT_MAX)
                    ->values();

                return [
                    'course' => [
                        'id' => $course->id,
                        'initials' => $course->initials,
                        'name' => $course->name,
                    ],
                    'curriculum' => [
                        'id' => $curriculum->id,
                        'name' => $curriculum->name,
                    ],
                    'stats' => [
                        'total' => $units->count(),
                        'core' => $units->where('core', true)->count(),
                        'elective' => $units->where('core', false)->count(),
                    ],
                    'modules' => $modules,
                ];
            })
            ->filter()
            ->sortBy(fn (array $group): string => strtolower($group['course']['name']))
            ->values();

        return response()->json(['groups' => $groups]);
    }
}
