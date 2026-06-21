<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Color;
use Libui\Draw\Brush;
use Libui\Draw\Matrix;
use Libui\Draw\Path;
use Libui\Draw\Stop;
use Libui\Draw\StrokeParams;
use Libui\Generated\Enum\DrawBrushType;
use Libui\Generated\Enum\DrawFillMode;
use Libui\Generated\Enum\DrawLineCap;
use Libui\Generated\Enum\DrawLineJoin;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the drawing subsystem (Path, Brush, StrokeParams, Matrix).
 * These classes are used within Area handlers for custom 2D drawing.
 */
final class DrawTest extends TestCase
{
    // ========================================================================
    // PATH TESTS
    // ========================================================================

    public function testPathConstructsWithDefaultFillMode(): void
    {
        $path = new Path();
        $this->assertInstanceOf(Path::class, $path);
    }

    public function testPathConstructsWithWindingFillMode(): void
    {
        $path = new Path(DrawFillMode::Winding);
        $this->assertInstanceOf(Path::class, $path);
    }

    public function testPathConstructsWithAlternateFillMode(): void
    {
        $path = new Path(DrawFillMode::Alternate);
        $this->assertInstanceOf(Path::class, $path);
    }

    public function testPathHandleReturnsFfiCData(): void
    {
        $path = new Path();
        $handle = $path->handle();

        $this->assertInstanceOf(\FFI\CData::class, $handle);
        $this->assertFalse(\FFI::isNull($handle));
    }

    public function testPathNewFigure(): void
    {
        $path = new Path();
        $result = $path->newFigure(10.0, 20.0);

        $this->assertSame($path, $result);
    }

    public function testPathNewFigureChaining(): void
    {
        $path = new Path();
        $result = $path->newFigure(0.0, 0.0)->newFigure(10.0, 10.0);

        $this->assertSame($path, $result);
    }

    public function testPathLineTo(): void
    {
        $path = new Path();
        $path->newFigure(0.0, 0.0);
        $result = $path->lineTo(100.0, 100.0);

        $this->assertSame($path, $result);
    }

    public function testPathCloseFigure(): void
    {
        $path = new Path();
        $path->newFigure(0.0, 0.0);
        $path->lineTo(100.0, 0.0);
        $path->lineTo(100.0, 100.0);

        $result = $path->closeFigure();

        $this->assertSame($path, $result);
    }

    public function testPathAddRectangle(): void
    {
        $path = new Path();
        $result = $path->addRectangle(0.0, 0.0, 100.0, 50.0);

        $this->assertSame($path, $result);
    }

    public function testPathEnd(): void
    {
        $path = new Path();
        $path->newFigure(0.0, 0.0);
        $path->lineTo(100.0, 100.0);

        // end() finalizes the path
        $path->end();

        $this->assertTrue(true, 'Path::end() should complete without error');
    }

    public function testPathFree(): void
    {
        $path = new Path();
        $path->newFigure(0.0, 0.0);
        $path->end();

        $path->free();

        $this->assertTrue(true, 'Path::free() should complete without error');
    }

    public function testPathArc(): void
    {
        $path = new Path();
        $result = $path->arc(0.0, 0.0, 50.0, 0.0, 3.14);

        $this->assertSame($path, $result);
    }

    public function testPathBezierTo(): void
    {
        $path = new Path();
        $path->newFigure(0.0, 0.0);
        $result = $path->bezierTo(100.0, 100.0, 150.0, 50.0, 200.0, 200.0);

        $this->assertSame($path, $result);
    }

    // ========================================================================
    // BRUSH TESTS
    // ========================================================================

    public function testBrushSolid(): void
    {
        $brush = Brush::solid(1.0, 0.5, 0.25, 1.0);
        $this->assertInstanceOf(Brush::class, $brush);
    }

    public function testBrushSolidWithDefaultAlpha(): void
    {
        $brush = Brush::solid(1.0, 0.5, 0.25);
        $this->assertInstanceOf(Brush::class, $brush);
    }

    public function testBrushRgb(): void
    {
        $brush = Brush::rgb(0xFF_0000); // Red
        $this->assertInstanceOf(Brush::class, $brush);
    }

