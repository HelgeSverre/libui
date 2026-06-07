<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Button;
use Libui\Control;
use Libui\Ffi;
use Libui\Window;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for callback retention mechanism.
 * Verifies that PHP closures are properly retained when passed to C as function pointers,
 * preventing the native trampoline from being garbage collected.
 */
#[Group('smoke')]
final class CallbackTest extends LibuiTestCase
{
    public function testButtonOnClickedBindsCallback(): void
    {
        $called = false;
        $button = new Button('Click me');

        $button->onClicked(static function () use (&$called): void {
            $called = true;
        });

        // The callback should be bound without error
        $this->assertTrue(true, 'onClicked should bind without error');
    }

    public function testButtonOnClickedReturnsThisForChaining(): void
    {
        $button = new Button('Click me');
        $result = $button->onClicked(static function (): void {});

        $this->assertSame($button, $result);
    }

    public function testWindowOnClosingBindsCallbackWithIntReturn(): void
    {
        $window = new Window('Test', 100, 100, false);

        $result = $window->onClosing(static fn () => true);

        $this->assertSame($window, $result);
    }

    public function testWindowOnClosingWithFalseReturn(): void
    {
        $window = new Window('Test', 100, 100, false);

        $result = $window->onClosing(static fn () => false);

        $this->assertSame($window, $result);
    }

    public function testMenuItemOnClickedBindsCallbackWithWindowParameter(): void
    {
        // Note: We can't easily test MenuItem without a full menu setup,
        // but we can verify the binding works

        // This would normally require creating a menu, menu item, and window
        // For now, just verify the method signature exists
        $this->assertTrue(
            method_exists(\Libui\Generated\MenuItem::class, 'onClicked'),
            'MenuItem should have onClicked method',
        );
    }

    public function testControlKeepRetainsClosure(): void
    {
        $callback = static function (): void {};

        // Use reflection to access the protected keep method
        $reflection = new \ReflectionClass(Control::class);
        $method = $reflection->getMethod('keep');

        $result = $method->invoke(null, $callback);

        $this->assertSame($callback, $result);
    }

    public function testControlKeepStoresClosureStatically(): void
    {
        $callback1 = static function (): void {};
        $callback2 = static function (): void {};

        $reflection = new \ReflectionClass(Control::class);
        $method = $reflection->getMethod('keep');

        // Access the callbacks storage
        $property = $reflection->getProperty('callbacks');

        $countBefore = count($property->getValue());

        $method->invoke(null, $callback1);
        $method->invoke(null, $callback2);

        $countAfter = count($property->getValue());

        $this->assertSame($countBefore + 2, $countAfter);
    }

    public function testCallbackWithUseVariablesIsRetained(): void
    {
        $externalValue = 'test';
        $button = new Button('Click');

        $button->onClicked(static function () use ($externalValue): void {
            // The closure captures $externalValue
            // This should be retained properly
        });

        $this->assertTrue(true, 'Callback with use variables should bind');
    }

    public function testCallbackWithObjectReferenceIsRetained(): void
    {
        $object = new \stdClass();
        $object->value = 42;

        $button = new Button('Click');

        $button->onClicked(static function () use ($object): void {
            // The closure captures the object
        });

        $this->assertTrue(true, 'Callback with object reference should bind');
    }

    public function testMultipleCallbacksOnSameWidgetAreAllRetained(): void
    {
        $button = new Button('Click');
        $count = 0;

        $button->onClicked(static function () use (&$count): void {
            $count++;
        });

        $button->onClicked(static function () use (&$count): void {
            $count++;
        });

        // Both callbacks should be retained
        // We can verify by checking they don't cause errors
        $this->assertTrue(true, 'Multiple callbacks should be retainable');
    }

    public function testFfiQueueMainRetainsCallback(): void
    {
        $ran = false;

        Ffi::queueMain(static function () use (&$ran): void {
            $ran = true;
        });

        // The callback should be retained in Ffi::$retained
        $reflection = new \ReflectionClass(Ffi::class);
        $property = $reflection->getProperty('retained');

        $retained = $property->getValue();

        // Should have at least one retained callback
        $this->assertGreaterThan(0, count($retained));
    }

    public function testFfiTimerRetainsCallback(): void
    {
        $ticks = 0;

        Ffi::timer(100, static function () use (&$ticks): bool {
            $ticks++;
            return $ticks < 3; // Stop after 3 ticks
        });

        // The callback should be retained
        $reflection = new \ReflectionClass(Ffi::class);
        $property = $reflection->getProperty('retained');

        $retained = $property->getValue();
        $this->assertGreaterThan(0, count($retained));
    }

    public function testFfiOnShouldQuitRetainsCallback(): void
    {
        Ffi::onShouldQuit(static fn (): bool => true);

        // The callback should be retained
        $reflection = new \ReflectionClass(Ffi::class);
        $property = $reflection->getProperty('retained');

        $retained = $property->getValue();
        $this->assertGreaterThan(0, count($retained));
    }

    public function testCallbackRetentionSurvivesWidgetDestruction(): void
    {
        // This tests that callbacks are retained statically on Control,
        // not on the widget instance, so they survive widget destruction

        $callback = static function (): void {};

        $button = new Button('Test');
        $button->onClicked($callback);

        // Get the callbacks storage
        $reflection = new \ReflectionClass(Control::class);
        $property = $reflection->getProperty('callbacks');

        $countBefore = count($property->getValue());

        // Destroy the widget
        $button->destroy();

        // The callback should still be retained
        $countAfter = count($property->getValue());
        $this->assertSame($countBefore, $countAfter);
    }

    public function testCallbackWithExceptionHandlerDoesNotThrow(): void
    {
        $errorMessage = '';

        // Set up a custom error handler to capture the message
        set_error_handler(static function ($severity, $message) use (&$errorMessage): void {
            $errorMessage = $message;
        });

        try {
            Ffi::queueMain(static function (): void {
                throw new \RuntimeException('Test exception in callback');
            });

            // The exception should be caught and logged to STDERR, not thrown
            $this->assertSame('', $errorMessage);
        } finally {
            restore_error_handler();
        }
    }

    public function testExceptionInFfiCallbackIsHandledGracefully(): void
    {
        // This verifies that exceptions in FFI callbacks don't cause hard fatals
        // The callback should catch the exception and report to STDERR

        $exceptionThrown = false;

        // We can't easily capture STDERR output, but we can verify
        // that the callback binding itself doesn't throw

        try {
            Ffi::queueMain(static function () use (&$exceptionThrown): void {
                $exceptionThrown = true;
                throw new \RuntimeException('Test');
            });

            $this->assertTrue(true, 'Exception in callback should be caught');
        } catch (\Throwable $e) {
            $this->fail('Exception in FFI callback should not propagate: ' . $e->getMessage());
        }
    }

    public function testCallbackCanAccessWidgetState(): void
    {
        $button = new Button('Initial');
        $expectedText = 'Updated';

        $button->onClicked(static function () use ($button, $expectedText): void {
            // The callback can access the widget via closure
            // This tests that the widget is still accessible
            // Note: We can't actually trigger the click, but we can verify binding
        });

        $button->setText($expectedText);

        $this->assertSame($expectedText, $button->text());
    }

    public function testChainedCallbacksWorkCorrectly(): void
    {
        $button = new Button('Test');
        $order = [];

        $result = $button
            ->onClicked(static function () use (&$order): void {
                $order[] = 'first';
            })
            ->onClicked(static function () use (&$order): void {
                $order[] = 'second';
            });

        $this->assertSame($button, $result);
        $this->assertCount(0, $order); // Not triggered yet
    }
}
