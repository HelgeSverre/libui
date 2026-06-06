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

$window = new Window('PHP libui — OO layer', 460, 200);
$window->setMargined(true);

$box = new Box(padded: true); // vertical

$prompt = new Label('What is your name?');
$entry = new Entry();
$button = new Button('Greet me');
$result = new Label('');

$box->append($prompt)->append($entry)->append($button)->append($result);

$window->setChild($box);

$button->onClicked(function () use ($entry, $result) {
    $name = trim($entry->text());
    $result->setText($name === '' ? 'Hello, mysterious stranger!' : "Hello, {$name}!");
});

$window->run();
