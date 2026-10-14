<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Draw\Params\AreaScrollEvent;
use Libui\Ffi;

/**
 * uiAreaMouseScrollEvent marshalling. Also pins that the new MouseScrolled
 * handler field and its event struct exist in the generated FFI cdef.
 */
final class AreaScrollEventTest extends LibuiTestCase
{
    public function testFromCDataMapsEveryField(): void
    {
        $e = Ffi::get()->new('uiAreaMouseScrollEvent');
        $e->X = 12.0;
        $e->Y = 34.0;
        $e->AreaWidth = 640.0;
        $e->AreaHeight = 480.0;
        $e->DeltaX = -1.5;
        $e->DeltaY = 2.0;
        $e->Modifiers = 0;

        $event = AreaScrollEvent::fromCData($e);

        $this->assertSame(12.0, $event->x);
        $this->assertSame(34.0, $event->y);
        $this->assertSame(640.0, $event->areaWidth);
        $this->assertSame(480.0, $event->areaHeight);
        $this->assertSame(-1.5, $event->deltaX);
        $this->assertSame(2.0, $event->deltaY);
        $this->assertSame(0, $event->modifiers);
    }

    public function testHandlerStructExposesMouseScrolled(): void
    {
        // The appended optional callback must be present in uiAreaHandler so the
        // binding can bind it; setting it to null must not error.
        $handler = Ffi::get()->new('uiAreaHandler');
        $handler->MouseScrolled = null;

        $this->assertNull($handler->MouseScrolled);
    }
}
