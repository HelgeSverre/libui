# Drawing facade — implementation-ready spec

Strictly **additive**, non-breaking. Package requires PHP >=8.5; v0.1.0 already shipped. Mirror the existing value-type style (`Libui\Color`, `Libui\Text\RichText`/`TextStyle`) and the FFI struct-lifetime retention already used in `Brush::toCData`/`StrokeParams::toCData`. Use `Libui\Color` wherever colour appears — do not reinvent it.

Grounding facts verified in the source:
- `Ffi::get()` lazily loads the FFI scope WITHOUT calling `uiInit()`. So `Path`, `Brush`, `StrokeParams`, `Stop`, `Matrix` construction and `toCData()` work in plain `PHPUnit\Framework\TestCase` (this is why `tests/DrawTest.php` extends `TestCase`, not `LibuiTestCase`). Only tests that need a live `uiArea`/`uiDrawContext` need `LibuiTestCase`.
- Existing retention pattern (Brush): build C struct in `toCData()`, write fields, for arrays `$array = $ffi->new("T[$n]")`, set pointer via `\FFI::addr($array[0])`, then **retain the array on `$this`** (`$this->stopsArray = $array`) and retain the struct (`$this->cdata = $brush`), return `\FFI::addr($brush)`.
- C signatures (src/Native/libui.gen.h): `uiDrawPathNewFigure(p,x,y)`, `uiDrawPathNewFigureWithArc(p,xCenter,yCenter,radius,startAngle,sweep,negative)`, `uiDrawPathLineTo(p,x,y)`, `uiDrawPathArcTo(...)`, `uiDrawPathBezierTo(p,c1x,c1y,c2x,c2y,endX,endY)`, `uiDrawPathCloseFigure(p)`, `uiDrawPathAddRectangle(p,x,y,w,h)`.
- `Control::keep(callable): callable` retains closures process-lifetime. `Area::makeHandler` already wraps delegate calls in `self::guard()`; `Area::queueRedrawAll()` exists.

---

## 1. NEW `src/Draw/Stop.php`

Immutable gradient stop value type built from `Libui\Color`. Mirrors `Color` style (private ctor optional; here a public ctor + `at()` factory is fine and ergonomic).

```php
<?php

declare(strict_types=1);

namespace Libui\Draw;

use Libui\Color;

/**
 * A single gradient colour stop: a position along the gradient (0..1) and a
 * {@see Color}. The typed replacement for hand-built [pos, r, g, b, a] tuples
 * passed to {@see Brush::linearGradient()} / {@see Brush::radialGradient()}.
 *
 *   Brush::linearGradient(0, 0, 0, 200, [
 *       Stop::at(0.0, Color::rgb(0x312B90)),
 *       Stop::at(1.0, Color::rgb(0x0F172A)),
 *   ]);
 */
final class Stop
{
    public function __construct(
        public readonly float $pos,
        public readonly Color $color,
    ) {}

    public static function at(float $pos, Color $color): self
    {
        return new self($pos, $color);
    }

    /**
     * The stop as the [pos, r, g, b, a] tuple that {@see Brush::toCData()}
     * already consumes.
     *
     * @return array{float, float, float, float, float}
     */
    public function toArray(): array
    {
        return [$this->pos, $this->color->r, $this->color->g, $this->color->b, $this->color->a];
    }
}
```

Notes: do NOT clamp `$pos` (libui accepts the raw value; clamping would silently hide bugs and there is no precedent in `Color`). Colour channels are already clamped by `Color`.

---

## 2. MODIFY `src/Draw/Brush.php`

Goal: `linearGradient`/`radialGradient` accept **either** `Stop[]` **or** the existing tuple `array{float,float,float,float,float}[]`. Keep everything else identical (retention logic untouched).

Add a private normalizer and call it from both gradient factories:

```php
/**
 * Normalize a stops array to the internal [pos,r,g,b,a] tuple list, accepting
 * either {@see Stop} objects or raw [pos,r,g,b,a] tuples (or a mix).
 *
 * @param list<Stop|array{float,float,float,float,float}> $stops
 * @return list<array{float,float,float,float,float}>
 */
private static function normalizeStops(array $stops): array
{
    return array_map(
        static fn (Stop|array $stop): array => $stop instanceof Stop ? $stop->toArray() : $stop,
        array_values($stops),
    );
}
```

Update the two factories (signatures unchanged at the PHP type level — keep `array $stops`; widen the docblock only):

