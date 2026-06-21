<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Exception\MenuOrderException;
use Libui\Menu;
use Libui\MenuItem;
use Libui\Window;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Tests for the hardened Menu facade: menu-ordering enforcement and the clean
 * single-argument onClick handler.
 *
 * Every test that creates a real libui menu (or a Window then a menu) runs in a
 * separate process: libui finalizes its menu list at first window creation and
 * aborts on any later uiNewMenu, so the shared PHPUnit process (where earlier
 * suites already created Windows) cannot host these. resetMenuLockForTesting()
 * keeps the PHP-side ordering checks order-independent; process isolation gives
 * each test a fresh libui session for the C-side menu list.
 */
#[Group('smoke')]
final class MenuTest extends LibuiTestCase
{
    protected function setUp(): void
    {
        Window::resetMenuLockForTesting();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMenuCreatedBeforeAnyWindowSucceeds(): void
    {
        $menu = new Menu('File');
        $this->assertInstanceOf(Menu::class, $menu);
    }

    public function testMenuCreatedAfterWindowThrowsMenuOrderException(): void
    {
        new Window('W', 100, 100, false);

        $this->expectException(MenuOrderException::class);
        $this->expectExceptionMessageMatches('/before the first window/i');

        new Menu('Late');
    }

    public function testMenuOrderExceptionIsLogicException(): void
    {
        $exception = new MenuOrderException('boom');
        $this->assertInstanceOf(\LogicException::class, $exception);
    }

    public function testWindowMenusLockedFlagFlipsOnFirstWindow(): void
    {
        $this->assertFalse(Window::menusLocked());
        new Window('W', 100, 100, false);
        $this->assertTrue(Window::menusLocked());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMultipleMenusBeforeWindowAllSucceed(): void
    {
        $a = new Menu('File');
        $b = new Menu('Edit');
        $c = new Menu('Help');

        $this->assertInstanceOf(Menu::class, $a);
        $this->assertInstanceOf(Menu::class, $b);
        $this->assertInstanceOf(Menu::class, $c);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMenuItemOnClickReturnsThisForChaining(): void
    {
        $menu = new Menu('File');
        $item = $menu->appendItem('X');

        $this->assertSame($item, $item->onClick(static fn () => null));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMenuItemOnClickAcceptsSingleArgHandler(): void
    {
        $menu = new Menu('File');
        $item = $menu->appendItem('Y');

        // Binding a single-arg handler must succeed and return the same item.
        $this->assertSame($item, $item->onClick(static fn (MenuItem $i) => null));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAppendItemWithInlineOnClickReturnsMenuItem(): void
    {
        $menu = new Menu('File');
        $item = $menu->appendItem('Open', static fn (MenuItem $i) => null);

        $this->assertInstanceOf(MenuItem::class, $item);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAppendCheckItemWithInlineOnClickReturnsMenuItem(): void
    {
        $menu = new Menu('View');
        $item = $menu->appendCheckItem('Toolbar', static fn (MenuItem $i) => null);

        $this->assertInstanceOf(MenuItem::class, $item);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testOnClickHandlerExceptionIsCaughtNotFatal(): void
    {
        $menu = new Menu('File');
        $item = $menu->appendItem('Boom');

        // Binding a throwing handler must not throw at bind time; the actual
        // invocation (which needs the event loop) swallows the exception.
        $result = $item->onClick(static function (MenuItem $i): void {
            throw new \RuntimeException('boom');
        });

        $this->assertSame($item, $result, 'Binding a throwing handler should not throw');
    }
}
