<?php

declare(strict_types=1);

namespace Libui\Draw;

use Libui\Ffi;
use Libui\Generated\Enum\DrawFillMode;

/**
 * A vector path, built then filled/stroked into a DrawContext.
 *
 * Call end() once the path is complete, draw it, then free() it. Paths are
 * cheap and meant to be short-lived inside a single draw handler.
 */
final class Path
{
    private \FFI\CData $path;

    private bool $freed = false;

    public function __construct(DrawFillMode $fillMode = DrawFillMode::Winding)
    {
        $this->path = Ffi::get()->uiDrawNewPath($fillMode->value);
    }

    public function handle(): \FFI\CData
    {
        return $this->path;
    }

    public function newFigure(float $x, float $y): self
    {
        Ffi::get()->uiDrawPathNewFigure($this->path, $x, $y);
        return $this;
    }

    public function lineTo(float $x, float $y): self
    {
        Ffi::get()->uiDrawPathLineTo($this->path, $x, $y);
        return $this;
    }

    public function closeFigure(): self
    {
        Ffi::get()->uiDrawPathCloseFigure($this->path);
        return $this;
    }

    public function addRectangle(float $x, float $y, float $width, float $height): self
    {
        Ffi::get()->uiDrawPathAddRectangle($this->path, $x, $y, $width, $height);
        return $this;
    }

    /**
     * Start a new figure on an arc (angles in radians, clockwise; $negative
     * sweeps the other way). Combine with closeFigure() for a filled wedge.
     */
    public function newFigureWithArc(float $xCenter, float $yCenter, float $radius, float $startAngle, float $sweep, bool $negative = false): self
    {
        Ffi::get()->uiDrawPathNewFigureWithArc($this->path, $xCenter, $yCenter, $radius, $startAngle, $sweep, (int) $negative);
        return $this;
    }

    /** Line from the current point to the arc's start, then the arc itself. */
    public function arcTo(float $xCenter, float $yCenter, float $radius, float $startAngle, float $sweep, bool $negative = false): self
    {
        Ffi::get()->uiDrawPathArcTo($this->path, $xCenter, $yCenter, $radius, $startAngle, $sweep, (int) $negative);
        return $this;
    }

    /** Cubic Bézier curve to (endX, endY) via the two control points. */
    public function bezierTo(float $c1x, float $c1y, float $c2x, float $c2y, float $endX, float $endY): self
    {
        Ffi::get()->uiDrawPathBezierTo($this->path, $c1x, $c1y, $c2x, $c2y, $endX, $endY);
        return $this;
    }

    /** Finalise the path; required before it can be drawn. */
    public function end(): self
    {
        Ffi::get()->uiDrawPathEnd($this->path);
        return $this;
    }

    /** Free the native path. Idempotent, and runs automatically on destruction. */
    public function free(): void
    {
        if ($this->freed) {
            return;
        }
        Ffi::get()->uiDrawFreePath($this->path);
        $this->freed = true;
    }

    public function __destruct()
    {
        $this->free();
    }
}
