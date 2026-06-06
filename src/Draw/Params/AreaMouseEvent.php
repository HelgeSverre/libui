<?php

declare(strict_types=1);

namespace Libui\Draw\Params;

/** A PHP view of uiAreaMouseEvent. */
final class AreaMouseEvent
{
    public function __construct(
        public readonly float $x,
        public readonly float $y,
        public readonly float $areaWidth,
        public readonly float $areaHeight,
        public readonly int $down, // button pressed this event (0 = none)
        public readonly int $up, // button released this event (0 = none)
        public readonly int $count, // click count (1 = single, 2 = double)
        public readonly int $modifiers, // bitmask, see Generated\Flags\Modifiers
        public readonly int $held, // bitmask of buttons 1..64 currently held
    ) {}

    public static function fromCData(\FFI\CData $e): self
    {
        return new self(
            $e->X,
            $e->Y,
            $e->AreaWidth,
            $e->AreaHeight,
            $e->Down,
            $e->Up,
            $e->Count,
            $e->Modifiers,
            $e->Held1To64,
        );
    }
}
