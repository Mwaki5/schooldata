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
}
