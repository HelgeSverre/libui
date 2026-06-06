<?php

declare(strict_types=1);

/**
 * Menubar demo on the generated OO layer.
 *   php examples/menu.php
 *
 * On macOS the menu titles show up in the SYSTEM menu bar at the top of the
 * screen (only while this app is frontmost), not inside the window itself.
 *
 * CRITICAL libui ordering rule: every menu must be created BEFORE the first
 * window. So we build all the menus first, then create the window with
 * hasMenubar = true.
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\Box;
use Libui\Ffi;
use Libui\Generated\Ui;
use Libui\Label;
use Libui\Menu;
use Libui\Window;

Ffi::init();

// --- 1. Build the menus FIRST (before any window) ------------------------

$fileMenu = new Menu('File');
$open = $fileMenu->appendItem('Open…');
$fileMenu->appendSeparator();
$quit = $fileMenu->appendQuitItem();

$editMenu = new Menu('Edit');
$cut = $editMenu->appendItem('Cut');
$copy = $editMenu->appendItem('Copy');
$paste = $editMenu->appendItem('Paste');
$editMenu->appendSeparator();
$editMenu->appendCheckItem('Word Wrap');
$disabled = $editMenu->appendItem('Unavailable');
$disabled->disable(); // demonstrate the generated MenuItem::disable()

$helpMenu = new Menu('Help');
$about = $helpMenu->appendAboutItem();

// --- 2. NOW create the window (menus are locked in) ----------------------

$window = new Window('Menu demo', 480, 300, true);
$window->setMargined(true);

$box = new Box();
$box->setPadded(true);
$box->append(new Label('Use the menu bar at the top of the screen.'), 0);

$window->setChild($box);

// --- 3. Wire the menu items ----------------------------------------------
//
// NOTE: uiMenuItemOnClicked hands the callback TWO args: ($item, $rawWindow).
// The 2nd arg is a raw uiWindow* CData, NOT a \Libui\Window — do not pass it
// to the Ui facade. Capture our own $window via `use` instead.

$open->onClicked(function ($item, $win) use ($window) {
    $path = Ui::openFile($window);
    if ($path !== '') {
        Ui::msgBox($window, 'You picked', $path);
    }
});

$about->onClicked(function ($item, $win) use ($window) {
    Ui::msgBox($window, 'About', 'PHP libui menu demo');
});

foreach (['Cut' => $cut, 'Copy' => $copy, 'Paste' => $paste] as $name => $entry) {
    $entry->onClicked(function ($item, $win) use ($name) {
        fwrite(STDOUT, "[menu] {$name}\n");
    });
}

$window->onClosing(function () {
    fwrite(STDOUT, "[close] quitting\n");
    Ffi::quit();
    return true;
});

fwrite(STDOUT, "Opening menu demo… (close the window to exit)\n");
$window->show();
Ffi::main();
Ffi::uninit();
