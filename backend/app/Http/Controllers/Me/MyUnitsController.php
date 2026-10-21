<?php

namespace App\Http\Controllers\Me;

use App\Enums\UnitType;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\CourseUnit;
use App\Models\Curriculum;
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

        // One query for every course-unit link the student may see,
        // split by pair in memory. Only active links are offered, so
        // retired deliveries stay off the student's page.
        $linksByPair = CourseUnit::query()
            ->join('units', 'units.id', '=', 'course_units.unit_id')
            ->whereNull('units.deleted_at')
            ->where('course_units.is_active', true)
            ->whereIn('course_units.course_id', $pairs->pluck('course_id'))
            ->whereIn('course_units.curriculum_id', $pairs->pluck('curriculum_id'))
            ->select('course_units.*')
            ->with('unit')
            ->orderBy('units.code')
            ->orderBy('units.name')
            ->get()
            ->groupBy(fn (CourseUnit $link): string => $link->course_id.'-'.$link->curriculum_id);

        $groups = $pairs
            ->map(function (CourseEnrolment $enrolment) use ($courses, $curricula, $linksByPair): ?array {
                $course = $courses->get($enrolment->course_id);
                $curriculum = $curricula->get($enrolment->curriculum_id);

                if ($course === null || $curriculum === null) {
                    return null;
                }

                $links = $linksByPair->get($enrolment->course_id.'-'.$enrolment->curriculum_id, collect());

                // Inside the course + curriculum group, the links are
                // sectioned by the module number they are taught in;
                // links without a module come last.
                $modules = $links
                    ->groupBy(fn (CourseUnit $link): string => $link->modules_taught === null ? 'none' : (string) $link->modules_taught)
                    ->map(fn ($moduleLinks, string $key): array => [
                        'modules_taught' => $key === 'none' ? null : (int) $key,
                        'total' => $moduleLinks->count(),
                        'units' => $moduleLinks
                            ->map(fn (CourseUnit $link): array => [
                                'id' => $link->unit_id,
                                'code' => $link->unit->code,
                                'name' => $link->unit->name,
                                'description' => $link->unit->description,
                                'unit_type' => $link->unit_type->value,
                                'taught_hours' => $link->taught_hours,
                                'credit_factor' => $link->credit_factor,
                            ])
                            ->values(),
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
                        'total' => $links->count(),
                        'basic' => $links->where('unit_type', UnitType::Basic)->count(),
                        'core' => $links->where('unit_type', UnitType::Core)->count(),
                        'common' => $links->where('unit_type', UnitType::Common)->count(),
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
