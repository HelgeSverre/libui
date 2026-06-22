<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Window;

/**
 * Cover the public content-size/position facades that promote the raw libui
 * out-pointer getters into ordinary [int, int] return values.
 */
final class WindowSizeTest extends LibuiTestCase
{
    public function testGetContentSizeReturnsTwoInts(): void
    {
        $window = new Window('Size', 320, 240, false);

        $size = $window->getContentSize();

        $this->assertCount(2, $size);
        $this->assertArrayHasKey(0, $size);
        $this->assertArrayHasKey(1, $size);
    }

    public function testGetContentSizeFallsBackToConstructedSize(): void
    {
        // Before layout (no show()), Unix may report non-positive values, in
        // which case the constructed dimensions are returned. Either way the
        // result must be positive, never zero/negative.
        $window = new Window('Fallback', 320, 240, false);

        [$width, $height] = $window->getContentSize();

        $this->assertGreaterThan(0, $width);
        $this->assertGreaterThan(0, $height);
    }

    public function testGetPositionReturnsTwoInts(): void
    {
        $window = new Window('Position', 320, 240, false);

        $position = $window->getPosition();

        $this->assertCount(2, $position);
        $this->assertArrayHasKey(0, $position);
        $this->assertArrayHasKey(1, $position);
    }
}
