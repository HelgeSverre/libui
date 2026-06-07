<?php

declare(strict_types=1);

namespace Libui\Draw;

use Libui\Ffi;
use Libui\Generated\Enum\DrawLineCap;
use Libui\Generated\Enum\DrawLineJoin;

/** Stroke styling for DrawContext::stroke(). */
final class StrokeParams
{
    public float $thickness = 1.0;
    public DrawLineCap $cap = DrawLineCap::Flat;
    public DrawLineJoin $join = DrawLineJoin::Miter;
    public float $miterLimit = 10.0;
    /** @var float[] dash on/off lengths; empty = solid line */
    public array $dashes = [];
    public float $dashPhase = 0.0;

    private ?\FFI\CData $cdata = null;
    private ?\FFI\CData $dashArray = null;

    /**
     * @param float[] $dashes Dash on/off lengths; empty = solid line
     */
    public function __construct(
        float $thickness = 1.0,
        DrawLineCap $cap = DrawLineCap::Flat,
        DrawLineJoin $join = DrawLineJoin::Miter,
        float $miterLimit = 10.0,
        array $dashes = [],
        float $dashPhase = 0.0,
    ) {
        $this->thickness = $thickness;
        $this->cap = $cap;
        $this->join = $join;
        $this->miterLimit = $miterLimit;
        $this->dashes = $dashes;
        $this->dashPhase = $dashPhase;
    }

    public static function solid(float $thickness): self
    {
        return new self(thickness: $thickness);
    }

    public function toCData(): \FFI\CData
    {
        $ffi = Ffi::get();
        $sp = $ffi->new('uiDrawStrokeParams'); // zero-initialised by FFI
        $sp->Cap = $this->cap->value;
        $sp->Join = $this->join->value;
        $sp->Thickness = $this->thickness;
        $sp->MiterLimit = $this->miterLimit;
        $sp->DashPhase = $this->dashPhase;

        if ($this->dashes !== []) {
            $n = \count($this->dashes);
            $array = $ffi->new("double[$n]");
            foreach ($this->dashes as $i => $d) {
                $array[$i] = $d;
            }
            $sp->Dashes = \FFI::addr($array[0]);
            $sp->NumDashes = $n;
            $this->dashArray = $array; // keep alive past addr()
        }

        $this->cdata = $sp;
        return \FFI::addr($sp);
    }
}
