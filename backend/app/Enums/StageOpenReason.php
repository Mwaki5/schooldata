<?php

namespace App\Enums;

/**
 * Why a stage is (or is not) accepting registrations in a session.
 *
 * The reason travels with the boolean so the UI can distinguish "never
 * offered" from "offered but withdrawn" and from a window that has not
 * opened or has already closed.
 */
enum StageOpenReason: string
{
    case Open = 'OPEN';
    case NoOffering = 'NO_OFFERING';
    case Closed = 'CLOSED';
    case NoRegistrationWindow = 'NO_REGISTRATION_WINDOW';
    case NotYetOpen = 'NOT_YET_OPEN';
    case WindowEnded = 'WINDOW_ENDED';

    /**
     * The student-facing explanation for a stage evaluation, shared by
     * the dashboard and the reporting endpoint so both always agree.
     */
    public function message(string $stageCode): string
    {
        return match ($this) {
            self::Open => "{$stageCode} is open for this session.",
            self::NoOffering, self::Closed => "{$stageCode} is not open for this session.",
            self::NoRegistrationWindow => 'Session registration is not open for this term.',
            self::NotYetOpen => 'Registration for this session has not opened yet.',
            self::WindowEnded => 'Registration for this session has closed.',
        };
    }
}
