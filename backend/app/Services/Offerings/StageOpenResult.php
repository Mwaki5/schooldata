<?php

namespace App\Services\Offerings;

use App\Enums\StageOpenReason;
use App\Models\SessionStageOffering;

/**
 * The single answer every registration path receives from the offering
 * service: whether the stage is open and why.
 */
final readonly class StageOpenResult
{
    public function __construct(
        public bool $open,
        public StageOpenReason $reason,
        public ?SessionStageOffering $offering = null,
    ) {}

    public static function allowed(?SessionStageOffering $offering = null): self
    {
        return new self(true, StageOpenReason::Open, $offering);
    }

    public static function denied(StageOpenReason $reason, ?SessionStageOffering $offering = null): self
    {
        return new self(false, $reason, $offering);
    }
}