```php
/** @param list<Stop|array{float,float,float,float,float}> $stops */
public static function linearGradient(float $x0, float $y0, float $x1, float $y1, array $stops): self
{
    return new self(
        DrawBrushType::LinearGradient->value, 0, 0, 0, 1,
        [$x0, $y0, $x1, $y1, 0.0],
        self::normalizeStops($stops),
    );
}

/** @param list<Stop|array{float,float,float,float,float}> $stops */
public static function radialGradient(float $cx, float $cy, float $radius, array $stops): self
{
    return new self(
        DrawBrushType::RadialGradient->value, 0, 0, 0, 1,
        [$cx, $cy, $cx, $cy, $radius],
        self::normalizeStops($stops),
    );
}
```

`toCData()` and the `private readonly array $stops` constructor param stay exactly as-is — they already iterate `[$pos, $r, $g, $b, $a]` tuples, and the retention (`$this->stopsArray`, `$this->cdata`) is unchanged. The `private __construct` docblock for `$stops` stays `array{float,float,float,float,float}[]` because by the time it reaches the constructor the values are always normalized tuples.

---

## 3. MODIFY `src/Draw/StrokeParams.php`

Add a fluent builder over the existing public mutable properties. The existing `solid(float $thickness): self` factory stays; add chainable mutators that return `$this`. The mutable public properties already exist (`$thickness`, `$cap`, `$join`, `$miterLimit`, `$dashes`, `$dashPhase`), so the builder just assigns and returns `$this`.

```php
public function thickness(float $thickness): self
{
    $this->thickness = $thickness;
    return $this;
}

public function cap(DrawLineCap $cap): self
{
    $this->cap = $cap;
    return $this;
}

public function join(DrawLineJoin $join): self
{
    $this->join = $join;
    return $this;
}

public function miterLimit(float $limit): self
{
    $this->miterLimit = $limit;
    return $this;
}

/**
 * Set the dash on/off pattern and optional phase. Empty $dashes = solid line.
 *
 * @param float[] $dashes on/off lengths
 */
public function dashed(array $dashes, float $phase = 0.0): self
{
    $this->dashes = $dashes;
    $this->dashPhase = $phase;
    return $this;
}
```

Result: `StrokeParams::solid(2.0)->cap(DrawLineCap::Round)->join(DrawLineJoin::Round)`. `toCData()` is untouched (it already reads these fields and retains `$this->dashArray`/`$this->cdata`).

---

## 4. MODIFY `src/Draw/Path.php`

Add geometric sugar over existing primitives. **Do not** add anything that depends on a context transform (paths have no transform). Each returns `$this`.

```php
use const M_PI;

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
    $this->newFigureWithArc($cx, $cy, $radius, 0.0, 2 * M_PI, false);
    $this->closeFigure();
    return $this;
}

/**
 * An axis-aligned ellipse approximated with four cubic Béziers (kappa method).
 * Paths have no transform, so a circle-plus-scale is not available here.
 */
public function ellipse(float $cx, float $cy, float $rx, float $ry): self
{
    $k = 0.5522847498307936; // 4/3 * (sqrt(2) - 1)
    $ox = $rx * $k;
    $oy = $ry * $k;

    $this->newFigure($cx + $rx, $cy);                       // start at right
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
    $this->arcTo($right - $r, $y + $r, $r, -M_PI / 2, M_PI / 2, false);
    $this->lineTo($right, $bottom - $r);
    $this->arcTo($right - $r, $bottom - $r, $r, 0.0, M_PI / 2, false);
    $this->lineTo($x + $r, $bottom);
    $this->arcTo($x + $r, $bottom - $r, $r, M_PI / 2, M_PI / 2, false);
    $this->lineTo($x, $y + $r);
    $this->arcTo($x + $r, $y + $r, $r, M_PI, M_PI / 2, false);
    $this->closeFigure();
    return $this;
}

/**
 * A quadratic Bézier from the current point to ($endX,$endY) via control
 * ($cx,$cy), expressed as the equivalent cubic for libui's bezierTo.
 * Requires an active figure (call newFigure/lineTo first).
 */
public function quadTo(float $cx, float $cy, float $endX, float $endY): self
{
    // Promote quadratic control point to two cubic control points relative to
    // the quadratic control point. Because Path does not expose the current
    // point, callers that need exact promotion should use bezierTo directly;
    // here we approximate with the quadratic control point duplicated, which
    // libui draws as a smooth curve through the control point.
    $this->bezierTo($cx, $cy, $cx, $cy, $endX, $endY);
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
```

