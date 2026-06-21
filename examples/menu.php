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

use Libui\App;
use Libui\Box;
use Libui\Ffi;
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

$window = new Window('Menu demo', 480, 300, hasMenubar: true);
$window->setMargined(true);

$box = new Box(padded: true);
$box->append(new Label('Use the menu bar at the top of the screen.'));

$window->setChild($box);

// --- 3. Wire the menu items ----------------------------------------------
//
// onClick() hides libui's raw uiWindow* 2nd arg, handing your handler only the
// typed MenuItem. Window::dialogs() binds a Dialogs facade to this window, so
// you never repeat $parent and file choosers return ?string (null on cancel).

$dialogs = $window->dialogs();

$open->onClick(function (\Libui\MenuItem $item) use ($dialogs) {
    $path = $dialogs->openFile();
    if ($path !== null) {
        $dialogs->msgBox('You picked', $path);
    }
});

$about->onClick(fn (\Libui\MenuItem $item) => $dialogs->msgBox('About', 'PHP libui menu demo'));

foreach (['Cut' => $cut, 'Copy' => $copy, 'Paste' => $paste] as $name => $entry) {
    $entry->onClicked(function ($item, $win) use ($name) {
        // wire your edit actions here
    });
}

// --- 4. Run via the App facade -------------------------------------------

App::new()->window($window)->run();
