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

    /**
     * beginUserWindowMove()/beginUserWindowResize() cannot be exercised here:
     * libui's Unix/GTK backend aborts the process if they are called outside a
     * live mouse-down handler (../unix/area.c). So we assert the public API
     * surface via reflection instead of invoking the native calls.
     */
    public function testWindowDragMethodsAreDeclaredWithExpectedSignatures(): void
    {
        $move = new \ReflectionMethod(Area::class, 'beginUserWindowMove');
        $this->assertTrue($move->isPublic());
        $this->assertSame(0, $move->getNumberOfParameters());
        $this->assertSame('void', (string) $move->getReturnType());

        $resize = new \ReflectionMethod(Area::class, 'beginUserWindowResize');
        $this->assertTrue($resize->isPublic());
        $this->assertSame('void', (string) $resize->getReturnType());
        $edge = $resize->getParameters()[0] ?? null;
        $this->assertNotNull($edge);
        $this->assertSame(WindowResizeEdge::class, (string) $edge->getType());
    }

    private function makeScrollingArea(): Area
    {
        $delegate = new class extends AreaDelegate {};

        return Area::scrolling($delegate, 800, 600);
    }
}
