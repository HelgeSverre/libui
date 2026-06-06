<?php

declare(strict_types=1);

/**
 * The sender half of the "poor man's Ray" demo — a tiny ray() function that
 * ships variable dumps to the receiver window (examples/ray.php) over a local
 * TCP socket, plus a self-test.
 *
 * Use it from any script:
 *   require 'examples/ray-helper.php';
 *   ray('hello', 42, ['a' => 1], $someObject);
 *
 * Or run it directly to fire a handful of varied sample dumps at a running
 * receiver (handy for demoing the window without wiring up another app):
 *   php examples/ray-helper.php
 *
 * It opens a short-lived client per call and fails silently if nothing is
 * listening, so leaving ray() calls in code is harmless when the window is shut.
 */

if (! defined('RAY_HOST')) {
    define('RAY_HOST', '127.0.0.1');
}
if (! defined('RAY_PORT')) {
    define('RAY_PORT', 9919);
}

if (! function_exists('ray')) {
    /**
     * Send one debug dump per argument to the Ray receiver window.
     *
     * Each dump is a single JSON line: {type, value, caller, time}. The caller
     * is resolved from the backtrace (the file:line that invoked ray()), so the
     * window can show where each dump came from. Fails silently when no receiver
     * is listening — ray() is meant to be safe to leave in place.
     */
    function ray(mixed ...$args): void
    {
        // Resolve where ray() was called from (one frame up).
        $frame = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? [];
        $caller = isset($frame['file'])
            ? basename($frame['file']) . ':' . ($frame['line'] ?? '?')
            : '(unknown)';
        $time = date('H:i:s');

        // One short-lived connection carries all the args as newline-delimited
        // JSON. Suppress errors: no listener is a no-op, not a crash.
        $client = @stream_socket_client(
            'tcp://' . RAY_HOST . ':' . RAY_PORT,
            $errno,
            $errstr,
            0.2,
        );
        if ($client === false) {
            return;
        }

        $lines = '';
        foreach ($args as $arg) {
            $lines .=
                json_encode([
                    'type' => get_debug_type($arg),
                    'value' => ray_format($arg),
                    'caller' => $caller,
                    'time' => $time,
                ]) . "\n";
        }

        @fwrite($client, $lines);
        @fclose($client);
    }
}

if (! function_exists('ray_format')) {
    /**
     * Render a value to a compact, single-line-ish, truncated string for the
     * table cell. Scalars print directly; arrays/objects use print_r/var_export.
     */
    function ray_format(mixed $value, int $max = 400): string
    {
        $out = match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_null($value) => 'null',
            is_string($value) => $value,
            is_scalar($value) => var_export($value, true),
            is_array($value) => print_r($value, true),
            is_object($value) => print_r($value, true), // print_r already prints the class name
            default => print_r($value, true),
        };

        // Collapse whitespace so multi-line dumps fit on one table row.
        $out = trim((string) preg_replace('/\s+/', ' ', $out));

        if (mb_strlen($out) > $max) {
            $out = mb_substr($out, 0, $max) . '…';
        }

        return $out;
    }
}

// When run directly, fire a spread of varied sample dumps at the receiver.
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $object = (object) ['name' => 'Helge', 'role' => 'maker', 'awesome' => true];

    ray('Hello from a separate PHP process');
    ray(42);
    ray(3.14159);
    ray(true);
    ray(false);
    ray(null);
    ray(['php' => 8.5, 'ffi' => true, 'langs' => ['PHP', 'C', 'Swift']]);
    ray($object);
    ray('multiple', 'args', 'in', 'one', 'call');

    fwrite(STDOUT, 'Sent sample dumps to ' . RAY_HOST . ':' . RAY_PORT . "\n");
}
