<?php

declare(strict_types=1);

/**
 * A read-only data grid in a few lines via Table::fromRows().
 *
 * No TableModelDelegate to implement, and no manual model cleanup — the model's
 * lifetime is handled automatically (Ffi::uninit(), called by run(), frees it).
 * For dynamic or computed data, extend TableModelDelegate instead — see
 * docs/GUIDE.md "Tables (data grids)".
 *   php examples/table.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\Box;
use Libui\Ffi;
use Libui\Table;
use Libui\Window;

Ffi::init();

$table = Table::fromRows(
    [
        ['Rasmus Lerdorf',     'PHP',        '1995'],
        ['Yukihiro Matsumoto', 'Ruby',       '1995'],
        ['Guido van Rossum',   'Python',     '1991'],
        ['Brendan Eich',       'JavaScript', '1995'],
        ['Anders Hejlsberg',   'C#',         '2000'],
    ],
    headers: ['Author', 'Language', 'Year'],
);

$window = new Window('PHP libui — data grid', 480, 240);
$box = new Box();
$box->appendStretchy($table); // fill the window
$window->setChild($box);

$window->run();
