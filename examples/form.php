<?php

declare(strict_types=1);

/**
 * The original PoC form, rebuilt on the generated OO layer.
 *   php examples/form.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\Box;
use Libui\Button;
use Libui\Entry;
use Libui\Ffi;
use Libui\Label;
use Libui\Window;

Ffi::init();

$window = new Window('PHP libui — OO layer', 460, 200, false);
$window->setMargined(true);

$box = new Box(); // vertical
$box->setPadded(true);

$prompt = new Label('What is your name?');
$entry = new Entry();
$button = new Button('Greet me');
$result = new Label('');

$box->append($prompt, 0)->append($entry, 0)->append($button, 0)->append($result, 0);

$window->setChild($box);

$button->onClicked(function () use ($entry, $result) {
    $name = trim($entry->text());
    $result->setText($name === '' ? 'Hello, mysterious stranger!' : "Hello, {$name}!");
    fwrite(STDOUT, "[click] greeted '{$name}'\n");
});

$window->onClosing(function () {
    fwrite(STDOUT, "[close] quitting\n");
    Ffi::quit();
    return true;
});

fwrite(STDOUT, "Opening form… (close it to exit)\n");
$window->show();
Ffi::main();
Ffi::uninit();
