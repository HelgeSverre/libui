<?php

declare(strict_types=1);

/**
 * Subprocess runner for {@see \Libui\Tests\TableModelTest}.
 *
 * The uiTableModel leak is only detectable inside uiUninit(), which aborts the
 * whole process on failure — so it cannot be asserted in the shared PHPUnit
 * process (which never uninits). This script runs one full table lifecycle and
 * exits; the test class execs it per mode and asserts the exit code.
 *
 * Not a *Test.php file, so PHPUnit does not collect it as a test.
 *
 *   php tests/table_lifecycle.php <freed|leak|double-free>
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\Ffi;
use Libui\Table;
use Libui\TableModelDelegate;

/**
 * Convert libui's leak-abort into a clean in-process exit on macOS.
 *
 * When a uiTableModel leaks, libui reports it from uiUninit() via
 * __builtin_trap() — an EXC_BREAKPOINT / SIGTRAP. Left alone that escalates to
 * macOS' ReportCrash, which writes an .ips log and pops a "php quit
 * unexpectedly" dialog on every test run. Installing a SIGTRAP handler that
 * _exit()s intercepts the trap in-process (the task-level signal handler runs
 * before the host crash reporter), so the abort becomes a clean non-zero exit.
 *
 * The handler firing is itself the proof libui's leak detector fired: if libui
 * ever stopped aborting, uiUninit() would return and we'd reach exit(0) — and
 * the test would (correctly) fail. Best-effort: if the libc symbols can't be
 * resolved we just proceed and let the original trap stand; only the noise is
 * affected, never the pass/fail result.
 */
function silenceLeakTrap(): void
{
    if (\PHP_OS_FAMILY !== 'Darwin') {
        return;
    }

    $libc = \FFI::cdef(
        'typedef void (*sighandler_t)(int);'
        . 'sighandler_t signal(int signum, sighandler_t handler);'
        . 'void *dlopen(const char *path, int mode);'
        . 'void *dlsym(void *handle, const char *symbol);',
    );

    $handle = $libc->dlopen('/usr/lib/libSystem.B.dylib', 2); // RTLD_NOW
    if (\FFI::isNull($handle)) {
        return;
    }

    $exit = $libc->dlsym($handle, '_exit');
    if (\FFI::isNull($exit)) {
        return;
    }

    // SIGTRAP = 5. The handler is _exit itself; called as _exit(5) it exits the
    // process with code 5 (non-zero) without re-running the trapping instruction.
    $libc->signal(5, $libc->cast('sighandler_t', $exit));
}

$mode = $argv[1] ?? 'freed';

Ffi::init();

$delegate = new class extends TableModelDelegate {
    public function numColumns(): int
    {
        return 1;
    }

    public function numRows(): int
    {
        return 1;
    }

    public function cellValue(int $row, int $column): string
    {
        return 'x';
    }
};

$table = Table::fromDelegate($delegate)->appendTextColumn('A', 0);

// Detach the model from the live uiTable before freeing it (libui aborts if a
// model is freed while a table still uses it). Closing a real window does this
// for you by destroying the window's control tree.
$table->destroy();

switch ($mode) {
    case 'freed':
        $table->model()->free();
        break;
    case 'double-free':
        $table->model()->free();
        $table->model()->free(); // idempotent: must not double-free
        break;
    case 'leak':
        // Intentionally leave the model unfreed — uiUninit() must abort.
        // Trap that abort and turn it into a clean non-zero exit so it doesn't
        // escalate to the macOS crash reporter (see silenceLeakTrap()).
        silenceLeakTrap();
        break;
}

Ffi::uninit();
exit(0);