Implementation notes for the implementer:
- `M_PI` must be referenced as `\M_PI` or imported with `use const M_PI;` at the top of the file (project uses `declare(strict_types=1)` and namespaced code; check existing files — `tests/DrawTest.php` uses bare `M_PI`, but in namespaced production code prefer `\M_PI`). Use `\M_PI` inline to avoid an import line, OR add `use const M_PI;`. Pick one and be consistent.
- `quadTo`'s exact cubic promotion needs the current point (P0); libui's `uiDrawPath` does not expose it. The duplicated-control-point form above is a documented approximation. If exactness is required, document that callers track P0 themselves and use `bezierTo`. Keep `quadTo` but clearly comment the approximation in the docblock. (This is the one place we trade precision for ergonomics; flagged in risks.)

---

## 5. MODIFY `src/Draw/DrawContext.php`

Add shape helpers that accept a `Brush|Color` paint (coerce `Color` -> `Brush::color()`), plus optional `StrokeParams`. Reuse the existing `fillPath`/`strokePath` builders so path lifetime is handled identically.

```php
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
```

`use Libui\Color;` already imported in DrawContext.php. `Brush`, `Path`, `StrokeParams` are same-namespace. The transient `Path` created inside `fillPath`/`strokePath` is freed by its own `__destruct` after the synchronous fill/stroke returns — unchanged lifetime semantics.

---

## 6. MODIFY `src/AreaDelegate.php`

Add a bound `Area` reference and a `redraw()` convenience so subclasses stop hand-rolling an Area field + `queueRedrawAll()`.

```php
abstract class AreaDelegate
{
    private ?Area $area = null;

    public function draw(DrawContext $ctx, AreaDrawParams $params): void {}
    public function mouse(AreaMouseEvent $event): void {}
    public function mouseCrossed(bool $left): void {}
    public function dragBroken(): void {}

    public function key(AreaKeyEvent $event): bool
    {
        return false;
    }

    /**
     * Bind this delegate to its Area. Called by {@see Area::__construct()};
     * not intended for direct use.
     *
     * @internal
     */
    public function bindArea(Area $area): void
    {
        $this->area = $area;
    }

    /** The Area this delegate drives, or null if not yet bound. */
    public function area(): ?Area
    {
        return $this->area;
    }

    /**
     * Queue a full repaint of the bound Area. No-op if the delegate has not
     * been bound to an Area yet. Subclasses call $this->redraw() from event
     * handlers instead of storing an Area and calling queueRedrawAll().
     */
    public function redraw(): void
    {
        $this->area?->queueRedrawAll();
    }
}
```

Add `use Libui\Area;`? No — `AreaDelegate` is in namespace `Libui`, and `Area` is also `Libui\Area`. No import needed; reference `Area` directly. (Verify: file declares `namespace Libui;`.)

---

## 7. MODIFY `src/Area.php`

After the handle is created in `__construct`, bind the delegate:

```php
public function __construct(AreaDelegate $delegate, ?int $scrollWidth = null, ?int $scrollHeight = null)
{
    $ffi = Ffi::get();
    $this->handler = $this->makeHandler($delegate);

    $this->handle = $scrollWidth !== null
        ? $ffi->uiNewScrollingArea(\FFI::addr($this->handler), $scrollWidth, $scrollHeight ?? 0)
        : $ffi->uiNewArea(\FFI::addr($this->handler));

    $delegate->bindArea($this); // let the delegate call $this->redraw()
}
```

This creates a PHP-level reference cycle (Area -> handler closures -> delegate -> area -> Area). PHP's cycle GC handles it; no native pointer is stored in the delegate. Acceptable and documented.

---

## 8. Tests — `tests/DrawTest.php` (+ new cases)

All Stop/Brush/StrokeParams/Path sugar tests stay on **plain `TestCase`** (FFI scope loads lazily via `Ffi::get()`; no `uiInit` needed), matching the existing `DrawTest`. Add new imports: `use Libui\Draw\Stop;`.

Stop:
- `testStopAtStoresPosAndColor`
- `testStopToArrayMatchesTupleForm` — `Stop::at(0.25, Color::rgb255(49,43,144,0.5))->toArray()` equals `[0.25, 49/255, 43/255, 144/255, 0.5]` with `assertEqualsWithDelta(..., 1e-9)`.