    public function testBrushRgbWithAlpha(): void
    {
        $brush = Brush::rgb(0x00_FF00, 0.5); // Green with 50% opacity
        $this->assertInstanceOf(Brush::class, $brush);
    }

    public function testBrushLinearGradient(): void
    {
        $stops = [
            [0.0, 1.0, 0.0, 0.0, 1.0], // Red at position 0
            [1.0, 0.0, 1.0, 0.0, 1.0], // Blue at position 1
        ];

        $brush = Brush::linearGradient(0.0, 0.0, 100.0, 100.0, $stops);
        $this->assertInstanceOf(Brush::class, $brush);
    }

    public function testBrushRadialGradient(): void
    {
        $stops = [
            [0.0, 1.0, 1.0, 1.0, 1.0], // White at center
            [1.0, 0.0, 0.0, 0.0, 1.0], // Black at edge
        ];

        $brush = Brush::radialGradient(50.0, 50.0, 100.0, $stops);
        $this->assertInstanceOf(Brush::class, $brush);
    }

    public function testBrushToCData(): void
    {
        $brush = Brush::solid(1.0, 0.5, 0.25, 1.0);
        $cdata = $brush->toCData();

        $this->assertInstanceOf(\FFI\CData::class, $cdata);
        $this->assertFalse(\FFI::isNull($cdata));
    }

    public function testBrushColorMarshalsColorChannels(): void
    {
        // Hold the Brush: toCData() retains the struct on the instance, so the
        // returned pointer is only valid while the Brush is alive.
        $brush = Brush::color(Color::rgb(0x80_4020, 0.5));
        $cdata = $brush->toCData();

        $this->assertEqualsWithDelta(0x80 / 255, $cdata->R, 1e-9);
        $this->assertEqualsWithDelta(0x40 / 255, $cdata->G, 1e-9);
        $this->assertEqualsWithDelta(0x20 / 255, $cdata->B, 1e-9);
        $this->assertEqualsWithDelta(0.5, $cdata->A, 1e-9);
        $this->assertSame(DrawBrushType::Solid->value, $cdata->Type);
    }

    public function testBrushRgbEqualsBrushFromColor(): void
    {
        $rgbBrush = Brush::rgb(0x12_3456);
        $colorBrush = Brush::color(Color::rgb(0x12_3456));
        $viaRgb = $rgbBrush->toCData();
        $viaColor = $colorBrush->toCData();

        $this->assertSame($viaRgb->R, $viaColor->R);
        $this->assertSame($viaRgb->G, $viaColor->G);
        $this->assertSame($viaRgb->B, $viaColor->B);
        $this->assertSame($viaRgb->A, $viaColor->A);
    }

    // ========================================================================
    // STROKE PARAMS TESTS
    // ========================================================================

    public function testStrokeParamsConstructsWithDefaults(): void
    {
        $params = new StrokeParams();
        $this->assertInstanceOf(StrokeParams::class, $params);
    }

    public function testStrokeParamsWithAllOptions(): void
    {
        $params = new StrokeParams(
            cap: DrawLineCap::Round,
            join: DrawLineJoin::Round,
            thickness: 2.5,
            miterLimit: 10.0,
            dashes: [5.0, 5.0],
            dashPhase: 0.0,
        );

        $this->assertInstanceOf(StrokeParams::class, $params);
    }

    public function testStrokeParamsToCData(): void
    {
        $params = new StrokeParams();
        $cdata = $params->toCData();

        $this->assertInstanceOf(\FFI\CData::class, $cdata);
        $this->assertFalse(\FFI::isNull($cdata));
    }

    public function testStrokeParamsWithDashes(): void
    {
        $params = new StrokeParams(dashes: [5.0, 2.5, 5.0, 2.5]);
        $this->assertInstanceOf(StrokeParams::class, $params);
    }

    public function testStrokeParamsWithThickness(): void
    {
        $params = new StrokeParams(thickness: 5.0);
        $this->assertInstanceOf(StrokeParams::class, $params);
    }

    // ========================================================================
    // MATRIX TESTS
    // ========================================================================

