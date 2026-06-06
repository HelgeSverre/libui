<?php

declare(strict_types=1);

namespace Libui\Text;

use Libui\Ffi;
use Libui\Generated\Enum\DrawTextAlign;

/**
 * A laid-out, ready-to-draw block of attributed text, wrapping
 * uiDrawTextLayout*.
 *
 * uiDrawNewTextLayout reads its inputs through a uiDrawTextLayoutParams struct
 * (the attributed string, default font, wrap width and alignment). That struct
 * and the PHP wrappers it references are retained here so their pointers stay
 * valid for the layout's lifetime. Call free() when done.
 */
final class TextLayout
{
    private \FFI\CData $layout;
    private \FFI\CData $params;

    public function __construct(
        private readonly AttributedString $string,
        private readonly FontDescriptor $font,
        float $width,
        DrawTextAlign $align = DrawTextAlign::Left,
    ) {
        $ffi = Ffi::get();

        $params = $ffi->new('uiDrawTextLayoutParams');
        $params->String = $string->handle();
        $params->DefaultFont = $font->addr();
        $params->Width = $width;
        $params->Align = $align->value;

        $this->params = $params; // keep params alive for the call (and referenced objects via props)
        $this->layout = $ffi->uiDrawNewTextLayout(\FFI::addr($params));
    }

    public function handle(): \FFI\CData
    {
        return $this->layout;
    }

    public function free(): void
    {
        Ffi::get()->uiDrawFreeTextLayout($this->layout);
    }
}
