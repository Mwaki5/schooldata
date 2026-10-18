<?php

namespace App\Enums;

/**
 * The lifecycle states a course enrolment can hold.
 */
enum EnrolmentStatus: string
{
    case Enrolled = 'ENROLLED';
    case Completed = 'COMPLETED';
    case Dropped = 'DROPPED';

    /**
     * Human-readable label for the enrolment status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Enrolled => 'Enrolled',
            self::Completed => 'Completed',
            self::Dropped => 'Dropped',
        };
    }
}
