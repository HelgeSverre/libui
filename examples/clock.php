<?php

declare(strict_types=1);

/**
 * Animated canvas demo: a sweeping clock hand driven by a timer.
 *
 * A \Libui\Ffi::timer fires ~30x/sec, bumps the delegate's frame counter and
 * queues a redraw. The draw handler renders a clock face whose hand angle is a
 * function of that counter, so the hand sweeps continuously around the dial.
 *   php examples/clock.php
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
use Libui\Window;

Ffi::init();

$delegate = new class extends AreaDelegate {
    public ?Area $area = null;
    /** Animation clock, advanced one step per timer tick. */
    public int $frame = 0;

    public function draw(DrawContext $ctx, AreaDrawParams $p): void
    {
        $w = $p->areaWidth;
        $h = $p->areaHeight;

        // dark background
        $bg = new Path()->addRectangle(0, 0, $w, $h)->end();
        $ctx->fill($bg, Brush::rgb(0x0F172A));
        $bg->free();

        $cx = $w / 2;
        $cy = $h / 2;
        $radius = min($w, $h) * 0.40;

        // clock face
        $face = new Path()->newFigure($cx + $radius, $cy);
        for ($i = 1; $i <= 64; $i++) {
            $a = ($i / 64) * 2 * M_PI;
            $face->lineTo($cx + (cos($a) * $radius), $cy + (sin($a) * $radius));
        }
        $face->closeFigure()->end();
        $ctx->fill($face, Brush::rgb(0x1E293B));
        $ctx->stroke($face, Brush::rgb(0x38BDF8), StrokeParams::solid(3.0));
        $face->free();

        // hour ticks around the rim
        for ($i = 0; $i < 12; $i++) {
            $a = ($i / 12) * 2 * M_PI;
            $tick = new Path()
                ->newFigure($cx + (cos($a) * $radius * 0.86), $cy + (sin($a) * $radius * 0.86))
                ->lineTo($cx + (cos($a) * $radius * 0.96), $cy + (sin($a) * $radius * 0.96))
                ->end();
            $ctx->stroke($tick, Brush::rgb(0x64748B), StrokeParams::solid(2.0));
            $tick->free();
        }

        // sweeping hand — angle depends on the frame counter so it rotates.
        // -M_PI/2 puts angle 0 at the top (12 o'clock), then sweep clockwise.
        $angle = (($this->frame / 120) * 2 * M_PI) - (M_PI / 2);
        $hand = new Path()
            ->newFigure($cx, $cy)
            ->lineTo($cx + (cos($angle) * $radius * 0.78), $cy + (sin($angle) * $radius * 0.78))
            ->end();
        $ctx->stroke($hand, Brush::rgb(0xFACC15), StrokeParams::solid(4.0));
        $hand->free();

        // a shorter, faster second indicator for extra motion
        $angle2 = (($this->frame / 30) * 2 * M_PI) - (M_PI / 2);
        $hand2 = new Path()
            ->newFigure($cx, $cy)
            ->lineTo($cx + (cos($angle2) * $radius * 0.55), $cy + (sin($angle2) * $radius * 0.55))
            ->end();
        $ctx->stroke($hand2, Brush::rgb(0xF87171), StrokeParams::solid(2.0));
        $hand2->free();

        // center hub
        $hub = new Path()->addRectangle($cx - 4, $cy - 4, 8, 8)->end();
        $ctx->fill($hub, Brush::rgb(0xFFFFFF));
        $hub->free();
    }
};

$area = new Area($delegate);
$delegate->area = $area;

// drive the animation: advance the frame and repaint ~30x/sec.
Ffi::timer(33, function () use ($delegate, $area) {
    $delegate->frame++;
    $area->queueRedrawAll();
    return true;
});

$window = new Window('PHP libui — animated clock', 420, 420, false);
$box = new Box();
$box->append($area, 1); // stretchy: fill the window
$window->setChild($box);

$window->onClosing(function () {
    Ffi::quit();
    return true;
});

fwrite(STDOUT, "Opening animated clock… (close the window to exit)\n");
$area->queueRedrawAll();
$window->show();
Ffi::main();
Ffi::uninit();
