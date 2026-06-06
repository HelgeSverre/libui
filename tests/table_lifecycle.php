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
        break;
}

Ffi::uninit();
exit(0);
