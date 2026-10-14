<?php

declare(strict_types=1);

namespace Libui\Draw\Params;

/** A PHP view of uiAreaMouseScrollEvent. */
final class AreaScrollEvent
{
    public function __construct(
        public readonly float $x,
        public readonly float $y,
        public readonly float $areaWidth,
        public readonly float $areaHeight,
        // Scroll amount in wheel "steps": one notch = +/-1.0 (fractional for
        // trackpads). Positive deltaY scrolls towards the content end (wheel
        // rolled down); positive deltaX towards the right.
        public readonly float $deltaX,
        public readonly float $deltaY,
        public readonly int $modifiers, // bitmask, see Generated\Flags\Modifiers
    ) {}

    public static function fromCData(\FFI\CData $e): self
    {
        return new self(
            $e->X,
            $e->Y,
            $e->AreaWidth,
            $e->AreaHeight,
            $e->DeltaX,
            $e->DeltaY,
            $e->Modifiers,
        );
    }
}
