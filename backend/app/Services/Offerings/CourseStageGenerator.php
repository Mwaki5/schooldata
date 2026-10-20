<?php

namespace App\Services\Offerings;

use App\Models\Course;
use App\Models\CourseStage;

/**
 * Builds (and tops up) the progression ladder for a course: one stage per
 * session of every academic year the course runs for.
 *
 * Generation is additive and idempotent. Shortening a course must never
 * delete a stage that already has offerings or registrations against it,
 * so the generator only inserts missing sequences.
 */
class CourseStageGenerator
{
    /**
     * Sessions (modules) in one academic year.
     */
    public const SESSIONS_PER_YEAR = 3;

    /**
     * Insert every missing stage for the course, returning how many rows
     * were added.
     */
    public function generate(Course $course): int
    {
        $totalModules = max(1, (int) ($course->total_modules ?? 3));
        $years = max(1, (int) ceil($totalModules / self::SESSIONS_PER_YEAR));
        $existing = $course->stages()->pluck('module_no')->all();
        $now = now();
        $rows = [];

        for ($year = 1; $year <= $years; $year++) {
            for ($sessionNo = 1; $sessionNo <= self::SESSIONS_PER_YEAR; $sessionNo++) {
                $moduleNo = (($year - 1) * self::SESSIONS_PER_YEAR) + $sessionNo;

                if (in_array($moduleNo, $existing, true)) {
                    continue;
                }

                $rows[] = [
                    'course_id' => $course->id,
                    'module_no' => $moduleNo,
                    'year_no' => $year,
                    'session_no' => $sessionNo,
                    'code' => "Y{$year}S{$sessionNo}",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            CourseStage::insert($rows);
        }

        return count($rows);
    }
}
