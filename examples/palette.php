<?php

declare(strict_types=1);

/**
 * A Raycast-style command palette: a borderless, rounded, shadowed window that is
 * entirely one custom-drawn Area. The search input and result list are painted by
 * hand, and every keystroke is handled in the Area's KeyEvent.
 *
 * Unlike a toy demo, the commands actually *do* something (mocked where a real OS
 * action would be, real where it's self-contained):
 *
 *   • Toggle Theme      — flips the whole palette between dark and light, live
 *   • Calculator        — opens a sub-mode that evaluates arithmetic as you type
 *   • Generate Password — produces a real random password and shows it
 *   • everything else   — opens a result panel with plausible (mocked) output
 *   • Quit              — exits
 *
 * Navigation: type to fuzzy-filter, ↑/↓ to move, ↵ to run, esc to go back (from a
 * result/calculator) or to clear the query / dismiss the window (from the list).
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
use Libui\Generated\Enum\WindowCornerStyle;
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

/**
 * Minimal, safe arithmetic evaluator: + - * / and parentheses, no eval().
 * Recursive-descent over a tokenised, validated expression via three mutually
 * recursive closures. Returns null on any parse error (illegal characters,
 * unbalanced parens, division by zero, trailing garbage).
 */
function calcEval(string $s): ?float
{
    $clean = preg_replace('/\s+/', '', $s) ?? '';
    if ($clean === '') {
        return null;
    }
    preg_match_all('/\d+\.?\d*|\.\d+|[-+*\/()]/', $clean, $m);
    if (implode('', $m[0]) !== $clean) {
        return null; // contained illegal characters
    }

    $tok = $m[0];
    $pos = 0;

    $expr = null;
    $factor = function () use (&$tok, &$pos, &$expr, &$factor): ?float {
        $t = $tok[$pos] ?? null;
        if ($t === '-') {
            $pos++;
            $v = $factor();
            return $v === null ? null : -$v;
        }
        if ($t === '(') {
            $pos++;
            $v = $expr();
            if (($tok[$pos] ?? null) !== ')') {
                return null;
            }
            $pos++;
            return $v;
        }
        if ($t !== null && is_numeric($t)) {
            $pos++;
            return (float) $t;
        }
        return null;
    };
    $term = function () use (&$tok, &$pos, &$factor): ?float {
        $v = $factor();
        while ($v !== null && (($op = $tok[$pos] ?? null) === '*' || $op === '/')) {
            $pos++;
            $r = $factor();
            if ($r === null || $op === '/' && $r === 0.0) {
                return null;
            }
            $v = $op === '*' ? $v * $r : $v / $r;
        }
        return $v;
    };
    $expr = function () use (&$tok, &$pos, &$term): ?float {
        $v = $term();
        while ($v !== null && (($op = $tok[$pos] ?? null) === '+' || $op === '-')) {
            $pos++;
            $r = $term();
            if ($r === null) {
                return null;
            }
            $v = $op === '+' ? $v + $r : $v - $r;
        }
        return $v;
    };

    $result = $expr();

    return $pos === count($tok) ? $result : null;
}

