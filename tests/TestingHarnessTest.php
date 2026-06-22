<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Button;
use Libui\Testing\CallbackSpy;
use Libui\Testing\Inspect;

/**
 * Tests for the headless GUI testing harness (CallbackSpy + Inspect).
 */
final class TestingHarnessTest extends LibuiTestCase
{
    // ------------------------------------------------------------------
    // CallbackSpy
    // ------------------------------------------------------------------

    public function testCallbackSpyStartsEmpty(): void
    {
        $spy = new CallbackSpy();

        $this->assertSame(0, $spy->count());
        $this->assertFalse($spy->called());
        $this->assertSame([], $spy->calls());
    }

    public function testCallbackSpyRecordsInvocationsAndArguments(): void
    {
        $spy = new CallbackSpy();

        $spy('first', 1);
        $spy('second', 2);

        $this->assertSame(2, $spy->count());
        $this->assertTrue($spy->called());
        $this->assertSame(['first', 1], $spy->argsOf(0));
        $this->assertSame(['second', 2], $spy->argsOf(1));
        $this->assertSame(['second', 2], $spy->lastArgs());
        $this->assertSame([['first', 1], ['second', 2]], $spy->calls());
    }

    public function testCallbackSpyForwardsToDelegateAndReturnsValue(): void
    {
        $spy = new CallbackSpy(static fn (int $a, int $b): int => $a + $b);

        $result = $spy(2, 3);

        $this->assertSame(5, $result);
        $this->assertSame([2, 3], $spy->lastArgs());
    }

    public function testCallbackSpyReturnsNullWithoutDelegate(): void
    {
        $spy = new CallbackSpy();

        $this->assertNull($spy('x'));
    }

    public function testCallbackSpyResetForgetsCalls(): void
    {
        $spy = new CallbackSpy();
        $spy('a');
        $spy->reset();

        $this->assertSame(0, $spy->count());
        $this->assertFalse($spy->called());
    }

    public function testCallbackSpyArgsOfThrowsOutOfRange(): void
    {
        $spy = new CallbackSpy();

        $this->expectException(\OutOfRangeException::class);
        $spy->argsOf(0);
    }

    public function testCallbackSpyIsUsableAsWidgetHandler(): void
    {
        // A spy must be passable wherever libui expects a callable handler.
        $spy = new CallbackSpy();
        $button = new Button('Click');

        $result = $button->onClicked($spy);

        $this->assertSame($button, $result);
    }

    // ------------------------------------------------------------------
    // Inspect
    // ------------------------------------------------------------------

    public function testInspectReflectsEnableAndDisable(): void
    {
        $button = new Button('Toggle');

        $button->enable();
        $this->assertTrue(Inspect::isEnabled($button));

        $button->disable();
        $this->assertFalse(Inspect::isEnabled($button));
    }

    public function testInspectReflectsShowAndHide(): void
    {
        $button = new Button('Toggle');

        $button->hide();
        $this->assertFalse(Inspect::isVisible($button));

        $button->show();
        $this->assertTrue(Inspect::isVisible($button));
    }

    public function testInspectIsToplevelReturnsBool(): void
    {
        $button = new Button('Plain');

        // A bare button is not a toplevel.
        $this->assertFalse(Inspect::isToplevel($button));
    }

    public function testInspectCallbacksRegisteredByMeasuresDelta(): void
    {
        $button = new Button('Counted');

        $registered = Inspect::callbacksRegisteredBy(
            static fn () => $button->onClicked(static function (): void {}),
        );

        $this->assertSame(1, $registered);
    }
}