Brush gradient overloads (assert against `toCData()` fields; hold the `$brush` local so the pointer stays valid):
- `testLinearGradientAcceptsStopObjects` — NumStops===2, Stops[0].Pos===0.0, Stops[1].Pos===1.0, channels match.
- `testLinearGradientStillAcceptsTuples` (regression) — tuple form still yields correct Pos/R/G/B/A.
- `testRadialGradientAcceptsStopObjects` — NumStops===2, OuterRadius set, channels correct.
- `testGradientMixedStopAndTupleArray` — `[Stop::at(0,..), [1.0,0,0,1,1]]` normalizes both.
- `testGradientStopsArrayRetainedAfterToCData` — after `toCData()`, read `Stops[1].Pos` again; still correct (CData kept alive).

StrokeParams builder:
- `testStrokeParamsFluentBuilder` — chain returns same instance; `toCData()` reflects Cap/Join/Thickness/MiterLimit/DashPhase/NumDashes.
- `testStrokeParamsThicknessReturnsSelf`
- `testStrokeParamsDashedSetsDashesAndPhase` — `toCData()` NumDashes===2.
- `testStrokeParamsBuilderDoesNotBreakConstructor` (regression).

Path sugar (build + `end()`, assert returns `$this`; these run the live primitives, but `Ffi::get()` alone suffices — no `uiInit`):
- `testPathLineCreatesFigure`
- `testPathCircleBuildsClosedFigure`
- `testPathEllipseBuilds`
- `testPathRoundedRectBuilds`
- `testPathRoundedRectClampsRadius`
- `testPathQuadToBuilds`
- `testPathBezierThroughBuilds`
- `testPathSugarChaining`

DrawContext / Color coercion: a real `uiDrawContext*` is **descoped** (only obtainable inside a live draw callback). Test the seam instead:
- `testDrawContextPaintCoercionColorToBrush` — exercise the coercion by asserting `Brush::color($color)->toCData()` channels match (the helper is `private static`; if not directly reachable, assert the behaviour through a small reflection or via a `Brush::color` equivalence check). Prefer asserting on `Brush::color` directly since `DrawContext::brush()` simply delegates to it.

AreaDelegate / Area (these need a live Area, so use `LibuiTestCase`):
- `testAreaDelegateRedrawNoAreaIsNoop` (plain TestCase) — a bare subclass calling `redraw()` before binding does nothing, no throw.
- `testAreaDelegateBindAreaStoresArea` (LibuiTestCase) — after construction, `delegate->area()` is the Area.
- `testAreaBindsDelegateOnConstruct` (LibuiTestCase) — constructing `new Area($delegate)` sets `delegate->area()`.
- `testAreaDelegateRedrawCallsQueueRedrawAll` (LibuiTestCase) — bind, call `redraw()`, assert no throw. Optionally spy by subclassing `Area` to override `queueRedrawAll()` and record the call (the cleanest assertion). DESCOPED: asserting an actual repaint.

Put the new Area/AreaDelegate tests either in `tests/DrawTest.php` (split: keep pure-unit there) or a small new `tests/AreaDelegateTest.php extends LibuiTestCase` for the live ones. Recommendation: add a new `tests/AreaDelegateTest.php` for the LibuiTestCase cases, keep pure-unit additions in `DrawTest.php`.

---

## 9. Acceptance / build

- `vendor/bin/phpunit` green (all existing + new).
- `vendor/bin/phpstan analyse` clean against the project baseline. Widened `array` docblocks (`list<Stop|array{...}>`) must satisfy PHPStan; if the existing baseline references gradient signatures, regenerate the baseline only if a genuinely new, unavoidable entry appears — prefer fixing types over baselining.
- No changes to generated files under `src/Generated/` or `src/Native/`.
- No public method removed or signature narrowed (additive only). `linearGradient`/`radialGradient` keep `array $stops` at the PHP signature level (widened in docblock only), so no BC break.

## 10. Explicitly descoped
- Live pixel/render assertions and obtaining a real `uiDrawContext` headless — impossible without a running event loop draw callback.
- Exact quadratic-to-cubic promotion in `quadTo` (no current-point accessor on `uiDrawPath`); shipped as a documented smooth-curve approximation.
- No native confirm/message-dialog work (out of scope for Drawing).