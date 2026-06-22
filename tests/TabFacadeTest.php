<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Label;
use Libui\Tab;

/**
 * Tests for the hand-written Tab convenience facade (appendMargined, pages).
 */
final class TabFacadeTest extends LibuiTestCase
{
    public function testAppendIncrementsNumPages(): void
    {
        $tab = new Tab();
        $this->assertSame(0, $tab->numPages());

        $tab->append('A', new Label('a'));
        $this->assertSame(1, $tab->numPages());

        $tab->append('B', new Label('b'));
        $this->assertSame(2, $tab->numPages());
    }

    public function testAppendMarginedMarksPageMarginedAndReturnsSelf(): void
    {
        $tab = new Tab();

        $result = $tab->appendMargined('A', new Label('a'));

        $this->assertSame($tab, $result);
        $this->assertSame(1, $tab->numPages());
        $this->assertTrue($tab->margined(0));
    }

    public function testPagesAppendsAllInOrderAndReturnsSelf(): void
    {
        $tab = new Tab();
        $c1 = new Label('one');
        $c2 = new Label('two');

        $result = $tab->pages(['A' => $c1, 'B' => $c2]);

        $this->assertSame($tab, $result);
        $this->assertSame(2, $tab->numPages());
    }
}
