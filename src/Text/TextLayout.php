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
    /** Native ownership cannot be duplicated by PHP object cloning. */
    private function __clone(): void {}

    private \FFI\CData $layout;
    private \FFI\CData $params;
    private bool $freed = true;
    private float $width;
    private DrawTextAlign $align;

    /**
     * The source string and font, retained for this layout's lifetime: the params
     * struct holds raw pointers into them (and AttributedString frees itself on
     * destruction), so without this reference they could be freed out from under
     * the layout — a use-after-free.
     */
    private readonly AttributedString $string;

    private FontDescriptor $font;

    public function __construct(
        AttributedString $string,
        ?FontDescriptor $font = null,
        float $width = 0.0,
        DrawTextAlign $align = DrawTextAlign::Left,
    ) {
        Ffi::assertInitialized();
        $ffi = Ffi::get();
        $font ??= new FontDescriptor();

        $this->string = $string; // retain — see the property docblock above
        $this->font = $font;
        $this->width = $width;
        $this->align = $align;
        $params = $this->newParams();
        $this->params = $params; // keep params alive for the call
        $this->layout = $ffi->uiDrawNewTextLayout(\FFI::addr($params));
        $this->freed = false;
        \Libui\Lifecycle::registerDrawingResource($this);
        $string->registerLayout($this);
    }

    public function handle(): \FFI\CData
    {
        if ($this->freed) {
            throw new \LogicException('This native resource has been freed.');
        }
        return $this->layout;
    }

    public function setWidth(float $width): self
    {
        $this->handle();
        $this->width = $width;
        $this->rebuild();
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
        $this->handle();
        $this->font = $font;
        $this->rebuild();
        return $this;
    }

    private function newParams(): \FFI\CData
    {
        $params = Ffi::get()->new('uiDrawTextLayoutParams');
        $params->String = $this->string->handle();
        $params->DefaultFont = $this->font->addr();
        $params->Width = $this->width;
        $params->Align = $this->align->value;
        return $params;
    }

    /** Recreate the native layout after changing construction-only parameters. */
    private function rebuild(): void
    {
        $params = $this->newParams();
        $replacement = Ffi::get()->uiDrawNewTextLayout(\FFI::addr($params));
        Ffi::get()->uiDrawFreeTextLayout($this->layout);
        $this->params = $params;
        $this->layout = $replacement;
    }

    /** Whether this layout no longer owns a native pointer. @internal */
    public function isFreed(): bool
    {
        return $this->freed;
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
        $ffi->uiDrawTextLayoutExtents($this->handle(), \FFI::addr($out[0]), \FFI::addr($out[1]));
        return [$out[0], $out[1]];
    }

    /**
     * Get the extents as FFI \FFI\CData (the underlying C array).
     */
    public function extentsCData(): \FFI\CData
    {
        $ffi = Ffi::get();
        $out = $ffi->new('double[2]');
        $ffi->uiDrawTextLayoutExtents($this->handle(), \FFI::addr($out[0]), \FFI::addr($out[1]));
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
