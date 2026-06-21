<?php

declare(strict_types=1);

/**
 * Live system-monitor dashboard — one Area, repainted ~2x/sec by a timer.
 *
 * Reads load averages via sys_getloadavg() and renders:
 *   - a CPU-load line chart (filled area + line) over a ring buffer of samples,
 *     auto-scaled on Y, with the current value labelled in the text layer;
 *   - three mini line charts for the 1- / 5- / 15-minute load averages;
 *   - a semicircle gauge showing current load as a fraction of the core count
 *     (the arc is approximated with straight segments — see the papercut note);
 *   - a dark theme with flat panel backgrounds, a title, and labels.
 *
 * If sys_getloadavg() is unavailable, smooth fake data is synthesised so the
 * dashboard always animates.
 *
 *   php examples/monitor.php
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
use Libui\Generated\Enum\DrawLineJoin;
use Libui\Generated\Enum\DrawTextAlign;
use Libui\Generated\Enum\TextWeight;
use Libui\Text\Attribute;
use Libui\Text\AttributedString;
use Libui\Text\FontDescriptor;
use Libui\Text\TextLayout;
use Libui\Window;

Ffi::init();

// Number of logical CPUs (macOS). Used to scale the load gauge to "fully busy".
$ncpu = (static function (): int {
    $out = @shell_exec('sysctl -n hw.ncpu');
    $n = is_string($out) ? (int) trim($out) : 0;
    return $n > 0 ? $n : 8;
})();

/** Dashboard palette (0xRRGGBB). */
const BG = 0x0B_10_1A; // window background

const PANEL = 0x12_1B_2B; // panel fill

const PANEL_EDGE = 0x22_30_46; // panel border

const GRID = 0x1C_28_3D; // chart gridlines

const INK = 0xE5_ED_F7; // primary text

const MUTED = 0x6B_7C_96; // secondary text

const ACCENT = 0x38_BD_F8; // cyan — primary series

const ACCENT2 = 0xA7_8B_FA; // violet

const WARN = 0xFB_BF_24; // amber

const DANGER = 0xF8_71_71; // red

