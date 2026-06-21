<?php

declare(strict_types=1);

/**
 * Control gallery — a tabbed tour of the generated OO widgets.
 *   php examples/gallery.php
 *
 * Three tab pages exercise the input, range, and chooser widgets. The Ranges
 * tab is live: dragging the slider drives the progress bar and the label.
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\Box;
use Libui\Checkbox;
use Libui\ColorButton;
use Libui\Combobox;
use Libui\DateTimePicker;
use Libui\EditableCombobox;
use Libui\Entry;
use Libui\Ffi;
use Libui\FontButton;
use Libui\Form;
use Libui\Label;
use Libui\MultilineEntry;
use Libui\ProgressBar;
use Libui\RadioButtons;
use Libui\Slider;
use Libui\Spinbox;
use Libui\Tab;
use Libui\Window;

Ffi::init();

$window = new Window('PHP libui — control gallery', 640, 480);
$window->setMargined(true);

$tab = new Tab();

// --- Page 1: Inputs -------------------------------------------------------
$inputs = new Form()->setPadded(true);

$name = new Entry();
$name->setText('Ada Lovelace');
$password = Entry::password();
$age = new Spinbox(0, 120);
$age->setValue(36);

$country = new Combobox();
$country->append('Norway')->append('Sweden')->append('Denmark')->append('Iceland')->append('Finland');
$country->setSelected(0);

$language = new EditableCombobox();
$language->append('PHP')->append('Rust')->append('Go')->append('Zig');
$language->setText('PHP');

$born = new DateTimePicker();

$inputs
    ->append('Name', $name)
    ->append('Password', $password)
    ->append('Age', $age)
    ->append('Country', $country)
    ->append('Language', $language)
    ->append('Born', $born);

$tab->append('Inputs', $inputs);

// --- Page 2: Ranges -------------------------------------------------------
$ranges = new Box(padded: true);

$slider = new Slider(0, 100);
$slider->setValue(25);
$slider->setHasToolTip(true);
$progress = new ProgressBar();
$progress->setValue(25);
$readout = new Label('Slider value: 25');

$slider->onChanged(function (Slider $s) use ($progress, $readout): void {
    $v = $s->value();
    $progress->setValue($v);
    $readout->setText("Slider value: {$v}");
});

$ranges
    ->append(new Label('Drag the slider to drive the progress bar:'))
    ->append($slider)
    ->append($progress)
    ->append($readout);

$tab->append('Ranges', $ranges);

// --- Page 3: Choosers -----------------------------------------------------
$choosers = new Box(padded: true);

$color = new ColorButton();
$color->setColor(0.2, 0.5, 0.9, 1.0);

$font = new FontButton();

$subscribe = new Checkbox('Subscribe to the newsletter');
$subscribe->setChecked(true);

$plan = new RadioButtons();
$plan->append('Free')->append('Pro')->append('Enterprise');
$plan->setSelected(1);

$notes = new MultilineEntry();
$notes->setText("Multiline notes…\nType something here.");

$choosers
    ->append(new Label('Pick a color:'))
    ->append($color)
    ->append(new Label('Pick a font:'))
    ->append($font)
    ->append($subscribe)
    ->append(new Label('Plan:'))
    ->append($plan)
    ->append(new Label('Notes:'))
    ->appendStretchy($notes);

$tab->append('Choosers', $choosers);

// Give every page an inner margin.
for ($i = 0, $n = $tab->numPages(); $i < $n; $i++) {
    $tab->setMargined($i, true);
}

$window->setChild($tab);

$window->run();
