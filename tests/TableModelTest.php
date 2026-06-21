<?php

declare(strict_types=1);

namespace Libui\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for the uiTableModel leak: libui's allocation tracker
 * aborts inside uiUninit() (SIGTRAP) when a uiTableModel created via
 * uiNewTableModel() is never freed with uiFreeTableModel().
 *
 * uiUninit() aborts the whole process, so the lifecycle can't run in the shared
 * PHPUnit process — each case is driven through tests/table_lifecycle.php in its
 * own subprocess and asserted by exit code. The 'leak' runner traps the SIGTRAP
 * and _exit()s with a non-zero code, so the abort stays a clean process exit
 * instead of an OS-level crash (no ReportCrash .ips, no "quit unexpectedly").
 */
final class TableModelTest extends TestCase
{
    /** Run the lifecycle runner in $mode and return its exit code. */
    private function runLifecycle(string $mode): int
    {
        // PHP_BINARY must be escaped too — e.g. Herd's path has spaces
        // ("…/Application Support/Herd/bin/php85"), which the shell would split.
        $cmd = escapeshellarg(\PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/table_lifecycle.php') . ' ' . escapeshellarg($mode);

        // 2>/dev/null swallows libui's "[libui] You have a bug" leak line. The
        // 'leak' runner converts libui's SIGTRAP into a clean exit itself (see
        // table_lifecycle.php), so no signal-death noise reaches this shell.
        $output = [];
        $code = 0;
        exec($cmd . ' 2>/dev/null', $output, $code);

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

    public function testForgottenFreeDoesNotAbort(): void
    {
        // No explicit free(): Ffi::uninit() must free the model via the Lifecycle
        // registry and exit cleanly — the footgun is neutralised.
        $this->assertSame(
            0,
            $this->runLifecycle('auto'),
            'a forgotten free() must be swept up by Lifecycle::freeAll() in uninit()',
        );
    }

    public function testLeakDetectorStillLiveWhenUnregistered(): void
    {
        // libui's leak-abort for an unfreed uiTableModel lives in darwin/table.m;
        // the GTK/Windows backends free quietly, so this negative control is macOS-only.
        if (\PHP_OS_FAMILY !== 'Darwin') {
            $this->markTestSkipped('uiTableModel leak-abort in uiUninit() is macOS-only.');
        }

        // The 'leak' runner unregisters the model from Lifecycle so freeAll() can't
        // rescue it; the detector must still fire, proving the negative control.
        $this->assertNotSame(
            0,
            $this->runLifecycle('leak'),
            'an unregistered, unfreed model must still abort in uiUninit()',
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