    public function testMatrixConstructsIdentity(): void
    {
        $matrix = new Matrix();
        $this->assertInstanceOf(Matrix::class, $matrix);
    }

    public function testMatrixToCData(): void
    {
        $matrix = new Matrix();
        $cdata = $matrix->toCData();

        $this->assertInstanceOf(\FFI\CData::class, $cdata);
        $this->assertFalse(\FFI::isNull($cdata));
    }

    public function testMatrixTranslate(): void
    {
        $matrix = new Matrix();
        $result = $matrix->translate(10.0, 20.0);

        $this->assertSame($matrix, $result);
    }

    public function testMatrixScale(): void
    {
        $matrix = new Matrix();
        $result = $matrix->scale(2.0, 2.0);

        $this->assertSame($matrix, $result);
    }

    public function testMatrixRotate(): void
    {
        $matrix = new Matrix();
        $result = $matrix->rotate(45.0); // 45 degrees

        $this->assertSame($matrix, $result);
    }

    public function testMatrixSkew(): void
    {
        $matrix = new Matrix();
        $result = $matrix->skew(10.0, 20.0);

        $this->assertSame($matrix, $result);
    }

    public function testMatrixMultiply(): void
    {
        $matrix1 = new Matrix();
        $matrix1->translate(10.0, 20.0);

        $matrix2 = new Matrix();
        $matrix2->rotate(45.0);

        $result = $matrix1->multiply($matrix2);

        $this->assertSame($matrix1, $result);
    }

    public function testMatrixInvert(): void
    {
        $matrix = new Matrix();
        $matrix->translate(10.0, 20.0);

        $result = $matrix->invert();

        $this->assertSame($matrix, $result);
    }

    public function testMatrixChaining(): void
    {
        $matrix = new Matrix();
        $result = $matrix
            ->translate(10.0, 20.0)
            ->rotate(45.0)
            ->scale(2.0, 2.0);

        $this->assertSame($matrix, $result);
    }

    // ========================================================================
    // ENUM COVERAGE FOR DRAWING
    // ========================================================================

    public function testDrawBrushTypeValues(): void
    {
        $this->assertSame(0, DrawBrushType::Solid->value);
        $this->assertSame(1, DrawBrushType::LinearGradient->value);
        $this->assertSame(2, DrawBrushType::RadialGradient->value);
        $this->assertSame(3, DrawBrushType::Image->value);
    }

    public function testDrawFillModeValues(): void
    {
        $this->assertSame(0, DrawFillMode::Winding->value);
        $this->assertSame(1, DrawFillMode::Alternate->value);
    }

    public function testDrawLineCapValues(): void
    {
        $this->assertSame(0, DrawLineCap::Flat->value);
        $this->assertSame(1, DrawLineCap::Round->value);
        $this->assertSame(2, DrawLineCap::Square->value);
    }

    public function testDrawLineJoinValues(): void
    {
        $this->assertSame(0, DrawLineJoin::Miter->value);
        $this->assertSame(1, DrawLineJoin::Round->value);
        $this->assertSame(2, DrawLineJoin::Bevel->value);
    }

    // ========================================================================
    // COMPLEX PATH BUILDING TESTS
    // ========================================================================

    public function testPathComplexShape(): void
    {
        $path = new Path();

        // Draw a star-like shape
        $path
            ->newFigure(50.0, 0.0)
            ->lineTo(61.8, 38.2)
            ->lineTo(90.9, 38.2)
            ->lineTo(68.2, 61.8)
            ->lineTo(79.4, 90.9)
            ->lineTo(50.0, 72.7)
            ->lineTo(20.6, 90.9)
            ->lineTo(31.8, 61.8)
            ->lineTo(9.1, 38.2)
            ->lineTo(38.2, 38.2)
            ->closeFigure()
            ->end();

        $this->assertTrue(true, 'Complex path should build without error');
    }

    public function testPathWithArcs(): void
    {
        $path = new Path();

        // Draw a circle using arcs
        $path->newFigure(50.0, 50.0);
        $path->arc(0.0, 0.0, 100.0, 0.0, 2 * M_PI);
        $path->closeFigure();
        $path->end();

        $this->assertTrue(true, 'Path with arc should build without error');
    }

