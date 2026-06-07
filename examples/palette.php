<?php

declare(strict_types=1);

/**
 * A Raycast-style command palette: a borderless window that is entirely one
 * custom-drawn Area — the search input and the result list are painted by hand,
 * and every keystroke (type to fuzzy-filter, ↑/↓ to move, enter to run, esc to
 * dismiss) is handled in the Area's KeyEvent.
 *
 * (libui's Entry doesn't surface arrow keys and selection can't move across
 * widgets, so a single full-window Area is the honest way to build this.)
 *
 *   php examples/palette.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\Area;
use Libui\AreaDelegate;
use Libui\Box;
use Libui\Draw\Brush;
use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\Params\AreaKeyEvent;
use Libui\Draw\Path;
use Libui\Ffi;
use Libui\Generated\Enum\ExtKey;
use Libui\Generated\Enum\TextWeight;
use Libui\Text\FontDescriptor;
use Libui\Window;

const FONT = 'Helvetica Neue';

/** Case-insensitive subsequence fuzzy match. */
function fuzzy(string $q, string $s): bool
{
    if ($q === '') {
        return true;
    }
    $q = strtolower($q);
    $s = strtolower($s);
    $i = 0;
    $n = strlen($q);
    foreach (str_split($s) as $ch) {
        if (! ($i < $n && $ch === $q[$i])) {
            continue;
        }

        $i++;
    }
    return $i === $n;
}

$palette = new class extends AreaDelegate {
    public ?Area $area = null;
    public string $query = '';
    public int $selected = 0;

    /** @var list<array{string,string,string}> icon, name, category */
    public array $commands = [
        ['🔍', 'Search Files',      'Navigation'],
        ['⚙️', 'Open Settings',     'System'],
        ['🎨', 'Toggle Theme',      'Appearance'],
        ['📋', 'Clipboard History', 'Productivity'],
        ['🪟', 'New Window',        'Window'],
        ['🔄', 'Reload Project',    'System'],
        ['🌐', 'Open in Browser',   'Web'],
        ['📁', 'Reveal in Finder',  'Files'],
        ['🔑', 'Generate Password', 'Security'],
        ['🧮', 'Calculator',        'Tools'],
        ['⏻',  'Quit',              'System'],
    ];

    /** @return list<array{string,string,string}> */
    private function filtered(): array
    {
        return array_values(array_filter($this->commands, fn (array $c) => fuzzy($this->query, $c[1])));
    }

    public function draw(DrawContext $ctx, AreaDrawParams $p): void
    {
        $w = $p->areaWidth;
        $h = $p->areaHeight;

        $ctx->fillPath(Brush::rgb(0x14_16_1D), static fn (Path $bg) => $bg->addRectangle(0, 0, $w, $h));

        // search input row
        $ctx->drawString('⌕', new FontDescriptor(FONT, 26.0), [0.40, 0.55, 1.0], 22, 16);
        if ($this->query === '') {
            $ctx->drawString('Search for apps and commands…', new FontDescriptor(FONT, 19.0), [0.42, 0.46, 0.55], 58, 22);
        } else {
            $ctx->drawString($this->query, new FontDescriptor(FONT, 19.0), [0.92, 0.94, 0.98], 58, 22);
        }
        $ctx->fillPath(Brush::rgb(0x25_29_33), static fn (Path $rule) => $rule->addRectangle(0, 62, $w, 1));

        // result rows
        $rowH = 46.0;
        $y = 72.0;
        foreach ($this->filtered() as $i => $cmd) {
            $isSel = $i === $this->selected;
            if ($isSel) {
                $ctx->fillPath(Brush::solid(0.36, 0.42, 0.95, 0.18), static fn (Path $hl) => $hl->addRectangle(8, $y - 4, $w - 16, $rowH - 4));
            }
            $ctx->drawString($cmd[0], new FontDescriptor(FONT, 20.0), [1, 1, 1], 22, $y + 6);
            $ctx->drawString(
                $cmd[1],
                new FontDescriptor(FONT, 15.5, $isSel ? TextWeight::Bold : TextWeight::Normal),
                $isSel ? [0.96, 0.97, 1.0] : [0.82, 0.85, 0.91],
                62,
                $y + 9,
            );
            // NB: libui doesn't visibly honour DrawTextAlign on macOS, so the
            // category is positioned manually near the right edge.
            $ctx->drawString($cmd[2], new FontDescriptor(FONT, 12.0), [0.46, 0.51, 0.60], $w - 132, $y + 12);
            $y += $rowH;
        }
    }

    public function key(AreaKeyEvent $e): bool
    {
        if ($e->up) {
            return true; // only act on key-down
        }

        // Extended keys: escape / arrows.
        if ($e->extKey === ExtKey::Escape->value || $e->key === 27) {
            Ffi::quit();
            return true;
        }
        $count = count($this->filtered());
        if ($e->extKey === ExtKey::Down->value) {
            $this->selected = $count > 0 ? ($this->selected + 1) % $count : 0;
            $this->area?->queueRedrawAll();
            return true;
        }
        if ($e->extKey === ExtKey::Up->value) {
            $this->selected = $count > 0 ? ($this->selected - 1 + $count) % $count : 0;
            $this->area?->queueRedrawAll();
            return true;
        }

        // Regular keys.
        $k = $e->key;
        if ($k === 10 || $k === 13) { // enter — run the selection
            $sel = $this->filtered()[$this->selected] ?? null;
            if ($sel !== null) {
                fwrite(\STDOUT, "▶ {$sel[1]}\n");
                Ffi::quit();
            }
            return true;
        }
        if ($k === 8 || $k === 127) { // backspace
            $this->query = substr($this->query, 0, -1);
            $this->selected = 0;
            $this->area?->queueRedrawAll();
            return true;
        }
        if ($k >= 32 && $k < 127) {
            $this->query .= chr($k);
            $this->selected = 0;
            $this->area?->queueRedrawAll();
            return true;
        }

        return true;
    }
};

// Seed a query so the demo opens mid-search (and the screenshot shows filtering).
$palette->query = 're';

Ffi::init();

$area = new Area($palette);
$palette->area = $area;

new Window('Command palette', 660, 420)
    ->setBorderless(true)
    ->setChild(new Box()->appendStretchy($area))
    ->run();
