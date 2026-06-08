<?php

declare(strict_types=1);

/**
 * Markdown editor with a live, rich-rendered preview.
 *
 * Left: a plain MultilineEntry of Markdown source. Right: a custom-drawn Area
 * that parses the source and lays it out with the Text engine — sized/bold
 * headings, **bold** / *italic* / `code` inline spans, bullet lists, blockquotes,
 * fenced code blocks and horizontal rules. Re-renders on every keystroke.
 *
 *   php examples/markdown.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\Area;
use Libui\AreaDelegate;
use Libui\Box;
use Libui\Draw\Brush;
use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\Path;
use Libui\Ffi;
use Libui\Generated\Enum\TextItalic;
use Libui\Generated\Enum\TextWeight;
use Libui\MultilineEntry;
use Libui\Text\Attribute;
use Libui\Text\AttributedString;
use Libui\Text\FontDescriptor;
use Libui\Text\TextLayout;
use Libui\Window;

const BODY_FONT = 'Helvetica Neue';

const CODE_FONT = 'Menlo';

const C_HEADING = [0.93, 0.95, 0.98];

const C_BODY = [0.78, 0.82, 0.88];

const C_CODE = [0.56, 0.86, 0.62];

const C_ACCENT = [0.20, 0.78, 0.95];

const C_QUOTE = [0.60, 0.66, 0.74];

/** Split Markdown source into a flat list of typed blocks. */
function parseMarkdown(string $src): array
{
    $lines = explode("\n", $src);
    $blocks = [];
    $para = [];
    $list = [];
    $quote = [];
    $code = null;

    $flushPara = function () use (&$para, &$blocks): void {
        if ($para !== []) {
            $blocks[] = ['type' => 'p', 'text' => implode(' ', $para)];
            $para = [];
        }
    };
    $flushList = function () use (&$list, &$blocks): void {
        if ($list !== []) {
            $blocks[] = ['type' => 'ul', 'items' => $list];
            $list = [];
        }
    };
    $flushQuote = function () use (&$quote, &$blocks): void {
        if ($quote !== []) {
            $blocks[] = ['type' => 'quote', 'text' => implode(' ', $quote)];
            $quote = [];
        }
    };

    foreach ($lines as $line) {
        if ($code !== null) {
            if (preg_match('/^```/', $line)) {
                $blocks[] = ['type' => 'code', 'text' => implode("\n", $code)];
                $code = null;
            } else {
                $code[] = $line;
            }
            continue;
        }
        if (preg_match('/^```/', $line)) {
            $flushPara();
            $flushList();
            $flushQuote();
            $code = [];
        } elseif (trim($line) === '') {
            $flushPara();
            $flushList();
            $flushQuote();
        } elseif (preg_match('/^(#{1,3})\s+(.*)$/', $line, $m)) {
            $flushPara();
            $flushList();
            $flushQuote();
            $blocks[] = ['type' => 'h', 'level' => strlen($m[1]), 'text' => $m[2]];
        } elseif (preg_match('/^\s*([-*_])\1{2,}\s*$/', $line)) {
            $flushPara();
            $flushList();
            $flushQuote();
            $blocks[] = ['type' => 'hr'];
        } elseif (preg_match('/^\s*[-*]\s+(.*)$/', $line, $m)) {
            $flushPara();
            $flushQuote();
            $list[] = $m[1];
        } elseif (preg_match('/^\s*>\s?(.*)$/', $line, $m)) {
            $flushPara();
            $flushList();
            $quote[] = $m[1];
        } else {
            $flushList();
            $flushQuote();
            $para[] = $line;
        }
    }
    $flushPara();
    $flushList();
    $flushQuote();

    return $blocks;
}