    public function testPathWithBezierCurves(): void
    {
        $path = new Path();

        $path->newFigure(0.0, 0.0);
        $path->lineTo(50.0, 0.0);
        $path->bezierTo(100.0, 0.0, 100.0, 50.0, 100.0, 100.0);
        $path->lineTo(0.0, 100.0);
        $path->closeFigure();
        $path->end();

        $this->assertTrue(true, 'Path with bezier curves should build without error');
    }

    // ========================================================================
    // BRUSH FACTORY TESTS
    // ========================================================================

    public function testBrushFactoriesReturnDistinctInstances(): void
    {
        $brush1 = Brush::solid(1.0, 0.0, 0.0, 1.0);
        $brush2 = Brush::solid(0.0, 1.0, 0.0, 1.0);

        $this->assertNotSame($brush1, $brush2);
    }

    public function testBrushLinearGradientWithMultipleStops(): void
    {
        $stops = [
            [0.0, 1.0, 0.0, 0.0, 1.0], // Red
            [0.5, 0.0, 1.0, 0.0, 1.0], // Green
            [1.0, 0.0, 0.0, 1.0, 1.0], // Blue
        ];

        $brush = Brush::linearGradient(0.0, 0.0, 100.0, 0.0, $stops);
        $this->assertInstanceOf(Brush::class, $brush);
    }

    public function testBrushRadialGradientWithMultipleStops(): void
    {
        $stops = [
            [0.0, 1.0, 1.0, 1.0, 1.0], // White at center
            [0.5, 0.5, 0.5, 0.5, 1.0], // Gray in middle
            [1.0, 0.0, 0.0, 0.0, 1.0], // Black at edge
        ];

        $brush = Brush::radialGradient(50.0, 50.0, 50.0, $stops);
        $this->assertInstanceOf(Brush::class, $brush);
    }

    // ========================================================================
    // MATRIX TRANSFORMATION TESTS
    // ========================================================================

    public function testMatrixIdentityToCData(): void
    {
        $matrix = new Matrix();
        $cdata = $matrix->toCData();

        $this->assertInstanceOf(\FFI\CData::class, $cdata);
    }

    public function testMatrixCombinedTransformations(): void
    {
        $matrix = new Matrix();

        // Apply multiple transformations
        $matrix
            ->translate(10.0, 20.0)
            ->rotate(30.0)
            ->scale(1.5, 1.5)
            ->skew(5.0, 10.0);

        $cdata = $matrix->toCData();
        $this->assertInstanceOf(\FFI\CData::class, $cdata);
    }

    public function testMatrixReset(): void
    {
        $matrix = new Matrix();
        $matrix->translate(10.0, 20.0);
        $matrix->rotate(45.0);

        // Reset to identity
        $matrix->reset();

        $cdata = $matrix->toCData();
        $this->assertInstanceOf(\FFI\CData::class, $cdata);
    }

    // ========================================================================
    // STOP TESTS
    // ========================================================================

    public function testStopAtStoresPosAndColor(): void
    {
        $color = Color::rgb(0x31_2B90);
        $stop = Stop::at(0.25, $color);

        $this->assertSame(0.25, $stop->pos);
        $this->assertSame($color, $stop->color);
    }

    public function testStopToArrayMatchesTupleForm(): void
    {
        $stop = Stop::at(0.25, Color::rgb255(49, 43, 144, 0.5));

        $this->assertEqualsWithDelta(
            [0.25, 49 / 255, 43 / 255, 144 / 255, 0.5],
            $stop->toArray(),
            1e-9,
        );
    }

    // ========================================================================
    // BRUSH GRADIENT OVERLOAD TESTS
    // ========================================================================

