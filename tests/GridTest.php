<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Button;
use Libui\Generated\Enum\Align;
use Libui\Grid;

/**
 * The hand-written Grid ergonomics sugar (appendAt/place) over the generated
 * 9-positional-arg append().
 */
final class GridTest extends LibuiTestCase
{
    public function testAppendAtWithDefaults(): void
    {
        $grid = new Grid();
        $result = $grid->appendAt(new Button('a'), 0, 0);

        $this->assertSame($grid, $result);
    }

    public function testAppendAtWithBoolExpandAndAlign(): void
    {
        $grid = new Grid();
        $result = $grid->appendAt(new Button('b'), 1, 2, xspan: 2, hexpand: true, halign: Align::Center);

        $this->assertSame($grid, $result);
    }

    public function testPlaceIsASingleCellShorthand(): void
    {
        $grid = new Grid();
        $result = $grid->place(new Button('c'), 3, 4);

        $this->assertSame($grid, $result);
    }
}