/** Build an AttributedString from inline Markdown (**bold**, *italic*, `code`). */
function inline(string $text, float $size, array $color, TextWeight $weight = TextWeight::Normal): AttributedString
{
    $s = new AttributedString();
    $parts = preg_split('/(\*\*.+?\*\*|`[^`]+`|\*[^*]+\*)/', $text, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY);

    foreach ($parts ?: [$text] as $part) {
        if (str_starts_with($part, '**') && str_ends_with($part, '**')) {
            $s->append(substr($part, 2, -2), Attribute::size($size), Attribute::color(...$color), Attribute::weight(TextWeight::Bold));
        } elseif (str_starts_with($part, '`') && str_ends_with($part, '`')) {
            $s->append(substr($part, 1, -1), Attribute::size($size - 1), Attribute::family(CODE_FONT), Attribute::color(...C_CODE));
        } elseif (strlen($part) > 1 && $part[0] === '*' && str_ends_with($part, '*')) {
            $s->append(substr($part, 1, -1), Attribute::size($size), Attribute::color(...$color), Attribute::italic(TextItalic::Italic));
        } else {
            $s->append($part, Attribute::size($size), Attribute::color(...$color), Attribute::weight($weight));
        }
    }
    return $s;
}

$preview = new class extends AreaDelegate {
    public ?Area $area = null;
    public array $blocks = [];
    public float $contentHeight = 0;
    private array $blockPositions = [];

    public function draw(DrawContext $ctx, AreaDrawParams $p): void
    {
        $w = $p->areaWidth;
        $h = $p->areaHeight;
        $ctx->fillPath(Brush::rgb(0x0F_11_17), static fn (Path $bg) => $bg->addRectangle(0, 0, $w, $h));

        $pad = 24.0;
        $x = $pad;
        $y = $pad;
        $width = $w - (2 * $pad);

        $clipY = $p->clipY ?? 0;
        $clipH = $p->clipHeight ?? $h;
        $clipBottom = $clipY + $clipH;

        foreach ($this->blocks as $i => $b) {
            $blockStartY = $this->blockPositions[$i]['y'] ?? $y;
            $blockHeight = $this->blockPositions[$i]['height'] ?? 0;
            $blockEndY = $blockStartY + $blockHeight;

            if ($blockStartY > $clipBottom || $blockEndY < $clipY) {
                continue;
            }

            $y = $this->block($ctx, $b, $x, $blockStartY, $width);
        }

        $this->contentHeight = $y;
    }

    private function text(DrawContext $ctx, AttributedString $s, FontDescriptor $f, float $x, float $y, float $width): float
    {
        $layout = new TextLayout($s, $f, $width);
        $ctx->text($layout, $x, $y);
        [, $height] = $layout->extents();
        return $height;
    }

    private function measureText(AttributedString $s, FontDescriptor $f, float $width): float
    {
        $layout = new TextLayout($s, $f, $width);
        [, $height] = $layout->extents();
        return $height;
    }

    private function block(DrawContext $ctx, array $b, float $x, float $y, float $width): float
    {
        switch ($b['type']) {
            case 'h':
                $size = [1 => 27.0, 2 => 21.0, 3 => 17.0][$b['level']];
                $f = new FontDescriptor(BODY_FONT, $size, TextWeight::Bold);
                $h = $this->text($ctx, inline($b['text'], $size, C_HEADING, TextWeight::Bold), $f, $x, $y, $width);
                return $y + $h + 14;

            case 'p':
                $f = new FontDescriptor(BODY_FONT, 14.5);
                $h = $this->text($ctx, inline($b['text'], 14.5, C_BODY), $f, $x, $y, $width);
                return $y + $h + 12;

            case 'ul':
                $f = new FontDescriptor(BODY_FONT, 14.5);
                foreach ($b['items'] as $item) {
                    $ctx->fillPath(Brush::solid(...C_ACCENT), static fn (Path $d) => $d->addRectangle($x + 2, $y + 8, 5, 5));
                    $h = $this->text($ctx, inline($item, 14.5, C_BODY), $f, $x + 18, $y, $width - 18);
                    $y += $h + 6;
                }
                return $y + 6;

            case 'quote':
                $ctx->fillPath(Brush::solid(...C_ACCENT), static fn (Path $bar) => $bar->addRectangle($x, $y, 3, 0));
                $f = new FontDescriptor(BODY_FONT, 14.5, TextWeight::Normal, TextItalic::Italic);
                $h = $this->text($ctx, inline($b['text'], 14.5, C_QUOTE), $f, $x + 14, $y, $width - 14);
                $ctx->fillPath(Brush::solid(...C_ACCENT), static fn (Path $bar) => $bar->addRectangle($x, $y, 3, $h));
                return $y + $h + 12;

            case 'code':
                $f = new FontDescriptor(CODE_FONT, 13.0);
                $s = new AttributedString();
                $s->append($b['text'], Attribute::size(13.0), Attribute::family(CODE_FONT), Attribute::color(...C_CODE));
                $layout = new TextLayout($s, $f, $width - 24);
                [, $h] = $layout->extents();
                $ctx->fillPath(Brush::rgb(0x16_19_22), static fn (Path $bgp) => $bgp->addRectangle($x, $y, $width, $h + 20));
                $ctx->text($layout, $x + 12, $y + 10);
                return $y + $h + 32;

            case 'hr':
                $ctx->fillPath(Brush::rgb(0x2A_2F_3A), static fn (Path $rule) => $rule->addRectangle($x, $y + 6, $width, 1));
                return $y + 18;
        }
        return $y;
    }

    public function measureContentHeight(float $width): float
    {
        $pad = 24.0;
        $x = $pad;
        $y = $pad;
        $contentWidth = $width - (2 * $pad);

        $this->blockPositions = [];

        foreach ($this->blocks as $i => $b) {
            $startY = $y;
            $y = $this->measureBlock($b, $x, $y, $contentWidth);
            $this->blockPositions[$i] = ['y' => $startY, 'height' => $y - $startY];
        }

        return $y;
    }

    private function measureBlock(array $b, float $x, float $y, float $width): float
    {
        switch ($b['type']) {
            case 'h':
                $size = [1 => 27.0, 2 => 21.0, 3 => 17.0][$b['level']];
                $f = new FontDescriptor(BODY_FONT, $size, TextWeight::Bold);
                $h = $this->measureText(inline($b['text'], $size, C_HEADING, TextWeight::Bold), $f, $width);
                return $y + $h + 14;

            case 'p':
                $f = new FontDescriptor(BODY_FONT, 14.5);
                $h = $this->measureText(inline($b['text'], 14.5, C_BODY), $f, $width);
                return $y + $h + 12;

            case 'ul':
                $f = new FontDescriptor(BODY_FONT, 14.5);
                foreach ($b['items'] as $item) {
                    $h = $this->measureText(inline($item, 14.5, C_BODY), $f, $width - 18);
                    $y += $h + 6;
                }
                return $y + 6;

            case 'quote':
                $f = new FontDescriptor(BODY_FONT, 14.5, TextWeight::Normal, TextItalic::Italic);
                $h = $this->measureText(inline($b['text'], 14.5, C_QUOTE), $f, $width - 14);
                return $y + $h + 12;

            case 'code':
                $f = new FontDescriptor(CODE_FONT, 13.0);
                $s = new AttributedString();
                $s->append($b['text'], Attribute::size(13.0), Attribute::family(CODE_FONT), Attribute::color(...C_CODE));
                $layout = new TextLayout($s, $f, $width - 24);
                [, $h] = $layout->extents();
                return $y + $h + 32;

            case 'hr':
                return $y + 18;
        }
        return $y;
    }
};

