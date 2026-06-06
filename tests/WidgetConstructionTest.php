<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Box;
use Libui\Button;
use Libui\Checkbox;
use Libui\Entry;
use Libui\Generated\Ui;
use Libui\Slider;
use Libui\Window;
use PHPUnit\Framework\Attributes\Group;

/**
 * The "smoke" suite: prove the generated OO layer constructs widgets, exposes
 * factories and the dialog facade, binds callbacks, and composes via the
 * uiControl upcast — all against the real library, no event loop.
 */
#[Group('smoke')]
final class WidgetConstructionTest extends LibuiTestCase
{
    public function testConstructorsProduceLiveHandles(): void
    {
        $this->assertFalse(\FFI::isNull(new Window('Smoke', 320, 200, false)->handle()));
        $this->assertFalse(\FFI::isNull(new Button('Click')->handle()));
        $this->assertFalse(\FFI::isNull(new Checkbox('Toggle')->handle()));
        $this->assertFalse(\FFI::isNull(new Slider(0, 100)->handle()));
        $this->assertFalse(\FFI::isNull(new Box()->handle()));
    }

    public function testStaticFactoriesProduceLiveHandles(): void
    {
        $this->assertFalse(\FFI::isNull(Box::horizontal()->handle()));
        $this->assertFalse(\FFI::isNull(Entry::password()->handle()));
        $this->assertFalse(\FFI::isNull(Entry::search()->handle()));
    }

    public function testDialogFacadeExposesMessageBox(): void
    {
        // Don't invoke it — it would block on a modal dialog.
        $this->assertTrue(method_exists(Ui::class, 'msgBox'));
        $this->assertTrue(method_exists(Ui::class, 'openFile'));
    }

    public function testEventHandlersBindFluently(): void
    {
        $button = new Button('Click');
        $window = new Window('W', 100, 100, false);

        // The standard callback and the int-return onClosing deviation both
        // bind without error and return $this for chaining.
        $this->assertSame($button, $button->onClicked(static fn () => null));
        $this->assertSame($window, $window->onClosing(static fn () => true));
    }

    public function testCompositionViaControlUpcast(): void
    {
        $box = new Box();
        $result = $box->append(new Button('a'), 0)->append(new Checkbox('b'), 0);

        $this->assertSame($box, $result);
        $this->assertFalse(\FFI::isNull($box->handle()));
    }
}
