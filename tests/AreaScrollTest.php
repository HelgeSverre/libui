<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Area;
use Libui\AreaDelegate;
use Libui\Generated\Enum\WindowResizeEdge;

/**
 * Live tests for Area scrolling and window-drag operations. These need a real
 * uiArea, so they extend {@see LibuiTestCase} (which runs uiInit()).
 */
final class AreaScrollTest extends LibuiTestCase
{
    public function testScrollingAreaHasNonNullHandle(): void
    {
        $area = $this->makeScrollingArea();

        $this->assertFalse(\FFI::isNull($area->handle()));
    }

    public function testScrollToDoesNotThrow(): void
    {
        $area = $this->makeScrollingArea();

        $area->scrollTo(10.0, 20.0, 100.0, 50.0);

        $this->assertFalse(\FFI::isNull($area->handle()));
    }

    public function testBeginUserWindowMoveDoesNotThrow(): void
    {
        $area = $this->makeScrollingArea();

        $area->beginUserWindowMove();

        $this->assertFalse(\FFI::isNull($area->handle()));
    }

    public function testBeginUserWindowResizeDoesNotThrow(): void
    {
        $area = $this->makeScrollingArea();

        $area->beginUserWindowResize(WindowResizeEdge::Bottom);

        $this->assertFalse(\FFI::isNull($area->handle()));
    }

    private function makeScrollingArea(): Area
    {
        $delegate = new class extends AreaDelegate {};

        return Area::scrolling($delegate, 800, 600);
    }
}
