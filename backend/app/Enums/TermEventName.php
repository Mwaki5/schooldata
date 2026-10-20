<?php

namespace App\Enums;

/**
 * The calendar events a term can carry.
 *
 * The vocabulary is closed: term events are picked from these five entries
 * rather than typed freely, so reports and the student dashboard can rely
 * on the names never drifting.
 */
enum TermEventName: string
{
    case SessionRegistration = 'Session Registration';
    case Graduation = 'Graduation';
    case MidtermExam = 'Midterm Exam';
    case EndTermExams = 'End Term Exams';
    case SchoolBreak = 'School Break';

    /**
     * The event names as plain strings, for validation and pickers.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $event): string => $event->value, self::cases());
    }
}
