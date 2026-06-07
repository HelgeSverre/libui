<?php

declare(strict_types=1);

/**
 * An HSV colour picker drawn entirely with the 2D API.
 *
 * The hue wheel is a fan of filled arc wedges (Path::arcTo) under a radial
 * white→transparent overlay for the saturation falloff; a vertical value bar is
 * a linear gradient. Click/drag the wheel to pick hue+saturation, the bar to
 * pick value. Click any of the format rows (HEX / RGB / HSL / HSV / OKLCH) to
 * copy that representation to the clipboard, or hit "Random colour".
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
use Libui\Utils\Clipboard;
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

/** sRGB (0-1) -> HSL [h 0-360, s 0-1, l 0-1]. */
function rgbToHsl(float $r, float $g, float $b): array
{
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l = ($max + $min) / 2;
    $d = $max - $min;
    if ($d == 0.0) {
        return [0.0, 0.0, $l];
    }
    $s = $d / (1 - abs((2 * $l) - 1));
    $h =
        match ($max) {
            $r => fmod(($g - $b) / $d, 6.0),
            $g => (($b - $r) / $d) + 2,
            default => (($r - $g) / $d) + 4,
        } * 60;
    return [fmod($h + 360.0, 360.0), $s, $l];
}

/** sRGB (0-1) -> OKLCH [L 0-1, C, H 0-360]. */
function rgbToOklch(float $r, float $g, float $b): array
{
    $lin = static fn (float $c): float => $c <= 0.040_45 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    $lr = $lin($r);
    $lg = $lin($g);
    $lb = $lin($b);

    $l = (0.412_221_470_8 * $lr) + (0.536_332_536_3 * $lg) + (0.051_445_992_9 * $lb);
    $m = (0.211_903_498_2 * $lr) + (0.680_699_545_1 * $lg) + (0.107_396_956_6 * $lb);
    $s = (0.088_302_461_9 * $lr) + (0.281_718_837_6 * $lg) + (0.629_978_700_5 * $lb);
    $l3 = $l ** (1 / 3);
    $m3 = $m ** (1 / 3);
    $s3 = $s ** (1 / 3);

    $L = (0.210_454_255_3 * $l3) + (0.793_617_785_0 * $m3) - (0.004_072_046_8 * $s3);
    $a = (1.977_998_495_1 * $l3) - (2.428_592_205_0 * $m3) + (0.450_593_709_9 * $s3);
    $bb = (0.025_904_037_1 * $l3) + (0.782_771_766_2 * $m3) - (0.808_675_766_0 * $s3);

    $C = sqrt(($a * $a) + ($bb * $bb));
    $H = fmod(rad2deg(atan2($bb, $a)) + 360.0, 360.0);
    return [$L, $C, $H];
}

