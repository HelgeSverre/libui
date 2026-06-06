<?php

declare(strict_types=1);

namespace Libui\Draw\Params;

/** Dimensions passed to an area's draw handler (a PHP view of uiAreaDrawParams). */
final class AreaDrawParams
{
    public function __construct(
        public readonly float $areaWidth,
        public readonly float $areaHeight,
        public readonly float $clipX,
        public readonly float $clipY,
        public readonly float $clipWidth,
        public readonly float $clipHeight,
    ) {}

    public static function fromCData(\FFI\CData $p): self
    {
        return new self(
            $p->AreaWidth, $p->AreaHeight,
            $p->ClipX, $p->ClipY, $p->ClipWidth, $p->ClipHeight,
        );
    }
}
