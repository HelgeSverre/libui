<?php

declare(strict_types=1);

/**
 * Custom 2D drawing via the hand-written Area/Draw adapter.
 *
 * Draws a gradient-filled mountain range; click-drag paints yellow dots.
 *   php examples/canvas.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\Area;
use Libui\AreaDelegate;
use Libui\Box;
use Libui\Draw\Brush;
use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\Params\AreaMouseEvent;
use Libui\Draw\Path;
use Libui\Draw\StrokeParams;
use Libui\Ffi;
use Libui\Window;

Ffi::init();

$delegate = new class extends AreaDelegate {
    public ?Area $area = null;
    /** @var array<int, array{float,float}> */
    public array $dots = [];

    public function draw(DrawContext $ctx, AreaDrawParams $p): void
    {
        $w = $p->areaWidth;
        $h = $p->areaHeight;

        // dark background
        $bg = new Path()->addRectangle(0, 0, $w, $h)->end();
        $ctx->fill($bg, Brush::rgb(0x0F172A));
        $bg->free();

        // a gradient-filled mountain silhouette
        $poly = new Path();
        $poly
            ->newFigure(0, $h * 0.80)
            ->lineTo($w * 0.20, $h * 0.45)
            ->lineTo($w * 0.40, $h * 0.62)
            ->lineTo($w * 0.60, $h * 0.30)
            ->lineTo($w * 0.80, $h * 0.55)
            ->lineTo($w, $h * 0.38)
            ->lineTo($w, $h)
            ->lineTo(0, $h)
            ->closeFigure()
            ->end();
        $ctx->fill($poly, Brush::linearGradient(0, 0, 0, $h, [
            [0.0, 0.31, 0.27, 0.90, 1.0], // indigo
            [1.0, 0.02, 0.71, 0.83, 1.0], // cyan
        ]));
        $ctx->stroke($poly, Brush::rgb(0xFFFFFF), StrokeParams::solid(2.0));
        $poly->free();

        // mouse-trail dots
        foreach ($this->dots as [$x, $y]) {
            $dot = new Path()->addRectangle($x - 3, $y - 3, 6, 6)->end();
            $ctx->fill($dot, Brush::rgb(0xFACC15));
            $dot->free();
        }
    }

    public function mouse(AreaMouseEvent $e): void
    {
        if ($e->down !== 0 || $e->held !== 0) {
            $this->dots[] = [$e->x, $e->y];
            $this->area?->queueRedrawAll();
        }
    }
};

$area = new Area($delegate);
$delegate->area = $area;

$window = new Window('PHP libui — custom canvas', 520, 360, false);
$box = new Box();
$box->append($area, 1); // stretchy: fill the window
$window->setChild($box);

$window->onClosing(function () {
    Ffi::quit();
    return true;
});

fwrite(STDOUT, "Opening canvas… (click-drag to paint, close to exit)\n");
$area->queueRedrawAll();
$window->show();
Ffi::main();
Ffi::uninit();
