<?php

declare(strict_types=1);

/**
 * A read-only data grid via the hand-written Table adapter.
 *
 * Shows a few programming languages in a three-column table (Name / Language /
 * Year), driven by a concrete TableModelDelegate that returns plain strings.
 *   php examples/table.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\Box;
use Libui\Ffi;
use Libui\Table;
use Libui\TableModelDelegate;
use Libui\Window;

Ffi::init();

$delegate = new class extends TableModelDelegate {
    /** @var array<int, array{string,string,string}> Name / Language / Year */
    private array $rows = [
        ['Rasmus Lerdorf',     'PHP',        '1995'],
        ['Yukihiro Matsumoto', 'Ruby',       '1995'],
        ['Guido van Rossum',   'Python',     '1991'],
        ['Brendan Eich',       'JavaScript', '1995'],
        ['Anders Hejlsberg',   'C#',         '2000'],
    ];

    public function numColumns(): int
    {
        return 3;
    }

    public function numRows(): int
    {
        return \count($this->rows);
    }

    public function cellValue(int $row, int $column): string|int
    {
        return $this->rows[$row][$column] ?? '';
    }
};

$table = Table::fromDelegate($delegate)
    ->appendTextColumn('Author', 0)
    ->appendTextColumn('Language', 1)
    ->appendTextColumn('Year', 2);

$window = new Window('PHP libui — data grid', 480, 240, false);
$box = new Box();
$box->append($table, 1); // stretchy: fill the window
$window->setChild($box);

$window->onClosing(function () {
    Ffi::quit();
    return true;
});

fwrite(STDOUT, "Opening table… (close the window to exit)\n");
$window->show();
Ffi::main();

// Closing the window destroyed it (and its child table) — see onClosing above —
// so the model is now detached and must be freed before uiUninit(), otherwise
// libui's leak checker aborts on the orphaned uiTableModel.
$table->model()->free();
Ffi::uninit();