$palette = new class extends AreaDelegate {
    public ?Area $area = null;
    public string $query = '';
    public int $selected = 0;
    public bool $dark = true;

    /** One of: palette | result | calc */
    public string $mode = 'palette';

    public string $resultTitle = '';
    /** @var list<string> */
    public array $resultLines = [];
    public ?string $resultValue = null;

    public string $calcExpr = '';

    /** @var list<array{string,string,string}> icon, name, category */
    public array $commands = [
        ['🎨', 'Toggle Theme',      'Appearance'],
        ['🧮', 'Calculator',        'Tools'],
        ['🔑', 'Generate Password', 'Security'],
        ['🔍', 'Search Files',      'Navigation'],
        ['⚙️', 'Open Settings',     'System'],
        ['📋', 'Clipboard History', 'Productivity'],
        ['🪟', 'New Window',        'Window'],
        ['🔄', 'Reload Project',    'System'],
        ['🌐', 'Open in Browser',   'Web'],
        ['📁', 'Reveal in Finder',  'Files'],
        ['⏻',  'Quit',              'System'],
    ];

    /**
     * Theme palette as [r,g,b] float triples (selection fill takes an alpha arg).
     *
     * @return array<string,array{float,float,float}>
     */
    private function colors(): array
    {
        return (
            $this->dark
                ? [
                    'bg' => [0.078, 0.086, 0.114],
                    'rule' => [0.145, 0.161, 0.200],
                    'accent' => [0.40, 0.55, 1.0],
                    'placeholder' => [0.42, 0.46, 0.55],
                    'query' => [0.92, 0.94, 0.98],
                    'name' => [0.82, 0.85, 0.91],
                    'nameSel' => [0.96, 0.97, 1.0],
                    'category' => [0.46, 0.51, 0.60],
                    'sel' => [0.36, 0.42, 0.95],
                    'value' => [0.52, 0.86, 0.62],
                ] : [
                    'bg' => [0.96, 0.965, 0.975],
                    'rule' => [0.84, 0.85, 0.88],
                    'accent' => [0.18, 0.40, 0.95],
                    'placeholder' => [0.55, 0.58, 0.64],
                    'query' => [0.10, 0.12, 0.16],
                    'name' => [0.18, 0.20, 0.26],
                    'nameSel' => [0.05, 0.06, 0.10],
                    'category' => [0.52, 0.55, 0.62],
                    'sel' => [0.30, 0.42, 0.95],
                    'value' => [0.10, 0.52, 0.28],
                ]
        );
    }

    /** Fill a rectangle with an [r,g,b] triple at the given alpha. */
    private function fill(DrawContext $ctx, array $c, float $x, float $y, float $w, float $h, float $a = 1.0): void
    {
        $ctx->fillPath(
            Brush::solid($c[0], $c[1], $c[2], $a),
            static fn (Path $p) => $p->addRectangle($x, $y, $w, $h),
        );
    }

    /** @return list<array{string,string,string}> */
    private function filtered(): array
    {
        return array_values(array_filter($this->commands, fn (array $c) => fuzzy($this->query, $c[1])));
    }

    public function draw(DrawContext $ctx, AreaDrawParams $p): void
    {
        $w = $p->areaWidth;
        $h = $p->areaHeight;
        $c = $this->colors();

        $this->fill($ctx, $c['bg'], 0, 0, $w, $h);

        match ($this->mode) {
            'calc' => $this->drawCalc($ctx, $w, $h, $c),
            'result' => $this->drawResult($ctx, $w, $h, $c),
            default => $this->drawPalette($ctx, $w, $h, $c),
        };

        // footer hint bar, shared across modes
        $hint = match ($this->mode) {
            'calc' => 'type an expression   ·   esc  back',
            'result' => 'esc  back',
            default => '↑ ↓  navigate     ↵  run     esc  clear / close',
        };
        $this->fill($ctx, $c['rule'], 0, $h - 34, $w, 1);
        $ctx->drawString($hint, new FontDescriptor(FONT, 11.5), $c['category'], 22, $h - 26);
    }

    /** @param array<string,array{float,float,float}> $c */
    private function drawPalette(DrawContext $ctx, float $w, float $h, array $c): void
    {
        // search input row
        $ctx->drawString('⌕', new FontDescriptor(FONT, 26.0), $c['accent'], 22, 16);
        if ($this->query === '') {
            $ctx->drawString('Search for apps and commands…', new FontDescriptor(FONT, 19.0), $c['placeholder'], 58, 22);
        } else {
            $ctx->drawString($this->query, new FontDescriptor(FONT, 19.0), $c['query'], 58, 22);
        }
        $this->fill($ctx, $c['rule'], 0, 62, $w, 1);

        $rows = $this->filtered();
        if ($rows === []) {
            $ctx->drawString('No matching commands', new FontDescriptor(FONT, 15.0), $c['placeholder'], 22, 88);
            return;
        }

        $rowH = 46.0;
        $y = 72.0;
        foreach ($rows as $i => $cmd) {
            $isSel = $i === $this->selected;
            if ($isSel) {
                $this->fill($ctx, $c['sel'], 8, $y - 4, $w - 16, $rowH - 4, 0.18);
            }
            $ctx->drawString($cmd[0], new FontDescriptor(FONT, 20.0), $isSel ? $c['nameSel'] : $c['name'], 22, $y + 6);
            $ctx->drawString(
                $cmd[1],
                new FontDescriptor(FONT, 15.5, $isSel ? TextWeight::Bold : TextWeight::Normal),
                $isSel ? $c['nameSel'] : $c['name'],
                62,
                $y + 9,
            );
            // NB: libui doesn't visibly honour DrawTextAlign on macOS, so the
            // category is positioned manually near the right edge.
            $ctx->drawString($cmd[2], new FontDescriptor(FONT, 12.0), $c['category'], $w - 132, $y + 12);
            $y += $rowH;
        }
    }

    /** @param array<string,array{float,float,float}> $c */
    private function drawResult(DrawContext $ctx, float $w, float $h, array $c): void
    {
        $ctx->drawString($this->resultTitle, new FontDescriptor(FONT, 22.0, TextWeight::Bold), $c['nameSel'], 22, 22);
        $this->fill($ctx, $c['rule'], 0, 62, $w, 1);

        $y = 84.0;
        foreach ($this->resultLines as $line) {
            $ctx->drawString($line, new FontDescriptor(FONT, 15.0), $c['name'], 24, $y);
            $y += 28.0;
        }

        if ($this->resultValue !== null) {
            $y += 8.0;
            $this->fill($ctx, $c['sel'], 18, $y - 6, $w - 36, 44, 0.14);
            $ctx->drawString($this->resultValue, new FontDescriptor(FONT, 20.0, TextWeight::Bold), $c['value'], 30, $y + 4);
        }
    }

    /** @param array<string,array{float,float,float}> $c */
    private function drawCalc(DrawContext $ctx, float $w, float $h, array $c): void
    {
        $ctx->drawString('🧮  Calculator', new FontDescriptor(FONT, 20.0, TextWeight::Bold), $c['nameSel'], 22, 20);
        $this->fill($ctx, $c['rule'], 0, 62, $w, 1);

        $expr = $this->calcExpr === '' ? '0' : $this->calcExpr;
        $ctx->drawString($expr, new FontDescriptor(FONT, 34.0), $c['query'], 30, 92);

        $result = calcEval($this->calcExpr);
        $shown = match (true) {
            $result !== null => '= ' . rtrim(rtrim(sprintf('%.6f', $result), '0'), '.'),
            $this->calcExpr === '' => 'type digits and + − × ÷ ( )',
            default => '…',
        };
        $ctx->drawString($shown, new FontDescriptor(FONT, 22.0, TextWeight::Bold), $result === null ? $c['placeholder'] : $c['value'], 30, 150);
    }

    public function key(AreaKeyEvent $e): bool
    {
        if ($e->up) {
            return true; // only act on key-down
        }

        return match ($this->mode) {
            'calc' => $this->keyCalc($e),
            'result' => $this->keyResult($e),
            default => $this->keyPalette($e),
        };
    }

    private function keyResult(AreaKeyEvent $e): bool
    {
        if ($e->extKey === ExtKey::Escape->value || $e->key === 27 || $e->key === 10 || $e->key === 13) {
            $this->mode = 'palette';
            $this->area?->queueRedrawAll();
        }
        return true;
    }

    private function keyCalc(AreaKeyEvent $e): bool
    {
        if ($e->extKey === ExtKey::Escape->value || $e->key === 27) {
            $this->mode = 'palette';
            $this->area?->queueRedrawAll();
            return true;
        }
        if ($e->key === 8 || $e->key === 127) { // backspace
            $this->calcExpr = substr($this->calcExpr, 0, -1);
            $this->area?->queueRedrawAll();
            return true;
        }
        $k = $e->key;
        if ($k >= 32 && $k < 127 && str_contains('0123456789.+-*/() ', chr($k))) {
            $this->calcExpr .= chr($k);
            $this->area?->queueRedrawAll();
        }
        return true;
    }

    private function keyPalette(AreaKeyEvent $e): bool
    {
        // escape: clear a query first, otherwise dismiss the window
        if ($e->extKey === ExtKey::Escape->value || $e->key === 27) {
            if ($this->query !== '') {
                $this->query = '';
                $this->selected = 0;
                $this->area?->queueRedrawAll();
            } else {
                Ffi::quit();
            }
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

        $k = $e->key;
        if ($k === 10 || $k === 13) { // enter — run the selection
            $sel = $this->filtered()[$this->selected] ?? null;
            if ($sel !== null) {
                $this->run($sel[1]);
                $this->area?->queueRedrawAll();
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

    /** Dispatch a chosen command. Self-contained actions are real; OS actions are mocked. */
    private function run(string $name): void
    {
        switch ($name) {
            case 'Toggle Theme':
                $this->dark = ! $this->dark; // live — the whole palette repaints
                return;

            case 'Quit':
                Ffi::quit();
                return;

            case 'Calculator':
                $this->mode = 'calc';
                $this->calcExpr = '';
                return;

            case 'Generate Password':
                $this->showResult(
                    'Generate Password',
                    ['A fresh 20-character password was', 'generated and copied to the clipboard (mock).'],
                    $this->generatePassword(),
                );
                return;

            default:
                [$title, $lines] = $this->mockResult($name);
                $this->showResult($title, $lines, null);
        }
    }

    /** @param list<string> $lines */
    private function showResult(string $title, array $lines, ?string $value): void
    {
        $this->mode = 'result';
        $this->resultTitle = $title;
        $this->resultLines = $lines;
        $this->resultValue = $value;
    }

    private function generatePassword(): string
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%^&*';
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < 20; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }

    /**
     * Plausible mocked output for OS-level commands we don't actually perform.
     *
     * @return array{string, list<string>}
     */
    private function mockResult(string $name): array
    {
        return match ($name) {
            'Search Files' => [
                'Search Files',
                [
                    '~/Projects/app/src/Main.php',
                    '~/Projects/app/README.md',
                    '~/Downloads/quarterly-report.pdf',
                    '— 3 of 1,204 indexed files',
                ],
            ],
            'Open Settings' => [
                'Settings',
                [
                    'Appearance        System',
                    'Global hotkey     ⌘ Space',
                    'Launch at login   On',
                ],
            ],
            'Clipboard History' => [
                'Clipboard History',
                [
                    '1.   https://libui.dev',
                    '2.   "the quick brown fox"',
                    '3.   #14161D',
                ],
            ],
            'New Window' => [
                'New Window',
                [
                    '(mock) would spawn a second',
                    'borderless, rounded window here.',
                ],
            ],
            'Reload Project' => [
                'Reload Project',
                [
                    'Reloaded 142 files in 0.31s.',
                    'No errors.',
                ],
            ],
            'Open in Browser' => [
                'Open in Browser',
                [
                    'Opening your default browser at',
                    'github.com/HelgeSverre/libui',
                ],
            ],
            'Reveal in Finder' => [
                'Reveal in Finder',
                [
                    'Revealed ~/Projects/app',
                    'in Finder.',
                ],
            ],
            default => [$name, ['(mock) ' . $name . ' executed.']],
        };
    }
};

Ffi::init();

$area = new Area($palette);
$palette->area = $area;

// Custom chrome: a borderless, rounded, shadowed window you can drag by its body.
$window = new Window('Command palette', 660, 460);
$window
    ->setBorderless(true)
    ->setCornerStyle(WindowCornerStyle::Rounded)
    ->setChild(new Box()->appendStretchy($area));
$window->setTitlebar($area); // drag the palette body to move the window
$window->run();
