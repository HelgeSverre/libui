<?php

declare(strict_types=1);

namespace Libui\Draw;

use Libui\Ffi;
use Libui\Generated\Enum\DrawBrushType;

/**
 * A paint source for filling/stroking. Build one with a factory, then hand it
 * to DrawContext::fill()/stroke().
 *
 * The underlying uiDrawBrush struct (and, for gradients, its C stops array) is
 * built lazily in toCData() and retained on this object so the pointers stay
 * valid for the duration of the draw call.
 */
final class Brush
{
    private ?\FFI\CData $cdata = null;
    private ?\FFI\CData $stopsArray = null;

    /**
     * @param array<int, array{float,float,float,float,float}> $stops [pos,r,g,b,a]
     * @param array{float,float,float,float,float}             $gradient [x0,y0,x1,y1,outerRadius]
     */
    private function __construct(
        private readonly int $type,
        private readonly float $r = 0.0,
        private readonly float $g = 0.0,
        private readonly float $b = 0.0,
        private readonly float $a = 1.0,
        private readonly array $gradient = [],
        private readonly array $stops = [],
    ) {}

    public static function solid(float $r, float $g, float $b, float $a = 1.0): self
    {
        return new self(DrawBrushType::Solid->value, $r, $g, $b, $a);
    }

    /** Build a solid brush from a 0xRRGGBB integer. */
    public static function rgb(int $hex, float $a = 1.0): self
    {
        return self::solid((($hex >> 16) & 0xFF) / 255, (($hex >> 8) & 0xFF) / 255, ($hex & 0xFF) / 255, $a);
    }

    /** @param array<int, array{float,float,float,float,float}> $stops */
    public static function linearGradient(float $x0, float $y0, float $x1, float $y1, array $stops): self
    {
        return new self(DrawBrushType::LinearGradient->value, 0, 0, 0, 1, [$x0, $y0, $x1, $y1, 0.0], $stops);
    }

    /**
     * Radial gradient centred at ($cx, $cy) out to $radius. Stops are [pos,r,g,b,a].
     *
     * @param array<int, array{float,float,float,float,float}> $stops
     */
    public static function radialGradient(float $cx, float $cy, float $radius, array $stops): self
    {
        return new self(DrawBrushType::RadialGradient->value, 0, 0, 0, 1, [$cx, $cy, $cx, $cy, $radius], $stops);
    }

    public function toCData(): \FFI\CData
    {
        $ffi = Ffi::get();
        $brush = $ffi->new('uiDrawBrush');
        $brush->Type = $this->type;
        $brush->R = $this->r;
        $brush->G = $this->g;
        $brush->B = $this->b;
        $brush->A = $this->a;

        if ($this->gradient !== []) {
            [$x0, $y0, $x1, $y1, $outer] = $this->gradient;
            $brush->X0 = $x0;
            $brush->Y0 = $y0;
            $brush->X1 = $x1;
            $brush->Y1 = $y1;
            $brush->OuterRadius = $outer;
        }

        if ($this->stops !== []) {
            $n = \count($this->stops);
            $array = $ffi->new("uiDrawBrushGradientStop[$n]");
            foreach ($this->stops as $i => [$pos, $r, $g, $b, $a]) {
                $array[$i]->Pos = $pos;
                $array[$i]->R = $r;
                $array[$i]->G = $g;
                $array[$i]->B = $b;
                $array[$i]->A = $a;
            }
            $brush->Stops = \FFI::addr($array[0]);
            $brush->NumStops = $n;
            $this->stopsArray = $array; // keep the C array alive past addr()
        }

        $this->cdata = $brush; // keep the struct alive for the draw call
        return $brush;
    }
}
