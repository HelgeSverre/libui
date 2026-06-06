<?php

declare(strict_types=1);

namespace Libui\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for the uiTableModel leak: libui's allocation tracker
 * aborts inside uiUninit() (SIGTRAP, exit 133) when a uiTableModel created via
 * uiNewTableModel() is never freed with uiFreeTableModel().
 *
 * uiUninit() aborts the whole process, so the lifecycle can't run in the shared
 * PHPUnit process — each case is driven through tests/table_lifecycle.php in its
 * own subprocess and asserted by exit code.
 */
final class TableModelTest extends TestCase
{
    /** Run the lifecycle runner in $mode and return its exit code. */
    private function runLifecycle(string $mode): int
    {
        // PHP_BINARY must be escaped too — e.g. Herd's path has spaces
        // ("…/Application Support/Herd/bin/php85"), which the shell would split.
        $cmd = escapeshellarg(\PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/table_lifecycle.php') . ' ' . escapeshellarg($mode);

        // The 'leak' case dies from SIGTRAP (libui's uiUninit() leak-abort). A
        // bare `2>/dev/null` only redirects php's stderr — the `sh -c` parent
        // that exec() spawns reaps the signalled child and prints its own
        // "Trace/BPT trap: 5" diagnostic to *its* stderr, which leaks past the
        // redirect. Wrapping in a `{ …; }` group runs the command in that same
        // shell with the redirect covering the reap, silencing the report while
        // preserving the exit code. (A `( … )` subshell does NOT work: sh
        // exec-optimises the lone command, so the outer shell still reports.)
        $output = [];
        $code = 0;
        exec('{ ' . $cmd . ' ; } 2>/dev/null', $output, $code);

        return $code;
    }

    public function testFreedModelLetsUninitExitCleanly(): void
    {
        $this->assertSame(
            0,
            $this->runLifecycle('freed'),
            'TableModel::free() should release the model so uiUninit() exits cleanly',
        );
    }

    public function testFreeIsIdempotent(): void
    {
        $this->assertSame(
            0,
            $this->runLifecycle('double-free'),
            'calling free() twice must be a no-op, not a double-free abort',
        );
    }

    public function testUnfreedModelAbortsInUninit(): void
    {
        // libui's leak-abort for an unfreed uiTableModel lives in darwin/table.m;
        // the GTK/Windows backends free quietly, so this negative control is macOS-only.
        if (\PHP_OS_FAMILY !== 'Darwin') {
            $this->markTestSkipped('uiTableModel leak-abort in uiUninit() is macOS-only.');
        }

        // Negative control: without free() libui's leak checker must still fire,
        // otherwise the passing cases above would prove nothing.
        $this->assertNotSame(
            0,
            $this->runLifecycle('leak'),
            'an unfreed model must still abort in uiUninit() (leak detector is live)',
        );
    }
}
