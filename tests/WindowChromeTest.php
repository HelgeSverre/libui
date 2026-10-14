<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Box;
use Libui\Generated\Enum\WindowCornerStyle;
use Libui\Label;
use Libui\Window;

/**
 * Custom-chrome window API (titlebar drag handle, corner style, shadow).
 * Behavioral/visual effects are platform-native and not asserted headlessly;
 * these cover the binding surface (fluent returns, enum round-trip, defaults).
 */
final class WindowChromeTest extends LibuiTestCase
{
    public function testSetTitlebarReturnsSameInstance(): void
    {
        $window = new Window('Chrome', 320, 240, false);
        $bar = new Box(padded: false)->append(new Label('drag me'));

        $this->assertSame($window, $window->setBorderless(true)->setTitlebar($bar));
    }

    public function testCornerStyleRoundTrips(): void
    {
        $window = new Window('Chrome', 320, 240, false);

        $this->assertSame($window, $window->setCornerStyle(WindowCornerStyle::Rounded));
        $this->assertSame(WindowCornerStyle::Rounded, $window->getCornerStyle());

        $window->setCornerStyle(WindowCornerStyle::RoundedSmall);
        $this->assertSame(WindowCornerStyle::RoundedSmall, $window->getCornerStyle());
    }

    public function testShadowDefaultsOnAndToggles(): void
    {
        $window = new Window('Chrome', 320, 240, false);

        $this->assertTrue($window->shadow(), 'borderless chrome windows keep a shadow by default');
        $this->assertSame($window, $window->setShadow(false));
        $this->assertFalse($window->shadow());
        $window->setShadow(true);
        $this->assertTrue($window->shadow());
    }
}