$sample = <<<'MD'
    # Markdown, rendered

    Live preview drawn with libui's **text engine** — no web view. Type on the
    left, see it *rendered* on the right.

    ## Inline styles

    You get **bold**, *italic*, and `inline code` spans, all measured and wrapped
    by `uiDrawTextLayout`.

    - Bullet lists
    - With **emphasis** inside items
    - And `code` too

    > Blockquotes get a coloured bar and muted, italic text.

    ---

    ```
    function ray(mixed $v): void {
        // a fenced code block in a monospace face
    }
    ```

    That's the whole renderer in one example file.
    MD;

Ffi::init();

$editor = new MultilineEntry();
$editor->setText($sample);

$preview->blocks = parseMarkdown($sample);
$previewWidth = 460;
$contentHeight = max(600, $preview->measureContentHeight($previewWidth));

$area = Area::scrolling($preview, $previewWidth, (int) ceil($contentHeight));
$preview->area = $area;

$editor->onChanged(function () use ($editor, $preview, $area): void {
    $preview->blocks = parseMarkdown($editor->text());
    $contentHeight = max(600, $preview->measureContentHeight(460));
    $area->setSize(460, (int) ceil($contentHeight));
    $area->queueRedrawAll();
});

new Window('Markdown editor', 920, 600)
    ->setChild(
        Box::horizontal(padded: true)
            ->appendStretchy($editor)
            ->appendStretchy($area),
    )
    ->run();
