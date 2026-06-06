<?php

declare(strict_types=1);

/**
 * Styled text rendering via the Draw + Text layers.
 *
 * Builds an attributed string with several differently-styled spans (colours,
 * a bold span, an italic span, an underlined span), lays it out at the area
 * width with a default font, and draws it into a custom Area.
 *   php examples/text.php
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
use Libui\Generated\Enum\DrawTextAlign;
use Libui\Generated\Enum\TextItalic;
use Libui\Generated\Enum\TextWeight;
use Libui\Text\Attribute;
use Libui\Text\AttributedString;
use Libui\Text\FontDescriptor;
use Libui\Text\TextLayout;
use Libui\Window;

Ffi::init();

$delegate = new class extends AreaDelegate {
    public function draw(DrawContext $ctx, AreaDrawParams $p): void
    {
        $w = $p->areaWidth;
        $h = $p->areaHeight;

        // Light background rectangle behind the text.
        $ctx->fillPath(Brush::rgb(0xF8FAFC), fn (Path $p) => $p->addRectangle(0, 0, $w, $h));

        // Build a multi-styled paragraph. Each append() applies its attributes
        // to exactly the span just added.
        $string = new AttributedString();
        $string->append('PHP', Attribute::color(0.31, 0.27, 0.90), Attribute::weight(TextWeight::Bold));
        $string->appendUnattributed(' meets ');
        $string->append('libui', Attribute::color(0.02, 0.71, 0.83), Attribute::weight(TextWeight::Bold));
        $string->appendUnattributed(" via FFI.\n\n");
        $string->append('This span is bold,', Attribute::weight(TextWeight::Bold), Attribute::color(0.86, 0.15, 0.15));
        $string->appendUnattributed(' ');
        $string->append(
            'this one is italic,',
            Attribute::italic(TextItalic::Italic),
            Attribute::color(0.09, 0.55, 0.24),
        );
        $string->appendUnattributed(' and ');
        $string->append('this one is underlined.', Attribute::underline(), Attribute::color(0.55, 0.10, 0.60));
        $string->appendUnattributed(
            "\n\nThe rest wraps as ordinary body text " . 'across the full width of the drawing area, demonstrating ' . 'the default font and left alignment.',
        );

        $font = new FontDescriptor('Georgia', 18.0);

        $layout = new TextLayout($string, $font, max(1.0, $w - 24.0), DrawTextAlign::Left);
        $ctx->text($layout, 12, 12);
        $layout->free();
    }
};

$area = new Area($delegate);

$window = new Window('PHP libui — styled text', 560, 320);
$box = new Box();
$box->appendStretchy($area); // fill the window
$window->setChild($box);

$area->queueRedrawAll();
$window->run();
