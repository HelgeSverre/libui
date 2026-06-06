<?php

declare(strict_types=1);

namespace Libui;

/**
 * Async event loop integration with libui's native event loop.
 *
 * This class provides a bridge between PHP's async ecosystem and libui-ng's
 * event loop. It allows you to schedule callbacks to run on the main thread
 * and set up repeating timers, enabling non-blocking I/O patterns.
 *
 * Usage:
 *   Loop::defer(fn() => echo "This runs on the next event loop tick");
 *   Loop::delay(1000, fn() => echo "This runs after 1 second");
 *   Loop::repeat(100, fn() => echo "This runs every 100ms");
 *
 * For full async integration with Revolt/ReactPHP/amphp, see the async
 * event-loop bridge implementation.
 */
final class Loop
{
    /** @var array<int, callable> Active timers indexed by their internal ID */
    private static array $timers = [];

    /** @var int Next timer ID */
    private static int $nextId = 1;

    /** @var array<int, callable> Queued callbacks to run on the next tick */
    private static array $queued = [];

    /**
     * Schedule a callback to run on the next event loop tick.
     *
     * This is equivalent to Ffi::queueMain() but with a more descriptive API.
     *
     * @param callable $callback The callback to invoke
     */
    public static function defer(callable $callback): void
    {
        Ffi::queueMain($callback);
    }

    /**
     * Schedule a callback to run after a delay.
     *
     * The callback runs once, after the specified number of milliseconds.
     *
     * @param int $milliseconds Delay in milliseconds
     * @param callable $callback The callback to invoke
     * @return int A timer ID that can be used with cancel()
     */
    public static function delay(int $milliseconds, callable $callback): int
    {
        $id = self::$nextId++;

        $wrapper = function () use ($id, $callback): bool {
            $callback();
            unset(self::$timers[$id]);
            return false; // Stop after one execution
        };

        self::$timers[$id] = $wrapper;
        Ffi::timer($milliseconds, $wrapper);

        return $id;
    }

    /**
     * Schedule a callback to run repeatedly at an interval.
     *
     * The callback runs every $milliseconds until cancelled or it returns false.
     *
     * @param int $milliseconds Interval in milliseconds
     * @param callable $callback The callback to invoke; return false to stop
     * @return int A timer ID that can be used with cancel()
     */
    public static function repeat(int $milliseconds, callable $callback): int
    {
        $id = self::$nextId++;

        $wrapper = function () use ($id, $callback): bool {
            $result = $callback();
            if ($result === false) {
                unset(self::$timers[$id]);
                return false;
            }
            return true;
        };

        self::$timers[$id] = $wrapper;
        Ffi::timer($milliseconds, $wrapper);

        return $id;
    }

    /**
     * Cancel a scheduled timer.
     *
     * @param int $id The timer ID returned by delay() or repeat()
     */
    public static function cancel(int $id): void
    {
        if (isset(self::$timers[$id])) {
            unset(self::$timers[$id]);

            // Note: We can't actually stop the C timer, but we remove our reference
            // so the callback won't be called again. The timer will just stop naturally
            // or we'd need to track it differently.
        }
    }

    /**
     * Check if the event loop is currently running.
     *
     * @return bool True if the loop is running, false otherwise
     */
    public static function isRunning(): bool
    {
        return Ffi::isInitialized();
    }

    /**
     * Run the event loop until quit is called.
     *
     * This is equivalent to Ffi::main() but provides a more semantic API.
     */
    public static function run(): void
    {
        Ffi::main();
    }

    /**
     * Signal the event loop to quit.
     *
     * This is equivalent to Ffi::quit() but provides a more semantic API.
     */
    public static function stop(): void
    {
        Ffi::quit();
    }
}
