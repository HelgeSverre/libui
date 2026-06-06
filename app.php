<?php

declare(strict_types=1);

/**
 * Example PHP desktop GUI app.
 *
 * This is the working, PHP-8.5 equivalent of the old `ext-ui` book example
 * (https://www.php.net/manual/en/book.ui.php). That extension is PHP 7-only
 * and unmaintained, so instead we drive the maintained libui-ng C library
 * directly via FFI. The result is a real native Cocoa window on macOS.
 *
 * Run it:  php app.php
 */

require __DIR__ . '/src/Ui.php';

$ui = new Ui();
$ui->init();

// --- build the window -------------------------------------------------------
$window = $ui->uiNewWindow('PHP + libui-ng (FFI)', 460, 200, 0);
$ui->uiWindowSetMargined($window, 1);

// A padded vertical stack of controls.
$box = $ui->uiNewVerticalBox();
$ui->uiBoxSetPadded($box, 1);

$prompt  = $ui->uiNewLabel('What is your name?');
$entry   = $ui->uiNewEntry();
$button  = $ui->uiNewButton('Greet me');
$result  = $ui->uiNewLabel('');

$ui->uiBoxAppend($box, $ui->control($prompt), 0);
$ui->uiBoxAppend($box, $ui->control($entry),  0);
$ui->uiBoxAppend($box, $ui->control($button), 0);
$ui->uiBoxAppend($box, $ui->control($result), 0);

$ui->uiWindowSetChild($window, $ui->control($box));

// --- behaviour --------------------------------------------------------------
// Clicking the button reads the entry and updates the result label.
$ui->uiButtonOnClicked($button, $ui->keepCallback(function ($sender, $data) use ($ui, $entry, $result) {
    $name = trim($ui->entryText($entry));
    $greeting = $name === '' ? 'Hello, mysterious stranger!' : "Hello, {$name}!";
    $ui->uiLabelSetText($result, $greeting);
    fwrite(STDOUT, "[click] {$greeting}\n");
}), null);

// Closing the window stops the event loop so php exits cleanly.
$ui->uiWindowOnClosing($window, $ui->keepCallback(function ($sender, $data) use ($ui) {
    fwrite(STDOUT, "[close] window closing, quitting\n");
    $ui->quit();
    return 1; // tell libui to destroy the window
}), null);

// --- show & run -------------------------------------------------------------
fwrite(STDOUT, "Opening native window… (close it to exit)\n");
$ui->uiControlShow($ui->control($window));
$ui->main();

$ui->uiUninit();
fwrite(STDOUT, "Goodbye.\n");
