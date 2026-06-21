<?php

declare(strict_types=1);

namespace Libui\Draw;

use Libui\Color;
use Libui\Ffi;
use Libui\Generated\Enum\DrawFillMode;
use Libui\Generated\Enum\DrawTextAlign;
use Libui\Text\Attribute;
use Libui\Text\AttributedString;
use Libui\Text\FontDescriptor;
use Libui\Text\TextLayout;

/**
 * The drawing surface handed to an area's draw handler. Wraps a uiDrawContext*;
 * only valid for the duration of that single draw call.
 */
final class DrawContext
{
    public function __construct(
        private readonly \FFI\CData $ctx,
    ) {}

    public function fill(Path $path, Brush $brush): void
    {
        // libui takes the brush/stroke structs by pointer.
        Ffi::get()->uiDrawFill($this->ctx, $path->handle(), $brush->toCData());
    }

    public function stroke(Path $path, Brush $brush, StrokeParams $stroke): void
    {
        Ffi::get()->uiDrawStroke(
            $this->ctx,
            $path->handle(),
            $brush->toCData(),
            $stroke->toCData(),
        );
    }

    /**
     * Build a path with $build, fill it, and free it — no manual end()/free().
     *
     *   $ctx->fillPath(Brush::rgb(0x0F172A), fn (Path $p) => $p->addRectangle(0, 0, $w, $h));
     */
    public function fillPath(Brush $brush, callable $build, DrawFillMode $fillMode = DrawFillMode::Winding): void
    {
        $path = new Path($fillMode);
        $build($path);
        $path->end();
        $this->fill($path, $brush);
    }

    /** Build a path with $build, stroke it, and free it. */
    public function strokePath(
        Brush $brush,
        StrokeParams $stroke,
        callable $build,
        DrawFillMode $fillMode = DrawFillMode::Winding,
    ): void {
        $path = new Path($fillMode);
        $build($path);
        $path->end();
        $this->stroke($path, $brush, $stroke);
    }

    /** Coerce a paint argument to a Brush (Color -> solid Brush). */
    private static function brush(Brush|Color $paint): Brush
    {
        return $paint instanceof Brush ? $paint : Brush::color($paint);
    }

    public function fillRect(float $x, float $y, float $width, float $height, Brush|Color $paint): void
    {
        $this->fillPath(self::brush($paint), static fn (Path $p) => $p->addRectangle($x, $y, $width, $height));
    }

    public function strokeRect(float $x, float $y, float $width, float $height, Brush|Color $paint, ?StrokeParams $stroke = null): void
    {
        $this->strokePath(
            self::brush($paint),
            $stroke ?? StrokeParams::solid(1.0),
            static fn (Path $p) => $p->addRectangle($x, $y, $width, $height),
        );
    }

    public function fillCircle(float $cx, float $cy, float $radius, Brush|Color $paint): void
    {
        $this->fillPath(self::brush($paint), static fn (Path $p) => $p->circle($cx, $cy, $radius));
    }

    public function strokeCircle(float $cx, float $cy, float $radius, Brush|Color $paint, ?StrokeParams $stroke = null): void
    {
        $this->strokePath(
            self::brush($paint),
            $stroke ?? StrokeParams::solid(1.0),
            static fn (Path $p) => $p->circle($cx, $cy, $radius),
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

    /**
     * Convenience for the common case: draw a single string in one colour and
     * font at ($x, $y) — no manual AttributedString / TextLayout dance.
     *
     * Pass $width to wrap/align within a box (needed for Center/Right alignment);
     * leave it null for an un-wrapped single line.
     *
     * @param Color|array{float,float,float}|array{float,float,float,float} $color
     */
    public function drawString(
        string $text,
        FontDescriptor $font,
        Color|array $color,
        float $x,
        float $y,
        ?float $width = null,
        DrawTextAlign $align = DrawTextAlign::Left,
    ): void {
        $string = new AttributedString();
        $string->append($text, Attribute::fromColor(Color::from($color)));
        $layout = new TextLayout($string, $font, $width ?? 1.0e6, $align);
        $this->text($layout, $x, $y);
    }
}
