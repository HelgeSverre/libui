<?php

declare(strict_types=1);

/**
 * An HSV colour picker drawn entirely with the 2D API.
 *
 * The hue wheel is a fan of filled arc wedges (Path::arcTo) under a radial
 * white→transparent overlay for the saturation falloff; a vertical value bar is
 * a linear gradient. Click/drag the wheel to pick hue+saturation, the bar to
 * pick value. The swatch, hex and RGB readouts update live.
 *
 *   php examples/colorpicker.php
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
use Libui\Generated\Enum\TextWeight;
use Libui\Text\FontDescriptor;
use Libui\Window;

const FONT = 'Helvetica Neue';

const MONO = 'Menlo';

/** HSV (h 0-360, s/v 0-1) -> [r, g, b] each 0-1. */
function hsv(float $h, float $s, float $v): array
{
    $h = fmod($h, 360.0);
    if ($h < 0) {
        $h += 360.0;
    }
    $c = $v * $s;
    $x = $c * (1 - abs(fmod($h / 60.0, 2.0) - 1));
    $m = $v - $c;
    [$r, $g, $b] = match (true) {
        $h < 60 => [$c, $x, 0.0],
        $h < 120 => [$x, $c, 0.0],
        $h < 180 => [0.0, $c, $x],
        $h < 240 => [0.0, $x, $c],
        $h < 300 => [$x, 0.0, $c],
        default => [$c, 0.0, $x],
    };
    return [$r + $m, $g + $m, $b + $m];
}

$picker = new class extends AreaDelegate {
    public ?Area $area = null;
    public float $hue = 190.0;
    public float $sat = 0.72;
    public float $val = 0.92;

    // wheel geometry
    private float $cx = 190.0;
    private float $cy = 222.0;
    private float $radius = 165.0;
    // value bar
    private float $barX = 392.0;
    private float $barY = 70.0;
    private float $barW = 26.0;
    private float $barH = 300.0;

    public function draw(DrawContext $ctx, AreaDrawParams $p): void
    {
        $w = $p->areaWidth;
        $h = $p->areaHeight;
        $ctx->fillPath(Brush::rgb(0x10_12_18), fn (Path $bg) => $bg->addRectangle(0, 0, $w, $h));

        // --- hue wheel: a fan of solid-hue wedges ---
        $segments = 72;
        $step = (2 * \M_PI) / $segments;
        for ($i = 0; $i < $segments; $i++) {
            $a0 = $i * $step;
            [$r, $g, $b] = hsv(($i / $segments) * 360.0, 1.0, 1.0);
            $ctx->fillPath(Brush::solid($r, $g, $b), function (Path $path) use ($a0, $step): void {
                $path->newFigure($this->cx, $this->cy);
                $path->arcTo($this->cx, $this->cy, $this->radius, $a0, $step + 0.02);
                $path->closeFigure();
            });
        }
        // saturation: white at the centre fading to transparent at the rim
        $ctx->fillPath(
            Brush::radialGradient($this->cx, $this->cy, $this->radius, [[0.0, 1, 1, 1, 1.0], [1.0, 1, 1, 1, 0.0]]),
            fn (Path $disc) => $disc->newFigureWithArc($this->cx, $this->cy, $this->radius, 0, 2 * \M_PI),
        );
        // selection marker on the wheel
        $ang = ($this->hue / 360.0) * 2 * \M_PI;
        $mx = $this->cx + (cos($ang) * $this->sat * $this->radius);
        $my = $this->cy + (sin($ang) * $this->sat * $this->radius);
        $ring = StrokeParams::solid(2.5);
        $ctx->strokePath(Brush::rgb(0xFFFFFF), $ring, fn (Path $mk) => $mk->newFigureWithArc($mx, $my, 7, 0, 2 * \M_PI));

        // --- value bar: full colour (top) to black (bottom) ---
        [$fr, $fg, $fb] = hsv($this->hue, $this->sat, 1.0);
        $ctx->fillPath(
            Brush::linearGradient($this->barX, $this->barY, $this->barX, $this->barY + $this->barH, [
                [0.0, $fr, $fg, $fb, 1.0],
                [1.0, 0,   0,   0,   1.0],
            ]),
            fn (Path $bar) => $bar->addRectangle($this->barX, $this->barY, $this->barW, $this->barH),
        );
        $vy = $this->barY + ((1 - $this->val) * $this->barH);
        $ctx->fillPath(Brush::rgb(0xFFFFFF), fn (Path $vm) => $vm->addRectangle($this->barX - 4, $vy - 1.5, $this->barW + 8, 3));

        // --- readout panel ---
        [$r, $g, $b] = hsv($this->hue, $this->sat, $this->val);
        $px = 460.0;
        $ctx->fillPath(Brush::solid($r, $g, $b), fn (Path $sw) => $sw->addRectangle($px, 70, 200, 110));

        $hex = sprintf('#%02X%02X%02X', (int) round($r * 255), (int) round($g * 255), (int) round($b * 255));
        $ctx->drawString($hex, new FontDescriptor(MONO, 30.0, TextWeight::Bold), [0.95, 0.96, 0.98], $px, 200);

        $label = new FontDescriptor(FONT, 13.0);
        $value = new FontDescriptor(MONO, 14.0);
        $rows = [
            ['R', (string) (int) round($r * 255)],
            ['G', (string) (int) round($g * 255)],
            ['B', (string) (int) round($b * 255)],
            ['H', round($this->hue) . '°'],
            ['S', round($this->sat * 100) . '%'],
            ['V', round($this->val * 100) . '%'],
        ];
        $ry = 252.0;
        foreach ($rows as [$k, $v]) {
            $ctx->drawString($k, $label, [0.5, 0.55, 0.63], $px, $ry);
            $ctx->drawString($v, $value, [0.82, 0.86, 0.92], $px + 30, $ry - 1);
            $ry += 26;
        }
    }

    public function mouse(AreaMouseEvent $e): void
    {
        $active = $e->down !== 0 || $e->held !== 0;
        if (! $active) {
            return;
        }

        $dx = $e->x - $this->cx;
        $dy = $e->y - $this->cy;
        $dist = sqrt(($dx * $dx) + ($dy * $dy));
        if ($dist <= ($this->radius + 6)) {
            $this->hue = fmod(rad2deg(atan2($dy, $dx)) + 360.0, 360.0);
            $this->sat = min(1.0, $dist / $this->radius);
            $this->area?->queueRedrawAll();
            return;
        }
        if ($e->x >= ($this->barX - 6) && $e->x <= ($this->barX + $this->barW + 6)) {
            $this->val = max(0.0, min(1.0, 1 - (($e->y - $this->barY) / $this->barH)));
            $this->area?->queueRedrawAll();
        }
    }
};

Ffi::init();

$area = new Area($picker);
$picker->area = $area;

new Window('Colour picker', 700, 440)
    ->setChild(new Box()->appendStretchy($area))
    ->run();
