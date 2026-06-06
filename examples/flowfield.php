<?php

declare(strict_types=1);

/**
 * Generative flow field — streamlines traced through an animated vector field,
 * stroked with a gradient palette. Pure 2D drawing on an Area, animated by a
 * timer that slowly evolves the field.
 *
 *   php examples/flowfield.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\Area;
use Libui\AreaDelegate;
use Libui\Box;
use Libui\Draw\Brush;
use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\Path;
use Libui\Draw\StrokeParams;
use Libui\Ffi;
use Libui\Generated\Enum\DrawLineCap;
use Libui\Window;

Ffi::init();

$field = new class extends AreaDelegate {
    public ?Area $area = null;
    public float $t = 0.0;

    /** A smooth, organic flow angle at (x, y), evolving with time. */
    private function angle(float $x, float $y, float $t): float
    {
        $s = 0.0042;
        return (sin(($x * $s) + $t) + cos(($y * $s) - ($t * 0.7)) + sin((($x + $y) * $s * 0.6) + ($t * 1.3))) * 1.7;
    }

    public function draw(DrawContext $ctx, AreaDrawParams $p): void
    {
        $w = $p->areaWidth;
        $h = $p->areaHeight;

        // near-black background
        $ctx->fillPath(Brush::rgb(0x05_07_0F), fn (Path $bg) => $bg->addRectangle(0, 0, $w, $h));

        $cols = 20;
        $rows = 14;
        $steps = 22;
        $len = 8.0;

        for ($iy = 0; $iy < $rows; $iy++) {
            for ($ix = 0; $ix < $cols; $ix++) {
                // seed point on a jittered grid
                $x = ((($ix + 0.5) / $cols) * $w) + (sin(($iy * 1.3) + $this->t) * 7);
                $y = ((($iy + 0.5) / $rows) * $h) + (cos(($ix * 1.7) + $this->t) * 7);

                // colour: lerp indigo -> cyan across the field
                $m = (($ix / $cols) + ($iy / $rows)) / 2;
                $r = 0.31 + ((0.13 - 0.31) * $m);
                $g = 0.27 + ((0.83 - 0.27) * $m);
                $b = 0.90 + ((0.93 - 0.90) * $m);

                $stroke = StrokeParams::solid(1.3);
                $stroke->cap = DrawLineCap::Round;

                $ctx->strokePath(Brush::solid($r, $g, $b, 0.55), $stroke, function (Path $path) use ($x, $y, $w, $h, $steps, $len): void {
                    $px = $x;
                    $py = $y;
                    $path->newFigure($px, $py);
                    for ($k = 0; $k < $steps; $k++) {
                        $a = $this->angle($px, $py, $this->t);
                        $px += cos($a) * $len;
                        $py += sin($a) * $len;
                        if ($px < 0 || $px > $w || $py < 0 || $py > $h) {
                            break;
                        }
                        $path->lineTo($px, $py);
                    }
                });
            }
        }
    }
};

$area = new Area($field);
$field->area = $area;

Ffi::timer(40, function () use ($field): bool {
    $field->t += 0.045;
    $field->area->queueRedrawAll();
    return true;
});

new Window('Flow field', 760, 520)
    ->setChild(new Box()->appendStretchy($area))
    ->run();