    public function testLinearGradientAcceptsStopObjects(): void
    {
        $brush = Brush::linearGradient(0.0, 0.0, 0.0, 200.0, [
            Stop::at(0.0, Color::rgb(0x31_2B90)),
            Stop::at(1.0, Color::rgb(0x0F_172A)),
        ]);
        $cdata = $brush->toCData();

        $this->assertSame(2, $cdata->NumStops);
        $this->assertSame(0.0, $cdata->Stops[0]->Pos);
        $this->assertSame(1.0, $cdata->Stops[1]->Pos);
        $this->assertEqualsWithDelta(0x31 / 255, $cdata->Stops[0]->R, 1e-9);
        $this->assertEqualsWithDelta(0x0F / 255, $cdata->Stops[1]->R, 1e-9);
    }

    public function testLinearGradientStillAcceptsTuples(): void
    {
        $brush = Brush::linearGradient(0.0, 0.0, 100.0, 100.0, [
            [0.0, 1.0, 0.0, 0.0, 1.0],
            [1.0, 0.0, 0.0, 1.0, 1.0],
        ]);
        $cdata = $brush->toCData();

        $this->assertSame(2, $cdata->NumStops);
        $this->assertSame(0.0, $cdata->Stops[0]->Pos);
        $this->assertSame(1.0, $cdata->Stops[0]->R);
        $this->assertSame(1.0, $cdata->Stops[1]->Pos);
        $this->assertSame(1.0, $cdata->Stops[1]->B);
    }

    public function testRadialGradientAcceptsStopObjects(): void
    {
        $brush = Brush::radialGradient(50.0, 50.0, 100.0, [
            Stop::at(0.0, Color::white()),
            Stop::at(1.0, Color::black()),
        ]);
        $cdata = $brush->toCData();

        $this->assertSame(2, $cdata->NumStops);
        $this->assertSame(100.0, $cdata->OuterRadius);
        $this->assertSame(1.0, $cdata->Stops[0]->R);
        $this->assertSame(0.0, $cdata->Stops[1]->R);
    }

    public function testGradientMixedStopAndTupleArray(): void
    {
        $brush = Brush::linearGradient(0.0, 0.0, 0.0, 200.0, [
            Stop::at(0.0, Color::rgb(0xFF_0000)),
            [1.0, 0.0, 0.0, 1.0, 1.0],
        ]);
        $cdata = $brush->toCData();

        $this->assertSame(2, $cdata->NumStops);
        $this->assertSame(1.0, $cdata->Stops[0]->R);
        $this->assertSame(1.0, $cdata->Stops[1]->B);
    }

    public function testGradientStopsArrayRetainedAfterToCData(): void
    {
        $brush = Brush::linearGradient(0.0, 0.0, 0.0, 200.0, [
            Stop::at(0.0, Color::rgb(0x31_2B90)),
            Stop::at(1.0, Color::rgb(0x0F_172A)),
        ]);
        $cdata = $brush->toCData();

        // Read again after the call: the CData (and stops array) is kept alive.
        $this->assertSame(1.0, $cdata->Stops[1]->Pos);
    }

    // ========================================================================
    // STROKE PARAMS BUILDER TESTS
    // ========================================================================

    public function testStrokeParamsFluentBuilder(): void
    {
        $params = StrokeParams::solid(2.0)
            ->cap(DrawLineCap::Round)
            ->join(DrawLineJoin::Round)
            ->miterLimit(4.0)
            ->dashed([3.0, 2.0], 1.0);
        $cdata = $params->toCData();

        $this->assertSame(DrawLineCap::Round->value, $cdata->Cap);
        $this->assertSame(DrawLineJoin::Round->value, $cdata->Join);
        $this->assertSame(2.0, $cdata->Thickness);
        $this->assertSame(4.0, $cdata->MiterLimit);
        $this->assertSame(1.0, $cdata->DashPhase);
        $this->assertSame(2, $cdata->NumDashes);
    }

    public function testStrokeParamsThicknessReturnsSelf(): void
    {
        $params = new StrokeParams();
        $result = $params->thickness(5.0);

        $this->assertSame($params, $result);
        $this->assertSame(5.0, $params->thickness);
    }

    public function testStrokeParamsDashedSetsDashesAndPhase(): void
    {
        $params = new StrokeParams()->dashed([4.0, 4.0], 2.0);
        $cdata = $params->toCData();

        $this->assertSame(2, $cdata->NumDashes);
        $this->assertSame(2.0, $cdata->DashPhase);
        $this->assertSame([4.0, 4.0], $params->dashes);
    }