$dash = new class($ncpu) extends AreaDelegate {
    public ?Area $area = null;

    /** Ring buffer of recent 1-min load samples for the main chart. */
    private const HISTORY = 120;

    /** @var float[] */
    public array $cpu = [];

    /** @var float[][] history for the three mini charts: [1m, 5m, 15m] */
    public array $mini = [[], [], []];

    /** Current snapshot of the three load averages. */
    public array $load = [0.0, 0.0, 0.0];

    /** Phase for synthesising fake data when sys_getloadavg() is missing. */
    private float $fakePhase = 0.0;

    public function __construct(
        public int $ncpu,
    ) {}

    /** Sample the system (or fake it) and push into the ring buffers. */
    public function sample(): void
    {
        if (function_exists('sys_getloadavg') && ($la = @sys_getloadavg()) !== false) {
            $this->load = [(float) $la[0], (float) $la[1], (float) $la[2]];
        } else {
            // Smooth, always-moving synthetic load so the demo animates anywhere.
            $this->fakePhase += 0.20;
            $base = $this->ncpu * 0.45;
            $this->load = [
                max(0.0, $base + (sin($this->fakePhase) * $base * 0.7) + (sin($this->fakePhase * 2.7) * $base * 0.2)),
                max(0.0, $base + (sin(($this->fakePhase * 0.6) - 1) * $base * 0.5)),
                max(0.0, $base + (sin(($this->fakePhase * 0.3) - 2) * $base * 0.35)),
            ];
        }

        $this->push($this->cpu, $this->load[0], self::HISTORY);
        for ($i = 0; $i < 3; $i++) {
            $this->push($this->mini[$i], $this->load[$i], self::HISTORY);
        }
    }

    /** @param float[] $buf */
    private function push(array &$buf, float $v, int $cap): void
    {
        $buf[] = $v;
        if (count($buf) > $cap) {
            array_shift($buf);
        }
    }

    public function draw(DrawContext $ctx, AreaDrawParams $p): void
    {
        $w = $p->areaWidth;
        $h = $p->areaHeight;

        // Background.
        $ctx->fillPath(Brush::rgb(BG), static fn (Path $bg) => $bg->addRectangle(0, 0, $w, $h));

        $pad = 18.0;
        $headerH = 56.0;

        // ---- Header -------------------------------------------------------
        $this->label($ctx, 'SYSTEM MONITOR', $pad, 14, 20.0, INK, TextWeight::Bold);
        $this->label(
            $ctx,
            sprintf('%d cores  ·  live load average  ·  2 Hz', $this->ncpu),
            $pad,
            38,
            12.0,
            MUTED,
        );
        // Current 1-min load, right-aligned, colour-coded by saturation.
        $cur = $this->load[0];
        $sat = $this->ncpu > 0 ? $cur / $this->ncpu : 0.0;
        $curColor = $sat >= 1.0 ? DANGER : ($sat >= 0.6 ? WARN : ACCENT);
        $this->label($ctx, sprintf('%.2f', $cur), $w - $pad - 110, 10, 28.0, $curColor, TextWeight::Bold, 110, DrawTextAlign::Right);
        $this->label($ctx, 'load (1m)', $w - $pad - 110, 42, 11.0, MUTED, TextWeight::Normal, 110, DrawTextAlign::Right);

        // ---- Layout: main chart (left), gauge (right) --------------------
        $top = $headerH + $pad;
        $gaugeW = min(260.0, $w * 0.34);
        $colGap = $pad;
        $mainX = $pad;
        $mainW = $w - $gaugeW - $colGap - ($pad * 2);
        $miniH = 78.0;
        $miniGap = 12.0;
        $miniBlockH = ($miniH * 3) + ($miniGap * 2);
        $mainH = $h - $top - $pad - $miniBlockH - $pad;
        if ($mainH < 80) {
            $mainH = max(80.0, ($h - $top - $pad) * 0.5);
        }

        // Main CPU-load chart panel.
        $this->panel($ctx, $mainX, $top, $mainW, $mainH);
        $this->lineChart(
            $ctx,
            $this->cpu,
            $mainX + 12,
            $top + 30,
            $mainW - 24,
            $mainH - 44,
            ACCENT,
            'CPU LOAD (1 min)',
            true,
        );

        // Gauge panel (right column, top).
        $gaugeX = $mainX + $mainW + $colGap;
        $gaugeH = $mainH;
        $this->panel($ctx, $gaugeX, $top, $gaugeW, $gaugeH);
        $this->gauge($ctx, $gaugeX, $top, $gaugeW, $gaugeH, $cur, $curColor);

        // ---- Three mini charts across the bottom -------------------------
        $miniTop = $top + $mainH + $pad;
        $miniW = $w - ($pad * 2);
        $labels = ['1 MIN', '5 MIN', '15 MIN'];
        $colors = [ACCENT, ACCENT2, WARN];
        for ($i = 0; $i < 3; $i++) {
            $y = $miniTop + ($i * ($miniH + $miniGap));
            $this->panel($ctx, $pad, $y, $miniW, $miniH);
            $this->lineChart(
                $ctx,
                $this->mini[$i],
                $pad + 12,
                $y + 24,
                $miniW - 24,
                $miniH - 36,
                $colors[$i],
                $labels[$i] . sprintf('   %.2f', $this->load[$i]),
                false,
            );
        }
    }

    // ---- Drawing helpers -------------------------------------------------

    /** Flat panel background with a subtle border. */
    private function panel(DrawContext $ctx, float $x, float $y, float $w, float $h): void
    {
        $ctx->fillPath(Brush::rgb(PANEL), static fn (Path $p) => $p->addRectangle($x, $y, $w, $h));
        $stroke = StrokeParams::solid(1.0)->join(DrawLineJoin::Round);
        $ctx->strokePath(Brush::rgb(PANEL_EDGE), $stroke, static fn (Path $p) => $p
            ->newFigure($x + 0.5, $y + 0.5)
            ->lineTo($x + $w - 0.5, $y + 0.5)
            ->lineTo($x + $w - 0.5, $y + $h - 0.5)
            ->lineTo($x + 0.5, $y + $h - 0.5)
            ->closeFigure());
    }

    /**
     * Filled-area + line chart of $data inside the given rect.
     * Y is auto-scaled to the data's max (with a small headroom).
     */
    private function lineChart(
        DrawContext $ctx,
        array $data,
        float $x,
        float $y,
        float $w,
        float $h,
        int $color,
        string $title,
        bool $gridlines,
    ): void {
        $this->label($ctx, $title, $x, $y - 18, 11.0, MUTED, TextWeight::Medium);

        $n = count($data);
        $max = 0.0;
        foreach ($data as $v) {
            $max = max($max, $v);
        }
        $max = max($max * 1.15, 0.5); // headroom + floor so a flat zero still has a baseline

        // Horizontal gridlines + scale labels for the big chart.
        if ($gridlines) {
            $stroke = StrokeParams::solid(1.0);
            for ($g = 0; $g <= 3; $g++) {
                $gy = $y + $h - (($g / 3) * $h);
                $ctx->strokePath(Brush::rgb(GRID), $stroke, static fn (Path $p) => $p
                    ->newFigure($x, $gy)->lineTo($x + $w, $gy));
                $this->label($ctx, sprintf('%.1f', ($g / 3) * $max), $x + $w - 34, $gy - 13, 9.0, MUTED, TextWeight::Normal, 34, DrawTextAlign::Right);
            }
        }

        if ($n < 2) {
            return; // need at least two points to draw a line
        }

        $step = $w / ($n - 1);
        $px = static fn (int $i): float => $x + ($i * $step);
        $py = static fn (float $v): float => $y + $h - (($v / $max) * $h);

        // Filled area under the curve (semi-transparent series colour).
        $ctx->fillPath(Brush::rgb($color, 0.16), static function (Path $p) use ($data, $n, $px, $py, $x, $y, $h): void {
            $p->newFigure($x, $y + $h);
            for ($i = 0; $i < $n; $i++) {
                $p->lineTo($px($i), $py($data[$i]));
            }
            $p->lineTo($px($n - 1), $y + $h);
            $p->closeFigure();
        });

        // The line itself.
        $line = StrokeParams::solid(2.0);
        $line->cap = DrawLineCap::Round;
        $line->join = DrawLineJoin::Round;
        $ctx->strokePath(Brush::rgb($color), $line, static function (Path $p) use ($data, $n, $px, $py): void {
            $p->newFigure($px(0), $py($data[0]));
            for ($i = 1; $i < $n; $i++) {
                $p->lineTo($px($i), $py($data[$i]));
            }
        });

        // Marker dot on the most recent sample.
        $lx = $px($n - 1);
        $ly = $py($data[$n - 1]);
        $ctx->fillPath(Brush::rgb($color), static fn (Path $p) => $p->addRectangle($lx - 2.5, $ly - 2.5, 5, 5));
    }

    /**
     * Semicircle gauge: current load as a fraction of the core count.
     *
     * NOTE: the Path wrapper has no arc primitive, so the arc is built from
     * straight lineTo() segments computed around the circle (see report).
     */
    private function gauge(DrawContext $ctx, float $x, float $y, float $w, float $h, float $value, int $color): void
    {
        $this->label($ctx, 'LOAD vs CORES', $x + 12, $y + 12, 11.0, MUTED, TextWeight::Medium);

        $cx = $x + ($w / 2);
        $cy = $y + ($h * 0.72); // pivot low so the semicircle fits the panel
        $radius = min($w * 0.40, $h * 0.42);
        $thickness = max(10.0, $radius * 0.22);
        $start = M_PI; // 180° — left
        $end = 2 * M_PI; // 360° — right (top semicircle)

        // Background track.
        $this->arc($ctx, $cx, $cy, $radius, $start, $end, Brush::rgb(GRID), $thickness);

        // Filled portion proportional to load/ncpu, clamped to one full ring.
        $frac = $this->ncpu > 0 ? max(0.0, min(1.0, $value / $this->ncpu)) : 0.0;
        if ($frac > 0.0) {
            $this->arc($ctx, $cx, $cy, $radius, $start, $start + (($end - $start) * $frac), Brush::rgb($color), $thickness);
        }

        // Tick marks at 0, 0.5, 1.0 of capacity.
        $tick = StrokeParams::solid(1.5);
        foreach ([0.0, 0.5, 1.0] as $f) {
            $a = $start + (($end - $start) * $f);
            $r0 = $radius + ($thickness / 2) + 2;
            $r1 = $radius + ($thickness / 2) + 8;
            $ctx->strokePath(Brush::rgb(MUTED), $tick, static fn (Path $p) => $p
                ->newFigure($cx + (cos($a) * $r0), $cy + (sin($a) * $r0))
                ->lineTo($cx + (cos($a) * $r1), $cy + (sin($a) * $r1)));
        }

        // Centre readout: percent of capacity + raw load.
        $this->label($ctx, sprintf('%d%%', (int) round($frac * 100)), $cx - 60, $cy - 34, 26.0, INK, TextWeight::Bold, 120, DrawTextAlign::Center);
        $this->label($ctx, sprintf('%.2f / %d', $value, $this->ncpu), $cx - 60, $cy - 4, 12.0, MUTED, TextWeight::Normal, 120, DrawTextAlign::Center);
    }

    /** Draw a thick, round-capped stroked arc from $start to $end (radians). */
    private function arc(
        DrawContext $ctx,
        float $cx,
        float $cy,
        float $radius,
        float $start,
        float $end,
        Brush $brush,
        float $thickness,
    ): void {
        $stroke = StrokeParams::solid($thickness)
            ->cap(DrawLineCap::Round)
            ->join(DrawLineJoin::Round);
        $ctx->strokePath(
            $brush,
            $stroke,
            static fn (Path $p) => $p->arc($cx, $cy, $radius, $start, $end - $start, false),
        );
    }

    /**
     * Draw a single line of text. Builds a one-shot AttributedString +
     * FontDescriptor + TextLayout, draws it, and frees the layout.
     */
    private function label(
        DrawContext $ctx,
        string $text,
        float $x,
        float $y,
        float $size,
        int $color,
        TextWeight $weight = TextWeight::Normal,
        float $width = 1.0e6,
        DrawTextAlign $align = DrawTextAlign::Left,
    ): void {
        $r = (($color >> 16) & 0xFF) / 255;
        $g = (($color >> 8) & 0xFF) / 255;
        $b = ($color & 0xFF) / 255;

        $str = new AttributedString();
        $str->append(
            $text,
            Attribute::color($r, $g, $b),
            Attribute::weight($weight),
            Attribute::size($size),
        );

        $font = new FontDescriptor('Helvetica', $size, $weight);
        $layout = new TextLayout($str, $font, $width, $align);
        $ctx->text($layout, $x, $y);
        $layout->free();
    }
};

$area = new Area($dash);
$dash->area = $area;

// Seed a little history so the first frame already shows a trend.
for ($i = 0; $i < 8; $i++) {
    $dash->sample();
}

// Sample + repaint twice a second.
Ffi::timer(500, function () use ($dash, $area): bool {
    $dash->sample();
    $area->queueRedrawAll();
    return true;
});

new Window('PHP libui — system monitor', 880, 600)
    ->setChild(new Box()->appendStretchy($area))
    ->run();
