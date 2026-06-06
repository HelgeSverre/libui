<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Button;
use Libui\Checkbox;
use Libui\Control;
use Libui\Entry;
use Libui\Label;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the base Control class that all widgets inherit from.
 * Verifies the common uiControl verbs and the callback retention mechanism.
 */
#[Group('smoke')]
final class ControlTest extends LibuiTestCase
{
    public function testHandleReturnsNonNullFfiCData(): void
    {
        $button = new Button('Test');
        $handle = $button->handle();

        $this->assertInstanceOf(\FFI\CData::class, $handle);
        $this->assertFalse(\FFI::isNull($handle));
    }

    public function testAsControlReturnsUiControlPointer(): void
    {
        $button = new Button('Test');
        $control = $button->asControl();

        $this->assertInstanceOf(\FFI\CData::class, $control);
        $this->assertFalse(\FFI::isNull($control));
    }

    public function testVisibleReturnsBoolean(): void
    {
        $button = new Button('Test');
        $this->assertIsBool($button->visible());
    }

    public function testEnabledDefaultsToTrueForNewWidgets(): void
    {
        $button = new Button('Test');
        $this->assertTrue($button->enabled());
    }

    public function testToplevelDefaultsToFalseForChildWidgets(): void
    {
        $button = new Button('Test');
        $this->assertFalse($button->toplevel());
    }

    public function testShowReturnsThisForChaining(): void
    {
        $button = new Button('Test');
        $result = $button->show();

        $this->assertSame($button, $result);
    }

    public function testHideReturnsThisForChaining(): void
    {
        $button = new Button('Test');
        $result = $button->hide();

        $this->assertSame($button, $result);
    }

    public function testEnableReturnsThisForChaining(): void
    {
        $button = new Button('Test');
        $button->disable();
        $result = $button->enable();

        $this->assertSame($button, $result);
    }

    public function testDisableReturnsThisForChaining(): void
    {
        $button = new Button('Test');
        $result = $button->disable();

        $this->assertSame($button, $result);
    }

    public function testEnableDisableRoundTrip(): void
    {
        $button = new Button('Test');

        $this->assertTrue($button->enabled());

        $button->disable();
        $this->assertFalse($button->enabled());

        $button->enable();
        $this->assertTrue($button->enabled());
    }

    public function testDestroyDoesNotThrow(): void
    {
        $button = new Button('Test');
        $button->destroy();

        $this->assertTrue(true, 'destroy() should complete without throwing');
    }

    public function testCallbackRetentionBindsWithoutError(): void
    {
        $button = new Button('Test');

        $button->onClicked(function (): void {});

        $this->assertTrue(true, 'onClicked should bind without error');
    }

    public function testControlIsAbstractBaseClass(): void
    {
        $this->assertTrue(
            new \ReflectionClass(Control::class)->isAbstract(),
            'Control should be an abstract base class',
        );
    }

    public function testAllWidgetsExtendControl(): void
    {
        $widgets = [
            Button::class,
            Label::class,
            Checkbox::class,
            Entry::class,
        ];

        foreach ($widgets as $widgetClass) {
            $this->assertTrue(
                is_subclass_of($widgetClass, Control::class),
                "$widgetClass should extend Control",
            );
        }
    }
}
