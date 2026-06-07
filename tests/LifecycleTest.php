<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Ffi;

/**
 * Exercise the async event-loop helpers headlessly (no window).
 *
 * libui's main loop still drains queued tasks and fires timers without any
 * window open, so each test runs the loop and lets the callback quit it once
 * its assertion condition is met.
 */
final class LifecycleTest extends LibuiTestCase
{
    public function testQueueMainRunsTheQueuedCallback(): void
    {
        $ran = false;

        Ffi::queueMain(static function () use (&$ran): void {
            $ran = true;
            Ffi::quit();
        });
        Ffi::main();

        $this->assertTrue($ran, 'the queued callback should have run on the loop tick');
    }

    public function testTimerFiresUntilItReturnsFalse(): void
    {
        $ticks = 0;

        Ffi::timer(10, static function () use (&$ticks): bool {
            if (++$ticks >= 3) {
                Ffi::quit();
                return false; // stop the timer
            }
            return true; // keep firing
        });
        Ffi::main();

        $this->assertGreaterThanOrEqual(3, $ticks);
    }
}
