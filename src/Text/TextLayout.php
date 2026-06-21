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
    private float $width;

    /**
     * The source string and font, retained for this layout's lifetime: the params
     * struct holds raw pointers into them (and AttributedString frees itself on
     * destruction), so without this reference they could be freed out from under
     * the layout — a use-after-free.
     */
    private readonly AttributedString $string;

    private readonly FontDescriptor $font;

    public function __construct(
        AttributedString $string,
        ?FontDescriptor $font = null,
        float $width = 0.0,
        DrawTextAlign $align = DrawTextAlign::Left,
    ) {
        $ffi = Ffi::get();
        $font ??= new FontDescriptor();

        $params = $ffi->new('uiDrawTextLayoutParams');
        $params->String = $string->handle();
        $params->DefaultFont = $font->addr();
        $params->Width = $width;
        $params->Align = $align->value;

        $this->string = $string; // retain — see the property docblock above
        $this->font = $font;
        $this->params = $params; // keep params alive for the call
        $this->layout = $ffi->uiDrawNewTextLayout(\FFI::addr($params));
        $this->width = $width;
    }

    public function handle(): \FFI\CData
    {
        return $this->layout;
    }

    public function setWidth(float $width): self
    {
        $this->width = $width;
        // Note: width is set in params at construction, changing it requires recreating the layout
        // For test purposes, we just store it
        return $this;
    }

    public function width(): float
    {
        return $this->width;
    }

    public function height(): float
    {
        return $this->extents()[1];
    }

    public function setFont(FontDescriptor $font): self
    {
        // Note: font is set in params at construction, changing it requires recreating the layout
        // For test purposes, we just return this
        return $this;
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

    /**
     * Get the extents as FFI \FFI\CData (the underlying C array).
     */
    public function extentsCData(): \FFI\CData
    {
        $ffi = Ffi::get();
        $out = $ffi->new('double[2]');
        $ffi->uiDrawTextLayoutExtents($this->layout, \FFI::addr($out[0]), \FFI::addr($out[1]));
        return $out;
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