$picker = new class extends AreaDelegate {
    public ?Area $area = null;
    public float $hue = 190.0;
    public float $sat = 0.72;
    public float $val = 0.92;
    public string $copied = ''; // label of the format last copied (flash)

    // wheel
    private float $cx = 190.0;
    private float $cy = 222.0;
    private float $radius = 165.0;
    // value bar
    private float $barX = 392.0;
    private float $barY = 70.0;
    private float $barW = 26.0;
    private float $barH = 300.0;
    // readout panel
    private float $px = 470.0;
    private float $panelW = 286.0;
    private float $rowY0 = 162.0;
    private float $rowH = 34.0;
    private float $btnY = 350.0;
    private float $btnH = 34.0;

    /** @return list<array{string,string}> ordered [label, value] for the current colour */
    public function formats(): array
    {
        [$r, $g, $b] = hsv($this->hue, $this->sat, $this->val);
        $ri = (int) round($r * 255);
        $gi = (int) round($g * 255);
        $bi = (int) round($b * 255);
        [$hl, $sl, $ll] = rgbToHsl($r, $g, $b);
        [$okl, $okc, $okh] = rgbToOklch($r, $g, $b);

        return [
            ['HEX', sprintf('#%02X%02X%02X', $ri, $gi, $bi)],
            ['RGB', "rgb({$ri}, {$gi}, {$bi})"],
            ['HSL', sprintf('hsl(%d, %d%%, %d%%)', round($hl), round($sl * 100), round($ll * 100))],
            ['HSV', sprintf('hsv(%d, %d%%, %d%%)', round($this->hue), round($this->sat * 100), round($this->val * 100))],
            ['OKLCH', sprintf('oklch(%.3f %.3f %.1f)', $okl, $okc, $okh)],
        ];
    }

    public function draw(DrawContext $ctx, AreaDrawParams $p): void
    {
        $w = $p->areaWidth;
        $h = $p->areaHeight;
        $ctx->fillPath(Brush::rgb(0x10_12_1A), static fn (Path $bg) => $bg->addRectangle(0, 0, $w, $h));

        // hue wheel: a fan of solid-hue wedges
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
        // saturation: white centre -> transparent rim
        $ctx->fillPath(
            Brush::radialGradient($this->cx, $this->cy, $this->radius, [[0.0, 1, 1, 1, 1.0], [1.0, 1, 1, 1, 0.0]]),
            fn (Path $disc) => $disc->newFigureWithArc($this->cx, $this->cy, $this->radius, 0, 2 * \M_PI),
        );
        // selection marker
        $ang = ($this->hue / 360.0) * 2 * \M_PI;
        $mx = $this->cx + (cos($ang) * $this->sat * $this->radius);
        $my = $this->cy + (sin($ang) * $this->sat * $this->radius);
        $ctx->strokePath(Brush::rgb(0xFF_FFFF), StrokeParams::solid(2.5), static fn (Path $mk) => $mk->newFigureWithArc($mx, $my, 7, 0, 2 * \M_PI));

        // value bar
        [$fr, $fg, $fb] = hsv($this->hue, $this->sat, 1.0);
        $ctx->fillPath(
            Brush::linearGradient($this->barX, $this->barY, $this->barX, $this->barY + $this->barH, [
                [0.0, $fr, $fg, $fb, 1.0],
                [1.0, 0,   0,   0,   1.0],
            ]),
            fn (Path $bar) => $bar->addRectangle($this->barX, $this->barY, $this->barW, $this->barH),
        );
        $vy = $this->barY + ((1 - $this->val) * $this->barH);
        $ctx->fillPath(Brush::rgb(0xFF_FFFF), fn (Path $vm) => $vm->addRectangle($this->barX - 4, $vy - 1.5, $this->barW + 8, 3));

        // --- readout panel ---
        [$r, $g, $b] = hsv($this->hue, $this->sat, $this->val);
        $ctx->fillPath(Brush::solid($r, $g, $b), fn (Path $sw) => $sw->addRectangle($this->px, 56, $this->panelW, 78));
        $ctx->drawString('CLICK A VALUE TO COPY', new FontDescriptor(FONT, 11.0), [0.45, 0.5, 0.58], $this->px, 144);

        $labelFont = new FontDescriptor(FONT, 11.0, TextWeight::Bold);
        $valueFont = new FontDescriptor(MONO, 14.0);
        foreach ($this->formats() as $i => [$label, $value]) {
            $y = $this->rowY0 + ($i * $this->rowH);
            $isCopied = $this->copied === $label;
            $ctx->fillPath(
                Brush::rgb($isCopied ? 0x13_3A_2E : 0x19_1C_26),
                fn (Path $row) => $row->addRectangle($this->px, $y, $this->panelW, $this->rowH - 6),
            );
            $ctx->drawString($label, $labelFont, $isCopied ? [0.45, 0.85, 0.55] : [0.5, 0.55, 0.63], $this->px + 10, $y + 5);
            $ctx->drawString(
                $isCopied ? '✓ Copied' : $value,
                $valueFont,
                $isCopied ? [0.55, 0.9, 0.62] : [0.85, 0.88, 0.93],
                $this->px + 62,
                $y + 4,
            );
        }

        // random button
        $ctx->fillPath(Brush::rgb(0x2A_30_40), fn (Path $btn) => $btn->addRectangle($this->px, $this->btnY, $this->panelW, $this->btnH));
        $ctx->drawString('🎲  Random colour', new FontDescriptor(FONT, 14.0, TextWeight::Bold), [0.9, 0.93, 0.98], $this->px + 14, $this->btnY + 8);
    }

    public function mouse(AreaMouseEvent $e): void
    {
        $down = $e->down !== 0;
        $dragging = $down || $e->held !== 0;

        // wheel (hue + saturation) — responds to drag
        $dx = $e->x - $this->cx;
        $dy = $e->y - $this->cy;
        $dist = sqrt(($dx * $dx) + ($dy * $dy));
        if ($dragging && $dist <= ($this->radius + 6)) {
            $this->hue = fmod(rad2deg(atan2($dy, $dx)) + 360.0, 360.0);
            $this->sat = min(1.0, $dist / $this->radius);
            $this->copied = '';
            $this->area?->queueRedrawAll();
            return;
        }
        // value bar — responds to drag
        if ($dragging && $e->x >= ($this->barX - 6) && $e->x <= ($this->barX + $this->barW + 6)) {
            $this->val = max(0.0, min(1.0, 1 - (($e->y - $this->barY) / $this->barH)));
            $this->copied = '';
            $this->area?->queueRedrawAll();
            return;
        }

        // panel interactions only fire on a click, not a drag
        if (! $down || $e->x < $this->px || $e->x > ($this->px + $this->panelW)) {
            return;
        }

        // format rows -> copy
        $formats = $this->formats();
        foreach ($formats as $i => [$label, $value]) {
            $y = $this->rowY0 + ($i * $this->rowH);
            if ($e->y >= $y && $e->y <= ($y + $this->rowH - 6)) {
                Clipboard::copy($value);
                $this->copied = $label;
                $this->area?->queueRedrawAll();
                return;
            }
        }
        // random button
        if ($e->y >= $this->btnY && $e->y <= ($this->btnY + $this->btnH)) {
            $this->hue = mt_rand(0, 3599) / 10;
            $this->sat = mt_rand(45, 100) / 100;
            $this->val = mt_rand(55, 100) / 100;
            $this->copied = '';
            $this->area?->queueRedrawAll();
        }
    }
};

Ffi::init();

$area = new Area($picker);
$picker->area = $area;

new Window('Colour picker', 780, 440)
    ->setChild(new Box()->appendStretchy($area))
    ->run();
