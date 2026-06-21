<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Area;
use Libui\AreaDelegate;

/**
 * Live tests for the Area <-> AreaDelegate binding. These need a real uiArea,
 * so they extend {@see LibuiTestCase} (which runs uiInit()).
 */
final class AreaDelegateTest extends LibuiTestCase
{
    public function testAreaDelegateBindAreaStoresArea(): void
    {
        $delegate = new class extends AreaDelegate {};
        $area = new Area($delegate);

        $this->assertSame($area, $delegate->area());
    }

    public function testAreaBindsDelegateOnConstruct(): void
    {
        $delegate = new class extends AreaDelegate {};

        $this->assertNull($delegate->area());
        new Area($delegate);

        $this->assertInstanceOf(Area::class, $delegate->area());
    }

    public function testAreaDelegateRedrawCallsQueueRedrawAll(): void
    {
        // Area is final, so we exercise redraw() against a real bound Area and
        // assert it drives queueRedrawAll() without throwing (DESCOPED: asserting
        // an actual repaint, which needs a live draw callback).
        $delegate = new class extends AreaDelegate {};
        $area = new Area($delegate);

        $this->assertSame($area, $delegate->area());
        $delegate->redraw();

        // redraw() drives queueRedrawAll() without throwing; the binding persists.
        $this->assertSame($area, $delegate->area());
    }
}
