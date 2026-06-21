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

    /**
     * The current pen position after the last point-producing op, or null when
     * undefined (after a new figure on an arc, a rectangle, or closeFigure). Used
     * by quadTo() to promote a quadratic Bézier to libui's cubic exactly.
     */
    private ?float $curX = null;

    private ?float $curY = null;

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
        $this->curX = $x;
        $this->curY = $y;
        return $this;
    }

    public function lineTo(float $x, float $y): self
    {
        Ffi::get()->uiDrawPathLineTo($this->path, $x, $y);
        $this->curX = $x;
        $this->curY = $y;
        return $this;
    }

    public function closeFigure(): self
    {
        Ffi::get()->uiDrawPathCloseFigure($this->path);
        $this->curX = $this->curY = null;
        return $this;
    }

    public function addRectangle(float $x, float $y, float $width, float $height): self
    {
        Ffi::get()->uiDrawPathAddRectangle($this->path, $x, $y, $width, $height);
        $this->curX = $this->curY = null;
        return $this;
    }

    /**
     * Start a new figure on an arc (angles in radians, clockwise; $negative
     * sweeps the other way). Combine with closeFigure() for a filled wedge.
     */
    public function newFigureWithArc(float $xCenter, float $yCenter, float $radius, float $startAngle, float $sweep, bool $negative = false): self
    {
        Ffi::get()->uiDrawPathNewFigureWithArc($this->path, $xCenter, $yCenter, $radius, $startAngle, $sweep, (int) $negative);
        $this->curX = $this->curY = null;
        return $this;
    }

    /** Line from the current point to the arc's start, then the arc itself. */
    public function arcTo(float $xCenter, float $yCenter, float $radius, float $startAngle, float $sweep, bool $negative = false): self
    {
        Ffi::get()->uiDrawPathArcTo($this->path, $xCenter, $yCenter, $radius, $startAngle, $sweep, (int) $negative);
        $this->curX = $this->curY = null;
        return $this;
    }

    /** Cubic Bézier curve to (endX, endY) via the two control points. */
    public function bezierTo(float $c1x, float $c1y, float $c2x, float $c2y, float $endX, float $endY): self
    {
        Ffi::get()->uiDrawPathBezierTo($this->path, $c1x, $c1y, $c2x, $c2y, $endX, $endY);
        $this->curX = $endX;
        $this->curY = $endY;
        return $this;
    }

    /**
     * Add an arc to the current figure (angles in radians, clockwise; $negative
     * sweeps the other way). This starts a new figure if one isn't active.
     */
    public function arc(float $xCenter, float $yCenter, float $radius, float $startAngle, float $sweep, bool $negative = false): self
    {
        Ffi::get()->uiDrawPathNewFigureWithArc($this->path, $xCenter, $yCenter, $radius, $startAngle, $sweep, (int) $negative);
        $this->curX = $this->curY = null;
        return $this;
    }

    /** A standalone line segment as its own figure. */
    public function line(float $x0, float $y0, float $x1, float $y1): self
    {
        $this->newFigure($x0, $y0);
        $this->lineTo($x1, $y1);
        return $this;
    }

    /** A full circle as a closed figure (single 0..2π arc). */
    public function circle(float $cx, float $cy, float $radius): self
    {
        $this->newFigureWithArc($cx, $cy, $radius, 0.0, 2 * \M_PI, false);
        $this->closeFigure();
        return $this;
    }

    /**
     * An axis-aligned ellipse approximated with four cubic Béziers (kappa method).
     * Paths have no transform, so a circle-plus-scale is not available here.
     */
    public function ellipse(float $cx, float $cy, float $rx, float $ry): self
    {
        $k = 0.552_284_749_830_793_6; // 4/3 * (sqrt(2) - 1)
        $ox = $rx * $k;
        $oy = $ry * $k;

        $this->newFigure($cx + $rx, $cy); // start at right
        $this->bezierTo($cx + $rx, $cy + $oy, $cx + $ox, $cy + $ry, $cx, $cy + $ry); // to bottom
        $this->bezierTo($cx - $ox, $cy + $ry, $cx - $rx, $cy + $oy, $cx - $rx, $cy); // to left
        $this->bezierTo($cx - $rx, $cy - $oy, $cx - $ox, $cy - $ry, $cx, $cy - $ry); // to top
        $this->bezierTo($cx + $ox, $cy - $ry, $cx + $rx, $cy - $oy, $cx + $rx, $cy); // to right
        $this->closeFigure();
        return $this;
    }

    /**
     * A rectangle with rounded corners. $radius is clamped to min(width,height)/2.
     * Corners are quarter-arcs; edges are straight (arcTo continues the figure).
     */
    public function roundedRect(float $x, float $y, float $width, float $height, float $radius): self
    {
        $r = min($radius, min($width, $height) / 2.0);
        if ($r <= 0.0) {
            return $this->addRectangle($x, $y, $width, $height);
        }

        $right = $x + $width;
        $bottom = $y + $height;

        // Start at the top edge, just right of the top-left corner.
        $this->newFigure($x + $r, $y);
        $this->lineTo($right - $r, $y);
        // top-right corner: centre ($right-$r, $y+$r), from -90deg sweeping +90deg
        $this->arcTo($right - $r, $y + $r, $r, -\M_PI / 2, \M_PI / 2, false);
        $this->lineTo($right, $bottom - $r);
        $this->arcTo($right - $r, $bottom - $r, $r, 0.0, \M_PI / 2, false);
        $this->lineTo($x + $r, $bottom);
        $this->arcTo($x + $r, $bottom - $r, $r, \M_PI / 2, \M_PI / 2, false);
        $this->lineTo($x, $y + $r);
        $this->arcTo($x + $r, $y + $r, $r, \M_PI, \M_PI / 2, false);
        $this->closeFigure();
        return $this;
    }

    /**
     * A quadratic Bézier from the current point to ($endX,$endY) via control
     * ($cx,$cy), promoted to libui's cubic bezierTo *exactly* (libui has no
     * native quadratic).
     *
     * Requires a defined current point — call newFigure()/lineTo()/bezierTo()
     * first; throws otherwise (an arc or rectangle leaves the point undefined).
     */
    public function quadTo(float $cx, float $cy, float $endX, float $endY): self
    {
        if ($this->curX === null || $this->curY === null) {
            throw new \LogicException(
                'Path::quadTo() needs a current point — call newFigure(), lineTo(), ' . 'or bezierTo() before it (arcs and rectangles leave it undefined).',
            );
        }

        // Exact quadratic -> cubic promotion: C1 = P0 + 2/3(C-P0), C2 = P1 + 2/3(C-P1).
        $twoThirds = 2.0 / 3.0;
        $c1x = $this->curX + ($twoThirds * ($cx - $this->curX));
        $c1y = $this->curY + ($twoThirds * ($cy - $this->curY));
        $c2x = $endX + ($twoThirds * ($cx - $endX));
        $c2y = $endY + ($twoThirds * ($cy - $endY));

        $this->bezierTo($c1x, $c1y, $c2x, $c2y, $endX, $endY);
        return $this;
    }

    /**
     * A cubic Bézier that also opens the figure at ($x0,$y0). Convenience for the
     * common "move then curve" pair.
     */
    public function bezierThrough(float $x0, float $y0, float $c1x, float $c1y, float $c2x, float $c2y, float $endX, float $endY): self
    {
        $this->newFigure($x0, $y0);
        $this->bezierTo($c1x, $c1y, $c2x, $c2y, $endX, $endY);
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