    public function testStrokeParamsBuilderDoesNotBreakConstructor(): void
    {
        $params = new StrokeParams(
            cap: DrawLineCap::Square,
            join: DrawLineJoin::Bevel,
            thickness: 3.0,
        );

        $this->assertSame(3.0, $params->thickness);
        $this->assertSame(DrawLineCap::Square, $params->cap);
        $this->assertSame(DrawLineJoin::Bevel, $params->join);
    }

    // ========================================================================
    // PATH SUGAR TESTS
    // ========================================================================

    public function testPathLineCreatesFigure(): void
    {
        $path = new Path();
        $result = $path->line(0.0, 0.0, 100.0, 100.0);
        $path->end();

        $this->assertSame($path, $result);
    }

    public function testPathCircleBuildsClosedFigure(): void
    {
        $path = new Path();
        $result = $path->circle(50.0, 50.0, 25.0);
        $path->end();

        $this->assertSame($path, $result);
    }

    public function testPathEllipseBuilds(): void
    {
        $path = new Path();
        $result = $path->ellipse(50.0, 50.0, 40.0, 20.0);
        $path->end();

        $this->assertSame($path, $result);
    }

    public function testPathRoundedRectBuilds(): void
    {
        $path = new Path();
        $result = $path->roundedRect(0.0, 0.0, 100.0, 60.0, 10.0);
        $path->end();

        $this->assertSame($path, $result);
    }

    public function testPathRoundedRectClampsRadius(): void
    {
        $path = new Path();
        // Radius larger than half the smaller side; should clamp, not error.
        $result = $path->roundedRect(0.0, 0.0, 40.0, 20.0, 1000.0);
        $path->end();

        $this->assertSame($path, $result);
    }

    public function testPathQuadToBuilds(): void
    {
        $path = new Path();
        $path->newFigure(0.0, 0.0);
        $result = $path->quadTo(50.0, 100.0, 100.0, 0.0);
        $path->end();

        $this->assertSame($path, $result);
    }

    public function testPathBezierThroughBuilds(): void
    {
        $path = new Path();
        $result = $path->bezierThrough(0.0, 0.0, 25.0, 50.0, 75.0, 50.0, 100.0, 0.0);
        $path->end();

        $this->assertSame($path, $result);
    }

    public function testPathSugarChaining(): void
    {
        $path = new Path();
        $result = $path
            ->line(0.0, 0.0, 10.0, 10.0)
            ->circle(50.0, 50.0, 20.0)
            ->roundedRect(0.0, 0.0, 30.0, 30.0, 5.0);
        $path->end();

        $this->assertSame($path, $result);
    }

    // ========================================================================
    // DRAWCONTEXT PAINT COERCION (seam)
    // ========================================================================

    public function testDrawContextPaintCoercionColorToBrush(): void
    {
        // DrawContext::brush() simply delegates to Brush::color(); assert the
        // equivalence on the reachable seam.
        $color = Color::rgb(0x80_4020, 0.5);
        // Hold the Brush: toCData() retains the struct on the instance.
        $brush = Brush::color($color);
        $cdata = $brush->toCData();

        $this->assertSame(DrawBrushType::Solid->value, $cdata->Type);
        $this->assertEqualsWithDelta(0x80 / 255, $cdata->R, 1e-9);
        $this->assertEqualsWithDelta(0x40 / 255, $cdata->G, 1e-9);
        $this->assertEqualsWithDelta(0x20 / 255, $cdata->B, 1e-9);
        $this->assertEqualsWithDelta(0.5, $cdata->A, 1e-9);
    }

    // ========================================================================
    // AREA DELEGATE (no-area no-op; live cases in AreaDelegateTest)
    // ========================================================================

    public function testAreaDelegateRedrawNoAreaIsNoop(): void
    {
        $delegate = new class extends \Libui\AreaDelegate {};

        $this->assertNull($delegate->area());
        $delegate->redraw(); // no Area bound yet; must be a silent no-op

        $this->assertNull($delegate->area());
    }
}
