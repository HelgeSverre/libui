<?php

declare(strict_types=1);

namespace Libui\Draw;

use Libui\Ffi;
use Libui\Text\TextLayout;

/**
 * The drawing surface handed to an area's draw handler. Wraps a uiDrawContext*;
 * only valid for the duration of that single draw call.
 */
final class DrawContext
{
    public function __construct(private readonly \FFI\CData $ctx) {}

    public function fill(Path $path, Brush $brush): void
    {
        // libui takes the brush/stroke structs by pointer.
        Ffi::get()->uiDrawFill($this->ctx, $path->handle(), \FFI::addr($brush->toCData()));
    }

    public function stroke(Path $path, Brush $brush, StrokeParams $stroke): void
    {
        Ffi::get()->uiDrawStroke(
            $this->ctx,
            $path->handle(),
            \FFI::addr($brush->toCData()),
            \FFI::addr($stroke->toCData()),
        );
    }

    /** Push the current clip/transform state onto libui's stack. */
    public function save(): void
    {
        Ffi::get()->uiDrawSave($this->ctx);
    }

    /** Pop the most recently saved clip/transform state. */
    public function restore(): void
    {
        Ffi::get()->uiDrawRestore($this->ctx);
    }

    /** Intersect the current clip region with the given path. */
    public function clip(Path $path): void
    {
        Ffi::get()->uiDrawClip($this->ctx, $path->handle());
    }

    /** Compose the given affine transform onto the current matrix. */
    public function transform(Matrix $matrix): void
    {
        Ffi::get()->uiDrawTransform($this->ctx, $matrix->addr());
    }

    /** Draw a laid-out text block with its top-left corner at ($x, $y). */
    public function text(TextLayout $layout, float $x, float $y): void
    {
        Ffi::get()->uiDrawText($this->ctx, $layout->handle(), $x, $y);
    }
}
