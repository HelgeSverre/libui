<?php

declare(strict_types=1);

namespace Libui;

/**
 * Async event loop integration with libui's native event loop.
 *
 * A thin, ergonomic layer over {@see Ffi}'s loop primitives for scheduling work
 * on the GUI thread without freezing it:
 *
 *   Loop::defer(fn () => print "next tick\n");
 *   Loop::delay(1000, fn () => print "after one second\n");
 *   $id = Loop::repeat(100, fn () => print "every 100ms\n");
 *   Loop::cancel($id);
 *
 * Cancellation is *lazy*: libui's uiTimer has no cancel call (a timer stops only
 * by returning 0 from its own callback). So after cancel() the user callback is
 * never invoked again, and the native timer stops on its next wake-up. For a
 * one-shot delay cancelled before it fires, the callback simply never runs.
 *
 * For full async integration, drive your event loop (Revolt/ReactPHP/Amp) from a
 * short Loop::repeat() tick and marshal completions back with Loop::defer().
 */
final class Loop
{
    /**
     * Live timer IDs. A timer's wrapper checks this set on every wake-up; once an
     * ID is gone the wrapper returns false and the native timer stops.
     *
     * @var array<int, true>
     */
    private static array $timers = [];

    /** Next timer ID handed out by delay()/repeat(). */
    private static int $nextId = 1;

    /** Whether the native event loop is currently running (between run() and stop()). */
    private static bool $running = false;

    /**
     * Schedule a callback to run once on the next event-loop tick.
     *
     * Equivalent to {@see Ffi::queueMain()} with a more descriptive name. Use it
     * to hand work back to the GUI thread (e.g. applying the result of background
     * I/O to a widget).
     *
     * @param callable $callback The callback to invoke
     */
    public static function defer(callable $callback): void
    {
        Ffi::queueMain($callback);
    }

    /**
     * Schedule a callback to run once after a delay.
     *
     * @param int $milliseconds Delay in milliseconds (must be >= 0)
     * @param callable $callback The callback to invoke
     * @return int A timer ID usable with {@see Loop::cancel()}
     * @throws \InvalidArgumentException If $milliseconds is negative
     */
    public static function delay(int $milliseconds, callable $callback): int
    {
        if ($milliseconds < 0) {
            throw new \InvalidArgumentException('delay() milliseconds must be >= 0');
        }

        $id = self::$nextId++;
        self::$timers[$id] = true;

        Ffi::timer($milliseconds, static function () use ($id, $callback): bool {
            // Cancelled before it fired — do nothing and stop the native timer.
            if (! isset(self::$timers[$id])) {
                return false;
            }
            // One-shot: consume the ID before running, so re-entrant cancel() is a no-op.
            unset(self::$timers[$id]);
            $callback();
            return false;
        });

        return $id;
    }

    /**
     * Schedule a callback to run repeatedly at a fixed interval.
     *
     * The callback fires every $milliseconds until it returns false, it is
     * cancelled, or it throws (a throw is reported to STDERR and stops the timer).
     *
     * @param int $milliseconds Interval in milliseconds (must be > 0)
     * @param callable $callback The callback to invoke; return false to stop
     * @return int A timer ID usable with {@see Loop::cancel()}
     * @throws \InvalidArgumentException If $milliseconds is not positive
     */
    public static function repeat(int $milliseconds, callable $callback): int
    {
        if ($milliseconds <= 0) {
            throw new \InvalidArgumentException('repeat() milliseconds must be > 0');
        }

        $id = self::$nextId++;
        self::$timers[$id] = true;

        Ffi::timer($milliseconds, static function () use ($id, $callback): bool {
            // Cancelled (externally or by a previous self-cancel) — stop the native timer.
            if (! isset(self::$timers[$id])) {
                return false;
            }

            // Explicit stop request from the callback. A callback that cancels
            // itself but returns true is caught by the isset check on the next wake.
            if ($callback() === false) {
                unset(self::$timers[$id]);
                return false;
            }

            return true;
        });

        return $id;
    }

    /**
     * Cancel a scheduled timer.
     *
     * After this returns the callback for $id will not be invoked again. The
     * underlying native timer stops lazily on its next wake-up. Cancelling an
     * unknown or already-finished ID is a harmless no-op.
     *
     * @param int $id The timer ID returned by delay() or repeat()
     */
    public static function cancel(int $id): void
    {
        unset(self::$timers[$id]);
    }

    /**
     * Whether the native event loop is currently running.
     *
     * True between {@see Loop::run()} and {@see Loop::stop()} (i.e. inside any
     * callback dispatched by the loop).
     */
    public static function isRunning(): bool
    {
        return self::$running;
    }

    /**
     * Run the event loop until {@see Loop::stop()} is called or all windows close.
     *
     * Equivalent to {@see Ffi::main()} with running-state tracking.
     */
    public static function run(): void
    {
        self::$running = true;
        try {
            Ffi::main();
        } finally {
            self::$running = false;
        }
    }

    /**
     * Signal the event loop to quit.
     *
     * Equivalent to {@see Ffi::quit()}.
     */
    public static function stop(): void
    {
        Ffi::quit();
    }
}
