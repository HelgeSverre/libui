<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Ffi;
use Libui\Loop;
use PHPUnit\Framework\Attributes\Group;

/**
 * Behavioural tests for the {@see Loop} async helpers.
 *
 * These drive libui's real event loop headlessly: each test schedules work, then
 * lets a timer call Loop::stop() so the loop drains and returns rather than
 * hanging. A short watchdog delay guards against a missed stop.
 */
#[Group('integration')]
final class LoopTest extends LibuiTestCase
{
    public function testDeferRunsOnNextTick(): void
    {
        $ran = false;

        Loop::defer(static function () use (&$ran): void {
            $ran = true;
            Loop::stop();
        });
        Loop::run();

        $this->assertTrue($ran, 'defer() callback should run on the next loop tick');
    }

    public function testDelayFiresOnce(): void
    {
        $count = 0;

        Loop::delay(5, static function () use (&$count): void {
            $count++;
        });
        // Stop well after the one-shot would have fired (and possibly repeated, if buggy).
        Loop::delay(60, static fn () => Loop::stop());
        Loop::run();

        $this->assertSame(1, $count, 'delay() must fire exactly once');
    }

    public function testDelayCancelledBeforeFiringNeverRuns(): void
    {
        $ran = false;

        $id = Loop::delay(40, static function () use (&$ran): void {
            $ran = true;
        });
        Loop::cancel($id);

        Loop::delay(80, static fn () => Loop::stop());
        Loop::run();

        $this->assertFalse($ran, 'a delay cancelled before it fires must not invoke its callback');
    }

    public function testRepeatStopsWhenCallbackReturnsFalse(): void
    {
        $ticks = 0;

        Loop::repeat(5, static function () use (&$ticks): bool {
            $ticks++;
            return $ticks < 3; // stop after the 3rd tick
        });
        Loop::delay(120, static fn () => Loop::stop());
        Loop::run();

        $this->assertSame(3, $ticks, 'repeat() should stop firing once the callback returns false');
    }

    public function testRepeatStopsWhenCancelled(): void
    {
        $ticks = 0;

        $id = Loop::repeat(5, static function () use (&$ticks): void {
            $ticks++;
        });

        // Cancel from a separate timer after a couple of ticks.
        Loop::delay(40, static function () use ($id): void {
            Loop::cancel($id);
        });
        Loop::delay(120, static fn () => Loop::stop());
        Loop::run();

        $ticksAtCancel = $ticks;
        $this->assertGreaterThanOrEqual(1, $ticks, 'repeat() should fire before being cancelled');

        // Give the loop more time and confirm no further ticks happened.
        Loop::delay(60, static fn () => Loop::stop());
        Loop::run();
        $this->assertSame($ticksAtCancel, $ticks, 'no further ticks should occur after cancel()');
    }

    public function testRepeatCanCancelItself(): void
    {
        $ticks = 0;
        $id = 0;

        $id = Loop::repeat(5, static function () use (&$ticks, &$id): void {
            $ticks++;
            if ($ticks === 2) {
                Loop::cancel($id);
            }
        });
        Loop::delay(120, static fn () => Loop::stop());
        Loop::run();

        $this->assertSame(2, $ticks, 'a repeat callback cancelling itself stops after the current tick');
    }

    public function testDelayRejectsNegativeDelay(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Loop::delay(-1, static fn () => null);
    }

    public function testRepeatRejectsNonPositiveInterval(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Loop::repeat(0, static fn () => null);
    }

    public function testIsRunningReflectsLoopState(): void
    {
        $insideLoop = null;

        $this->assertFalse(Loop::isRunning(), 'loop should not be running before run()');

        Loop::defer(static function () use (&$insideLoop): void {
            $insideLoop = Loop::isRunning();
            Loop::stop();
        });
        Loop::run();

        $this->assertTrue($insideLoop, 'isRunning() should be true inside a dispatched callback');
        $this->assertFalse(Loop::isRunning(), 'isRunning() should be false after the loop returns');
    }

    public function testCancelUnknownIdIsNoop(): void
    {
        $this->expectNotToPerformAssertions();
        Loop::cancel(999_999); // must not throw
    }

    public function testDistinctTimerIds(): void
    {
        $a = Loop::delay(1000, static fn () => null);
        $b = Loop::delay(1000, static fn () => null);
        Loop::cancel($a);
        Loop::cancel($b);

        $this->assertNotSame($a, $b, 'each scheduled timer should get a distinct id');
    }
}
