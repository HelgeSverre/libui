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
    private bool $freed = false;

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

    /**
     * Measure the laid-out text. Returns [width, height] in points — the actual
     * extents after wrapping at the layout width. (Wraps uiDrawTextLayoutExtents,
     * whose two `double *` out-params are otherwise awkward to call directly.)
     *
     * @return array{float, float}
     */
    public function extents(): array
    {
        $ffi = Ffi::get();
        $out = $ffi->new('double[2]');
        $ffi->uiDrawTextLayoutExtents($this->layout, \FFI::addr($out[0]), \FFI::addr($out[1]));
        return [$out[0], $out[1]];
    }

    /** Free the native layout. Idempotent, and runs automatically on destruction. */
    public function free(): void
    {
        if ($this->freed) {
            return;
        }
        Ffi::get()->uiDrawFreeTextLayout($this->layout);
        $this->freed = true;
    }

    public function __destruct()
    {
        $this->free();
    }
}
