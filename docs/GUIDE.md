# Libui for PHP — User Guide

A task-by-task guide to building native desktop apps with `helgesverre/libui`. It
assumes you've installed the package (`composer require helgesverre/libui`) and
have PHP 8.5+ with FFI. For *why* the library is built the way it is, read
[ARCHITECTURE.md](ARCHITECTURE.md); for the API reference, generate the API docs
(`composer docs:api`) or read the docblocks in your IDE.

## Contents

- [Hello, window](#hello-window)
- [Application lifecycle](#application-lifecycle)
- [Widgets](#widgets)
- [Layout: boxes, grids, forms, tabs](#layout-boxes-grids-forms-tabs)
- [Declarative layout — the `Build` facade](#declarative-layout--the-build-facade)
- [Events and callbacks](#events-and-callbacks)
- [Dialogs](#dialogs)
- [Menus](#menus)
- [Async — the event loop](#async--the-event-loop)
- [Colours](#colours)
- [Custom drawing](#custom-drawing)
- [Attributed text](#attributed-text)
- [Tables (data grids)](#tables-data-grids)
- [Images](#images)
- [Clipboard](#clipboard)
- [Testing your UI headlessly](#testing-your-ui-headlessly)
- [The raw FFI escape hatch](#the-raw-ffi-escape-hatch)
- [Gotchas](#gotchas)
- [Platform notes](#platform-notes)

---

## Hello, window

Every program touches three things: `Ffi::init()` to start libui, a `Window`,
and an event loop. `Window::run()` bundles all three for a single-window app:

```php
<?php
require 'vendor/autoload.php';

use Libui\Ffi;
use Libui\Window;
use Libui\Box;
use Libui\Entry;
use Libui\Button;

Ffi::init();

$entry  = new Entry();
$button = (new Button('Greet'))->onClicked(fn () => print "Hi, {$entry->text()}!\n");

(new Window('Greeter'))
    ->setChild(
        (new Box(padded: true))
            ->append($entry)
            ->append($button),
    )
    ->run();
```

`run()` shows the window, runs the loop until the window closes, then tears libui
down. You don't manage the loop yourself.

> **Note:** `fn () => print "…"` works because `print` is an *expression*.
> `fn () => echo "…"` is a **syntax error** — `echo` is a statement. Use `print`
> or a full `function () { echo "…"; }` body.

---

## Application lifecycle

There are three layers; pick the highest one that fits.

### `Window::run()` — one-call single window

```php
$window->run(); // init → show → loop → uninit
```

Pass an `$afterClose` callback to free native resources that must outlive the
window's child controls (see [the table gotcha](#model-lifetime-is-handled-for-you)):

```php
$window->run(function () use ($model) {
    $model->free();
});
```

`onClose()` registers cleanup that runs *before* the app quits, without you
managing the loop or return value:

```php
$window->onClose(fn () => print "saving…\n")->run();
```

### `App` — multiple windows, app-level quit handler

```php
use Libui\App;

App::new()
    ->window($mainWindow)        // first window drives app lifetime
    ->window($paletteWindow)
    ->onShouldQuit(fn () => $document->isSaved()) // return false to veto quit
    ->run();
```

Closing the first registered window quits the app; other windows just close.

### `Ffi` — manual control

When you need full control of the loop (e.g. integrating another event system),
drive it yourself:

```php
Ffi::init();
$window->show();
Ffi::main();   // blocks until Ffi::quit()
Ffi::uninit();
```

`Ffi::init()` is idempotent and must run before any widget is constructed.
`Ffi::uninit()` must run *after* `Ffi::main()` returns; if you want to use libui
again afterwards you must `init()` again.

---

## Widgets

Every widget is a typed class extending `Libui\Control`, with fluent setters and
IDE autocompletion. The common verbs live on `Control`:

```php
$widget->show();      $widget->hide();
$widget->enable();    $widget->disable();
$widget->visible();   $widget->enabled();   $widget->toplevel();
$widget->destroy();   // frees the native control
```

A quick tour of the most common widgets and their sugar:

```php
use Libui\{Button, Checkbox, Entry, Label, Slider, Spinbox, ProgressBar, Combobox};

$label  = new Label('Name');
$entry  = (new Entry())->setText('hello');           // ->text(), ->onChanged(fn)
$pw     = Entry::password();                          // factory variants
$search = Entry::search();
$check  = (new Checkbox('Enabled'))->setChecked(true); // ->checked(), ->onToggled(fn)
$slider = (new Slider(0, 100))->setValue(50);          // ->value(), ->onChanged(fn), ->onReleased(fn)
$spin   = (new Spinbox(0, 10))->onChanged(fn () => /* … */ null);
$bar    = (new ProgressBar())->setValue(40);
$combo  = (new Combobox())->append('A')->append('B');  // ->onSelected(fn)
```

`Entry`, `Combobox`, `DateTimePicker` and friends expose factory constructors
(`Entry::password()`, `DateTimePicker::dateOnly()`, …) for their libui variants.

---

## Layout: boxes, grids, forms, tabs

libui has no absolute positioning — you compose containers.

### Box — vertical or horizontal stack

```php
use Libui\Box;

$col = (new Box(padded: true))     // vertical by default
    ->append($header)              // non-stretchy
    ->appendStretchy($body)        // grows to fill the main axis
    ->append($footer);

$row = Box::horizontal(padded: true)
    ->append($left)
    ->appendStretchy($right);
```

`append(Control $child, bool|int $stretchy = false)` — pass `true` (or use the
clearer `appendStretchy($child)`) to let a child grow along the main axis. An int
is accepted too, so legacy `append($child, 1)` still works.

### Grid — 2D placement

```php
use Libui\Grid;
use Libui\Generated\Enum\Align;
use Libui\Generated\Enum\At;

$grid = new Grid();
// append(child, left, top, xspan, yspan, hexpand, halign, vexpand, valign)
$grid->append($label, 0, 0, 1, 1, 0, Align::Start, 0, Align::Center);
$grid->append($entry, 1, 0, 1, 1, 1, Align::Fill,  0, Align::Center);
```

### Form — labelled rows

```php
use Libui\Form;

$form = new Form();
$form->setPadded(true);
$form->append('Name', $nameEntry);
$form->appendStretchy('Bio', $bioEntry); // this row grows vertically
```

**Bulk binding.** Input widgets implement `HasValue` (`value()`/`setValue()`), and
a `Form` tracks its fields by label, so you can read and write a whole form at once
instead of wiring each control:

```php
$form->setValues(['Name' => 'Ada', 'Subscribe' => true, 'Age' => 36]);

$data = $form->values(); // ['Name' => 'Ada', 'Subscribe' => true, 'Age' => 36, …]
```

`setValues()` ignores unknown labels and non-value controls, so a partial map is
fine. Each widget keeps its typed accessors (`text()`, `checked()`, `getColor()`, …)
— `value()`/`setValue()` are just the uniform layer for binding.

### Tab — paged container

```php
use Libui\Tab;

$tab = (new Tab())
    ->append('General', $generalBox)
    ->append('Advanced', $advancedBox);
```

`appendMargined($name, $child)` appends a page *and* turns on its margin in one
step, and `pages([...])` appends a whole ordered `title => control` map:

```php
$tab = (new Tab())->pages([
    'General'  => $generalBox,
    'Advanced' => $advancedBox,
]);

$tab->appendMargined('About', $aboutBox); // padded page, no manual setMargined()
$tab->setSelected(0);                      // select the first page
```

### Group — titled frame

```php
use Libui\Group;

$group = (new Group('Settings'))->setChild($box);
```

---

## Declarative layout — the `Build` facade

`Libui\Build` is a thin, declarative layer over `Box`, `Form` and `Window`: it
trades the imperative `->append()` chains for nested constructor calls, so the PHP
reads like the layout tree it builds. It's additive — every widget underneath is
the same one you'd make by hand.

```php
use Libui\Build;

$form = Build::form([
    'Name'  => new Entry(),
    'Email' => new Entry(),
]);

$layout = Build::vbox(
    new Label('Welcome'),
    $form,
    Build::stretchy(new MultilineEntry()), // grows to fill the box
);

$window = Build::window('Demo', 640, 480, $layout);
$window->run();
```

The pieces:

- **`Build::vbox(...$children)` / `Build::hbox(...$children)`** — a *padded* box
  (vertical / horizontal) with each child appended in order. For an unpadded box,
  drop to `new Box(false)` / `Box::horizontal(false)`.
- **`Build::stretchy($control)`** — wrap a child to mark it stretchy inside a
  `vbox`/`hbox` (the equivalent of `appendStretchy()`). Bare controls are
  non-stretchy. The returned marker is opaque — only `vbox`/`hbox` consume it.
- **`Build::form(['Title' => $control, …])`** — a padded `Form` built from an
  ordered `label => control` map.
- **`Build::window($title, $width, $height, $child, $margined = true)`** — a
  top-level `Window` with its single child set and margins on by default.

---

## Events and callbacks

Handlers are registered with `on…()` methods and receive the widget as the first
argument:

```php
$button->onClicked(function (Button $self) { /* … */ });
$entry->onChanged(function (Entry $self) { print $self->text(); });
$slider->onChanged(fn (Slider $s) => print $s->value());
$window->onClosing(function (): bool {
    // return true to allow close, false to keep the window open
    return true;
});
```

Callbacks are **retained for the process lifetime** automatically (see
[the callback gotcha](#closures-passed-to-c-are-kept-forever)). You don't manage
their lifetime, but be aware they never get garbage-collected.

Any exception thrown inside a handler is **caught and reported to STDERR**, not
propagated — a PHP exception unwinding into libui's C trampoline would crash the
process. If your handler can fail, handle it yourself; don't rely on an exception
bubbling up to a caller, because there is no PHP caller.

---

## Dialogs

Dialogs are static methods on the generated `Ui` facade and take a parent window:

```php
use Libui\Generated\Ui;

$path = Ui::openFile($window);     // '' if cancelled
$dir  = Ui::openFolder($window);
$save = Ui::saveFile($window);

Ui::msgBox($window, 'Done', 'The file was saved.');
Ui::msgBoxError($window, 'Oops', 'Could not write the file.');
```

A cancelled chooser returns an empty string.

---

## Menus

Menus are application-global and **must be created before the first window** —
this is now enforced: constructing a `Menu` after a `Window` exists throws a
`MenuOrderException` instead of silently doing nothing.

Wire items with `onClick(fn (MenuItem $item) => …)` — a clean handler that doesn't
leak libui's raw window pointer. Pair it with a `Dialogs` facade bound to your
window so you don't pass the parent on every call (and `openFile()`/`saveFile()`
return `null` on cancel, distinct from an empty pick):

```php
use Libui\Menu;
use Libui\Dialogs;

$file = new Menu('File');
$file->appendItem('Open…');            // wire onClick after the window exists (below)
$file->appendQuitItem();               // standard items support onClick() too

$window = new Window('App', 640, 480, hasMenubar: true);
$dialogs = Dialogs::for($window);

$file->appendItem('Save As…')->onClick(function () use ($dialogs) {
    $path = $dialogs->saveFile();
    if ($path !== null) {
        $dialogs->msgBox('Saved', $path);
    }
});
```

See [`examples/menu.php`](../examples/menu.php) for a full menubar wired to
dialogs.

---

## Async — the event loop

libui is single-threaded: long blocking work in a callback freezes the UI. The
`Loop` class schedules work on the GUI thread without blocking it.

```php
use Libui\Loop;

Loop::defer(fn () => print "next tick\n");                 // run once, ASAP
Loop::delay(1000, fn () => print "after one second\n");    // run once, later
$id = Loop::repeat(100, fn () => print "every 100ms\n");   // repeat
Loop::cancel($id);                                          // stop it
```

Semantics that matter:

- **`repeat()` stops** when the callback returns `false`, when you `cancel()` it,
  or when it throws (the throw is logged to STDERR and the timer stops).
- **Cancellation is lazy.** libui's native timer has no cancel call (a timer stops
  only by returning 0 from its own tick). After `Loop::cancel($id)` the *callback
  never fires again*; the native timer stops on its next wake-up. A one-shot
  `delay()` cancelled before it fires simply never runs.
- **`Loop::run()` / `Loop::stop()`** are semantic aliases for `Ffi::main()` /
  `Ffi::quit()`; `Loop::isRunning()` is true between them.

### Real async I/O

For network calls, don't block in a callback. Drive an async HTTP client
(ReactPHP, Amp, Guzzle's curl-multi) from a short `Loop::repeat()` tick and
marshal completions back to widgets with `Loop::defer()`:

```php
Loop::repeat(10, function () use ($client) {
    $client->tick(); // advance the async client one step
});

// in the client's completion handler:
Loop::defer(fn () => $statusLabel->setText('done'));
```

See [`examples/async_http.php`](../examples/async_http.php) for the pattern.

---

## Colours

`Libui\Color` is the one typed way to express a colour. Construct it however you
think about colour — it stores normalized `0..1` channels internally (what libui
wants):

```php
use Libui\Color;

Color::rgb(0x312B90);            // hex int
Color::hex('#312B90');           // string: #RGB / #RRGGBB / #RRGGBBAA
Color::rgba(0.19, 0.17, 0.56);   // 0..1 floats
Color::rgb255(49, 43, 144);      // 8-bit ints
Color::black(); Color::white(); Color::transparent();

$faded = Color::rgb(0x312B90)->withAlpha(0.5);   // immutable — returns a new Color
```

Out-of-range floats are clamped to `0..1`; bad hex/8-bit values throw an
`InvalidArgumentException` (almost always a typo). A `Color` is accepted anywhere
the binding takes a colour:

```php
Brush::color(Color::hex('#312B90'));               // fills
Attribute::fromColor(Color::rgb(0xE01515));        // text colour
Attribute::backgroundFromColor(Color::white());    // text background
$ctx->drawString('Hi', $font, Color::white(), 10, 10);  // also still accepts [r,g,b,a]
$colorButton->setColor(Color::rgb(0x3380E6));
$current = $colorButton->getColor();               // returns a Color
```

The older float/hex forms (`Brush::solid()`, `Brush::rgb()`, `[r, g, b, a]`
arrays) all still work — `Color` is additive.

## Custom drawing

For anything libui doesn't have a widget for — charts, canvases, custom controls
— use an `Area` driven by an `AreaDelegate`.

```php
use Libui\Area;
use Libui\AreaDelegate;
use Libui\Color;
use Libui\Draw\DrawContext;
use Libui\Draw\Brush;
use Libui\Draw\Path;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\Params\AreaMouseEvent;
use Libui\Text\FontDescriptor;

$delegate = new class extends AreaDelegate {
    public function draw(DrawContext $ctx, AreaDrawParams $p): void
    {
        // Fill the background.
        $ctx->fillPath(
            Brush::rgb(0x0F172A),
            fn (Path $path) => $path->addRectangle(0, 0, $p->areaWidth, $p->areaHeight),
        );

        // A one-line text helper (no AttributedString dance needed):
        $ctx->drawString('Hello', new FontDescriptor('Helvetica', 24.0), Color::white(), 20, 20);
    }

    public function mouse(AreaMouseEvent $e): void
    {
        // $e->x, $e->y, $e->down, $e->up, …
    }
};

$area = new Area($delegate);          // or Area::scrolling($delegate, $w, $h)
$area->queueRedrawAll();              // request a repaint
```

Key pieces:

- **`DrawContext`** is valid only for the duration of one `draw()` call. Don't
  stash it.
- **`fillPath()` / `strokePath()`** build, paint, and free a `Path` for you. Use
  raw `Path` + `fill()`/`stroke()` if you need to reuse a path.
- **`Brush::rgb(0xRRGGBB)`**, `Brush::solid($r, $g, $b, $a)`, and
  `Brush::linearGradient(...)` / `Brush::radialGradient(...)` paint fills.
- **`save()` / `restore()` / `clip()` / `transform()`** manage clip and transform
  state with a `Matrix`.

Shape & shortcut sugar (all additive — the primitives above still work):

- **`Path`** has `line()`, `circle()`, `arc()`, `roundedRect()`, and bézier
  helpers, so you rarely hand-roll `newFigure`/`lineTo`/`arcTo` sequences.
- **`DrawContext`** has `fillRect()`, `strokeRect()`, `fillCircle()`,
  `strokeCircle()` — each takes a `Brush` *or* a `Color` directly.
- **Gradients** accept typed `Stop`s built from `Color`:
  `Brush::linearGradient($x0, $y0, $x1, $y1, [Stop::at(0.0, Color::rgb(0x312B90)), Stop::at(1.0, Color::rgb(0x0296C3))])`.
- **`StrokeParams`** has a fluent builder:
  `StrokeParams::solid(2.0)->cap(DrawLineCap::Round)->join(DrawLineJoin::Round)`.
- **`AreaDelegate::redraw()`** repaints the bound area without you stashing the
  `Area` reference yourself.

### Scrolling areas

A plain `Area` is sized by its container. For content larger than the viewport,
build a **scrolling** area with an explicit virtual size — libui then manages the
scrollbars and only `draw()`s the visible region:

```php
$area = Area::scrolling($delegate, 2000, 1500); // virtual width × height
$area->setSize(3000, 2000);                      // resize the scrollable canvas
$area->scrollTo(0, 800, 400, 300);               // bring a rectangle into view
```

`setSize()` / `scrollTo()` are no-ops on a non-scrolling area.

### Dragging and resizing the window from an Area

An `Area` can drive a frameless/custom-chrome window: from a mouse-down handler
you ask libui to take over the OS move/resize loop.

```php
use Libui\Generated\Enum\WindowResizeEdge;

public function mouse(AreaMouseEvent $e): void
{
    if ($e->down === 1) {
        $this->redraw();                 // (your own state)
        $area->beginUserWindowMove();    // drag the window
        // or: $area->beginUserWindowResize(WindowResizeEdge::BottomRight);
    }
}
```

> **Mouse-down only.** `beginUserWindowMove()` / `beginUserWindowResize()` **must**
> be called only from inside an `AreaDelegate::mouse()` handler while a mouse
> button is held down (a "down" event). libui's Unix/GTK backend **hard-aborts the
> process** if either is called at any other time (outside a mouse handler, on a
> mouse-up, from a timer, etc.). macOS is more forgiving, but write for the strict
> backend: only ever start a move/resize in direct response to a live mouse-down.

See [`examples/canvas.php`](../examples/canvas.php),
[`examples/flowfield.php`](../examples/flowfield.php), and
[`examples/clock.php`](../examples/clock.php).

## Custom-chrome windows

For Raycast/Spotlight-style windows — borderless, but with an app-defined
titlebar, rounded corners, and a drop shadow — combine `setBorderless(true)` with
the chrome API:

```php
use Libui\Generated\Enum\WindowCornerStyle;

$bar = (new Box())->append(new Label('My App'))->append($closeButton);

$window = new Window('My App', 720, 480);
$window
    ->setBorderless(true)
    ->setCornerStyle(WindowCornerStyle::Rounded)   // None | Rounded | RoundedSmall
    ->setChild($content);
$window->setTitlebar($bar);   // drag this control to move the window
$window->setShadow(true);     // on by default for borderless windows
```

- **`setTitlebar(Control)`** marks a child as the drag handle — dragging its empty
  areas moves the window, while buttons/entries inside it keep working. (On macOS
  the whole window background drags; Windows/GTK honor the specific control.)
- **`setCornerStyle(WindowCornerStyle)`** rounds the window. The enum is a preset
  (`None`/`Rounded`/`RoundedSmall`) so it behaves consistently everywhere — Windows
  only exposes presets via DWM.
- **`setShadow(bool)`** restores the drop shadow a borderless window would lose.

**Cross-platform support (best-effort, R9 — the window always works regardless):**

| | Windows | macOS | Linux/GTK |
|---|---|---|---|
| Rounded corners | Windows 11 (square on Win10) | yes | compositor-dependent |
| Shadow | yes (DWM) | yes | compositor-dependent |
| Titlebar drag / resize | yes | yes | X11 yes; Wayland limited |

[`examples/palette.php`](../examples/palette.php) is built on this API.

---

## Attributed text

Rich text is built from an `AttributedString` (text + per-range attributes), laid
out with a `FontDescriptor` into a `TextLayout`, and drawn into a `DrawContext`.

```php
use Libui\Text\AttributedString;
use Libui\Text\Attribute;
use Libui\Text\FontDescriptor;
use Libui\Text\TextLayout;
use Libui\Generated\Enum\TextWeight;

$string = new AttributedString();
$string->append('PHP ', Attribute::weight(TextWeight::Bold), Attribute::color(0.31, 0.27, 0.90));
$string->append('is fun.', Attribute::italic(\Libui\Generated\Enum\TextItalic::Italic));

$font   = new FontDescriptor('Helvetica', 18.0);
$layout = new TextLayout($string, $font, width: 400.0);

// In an Area's draw():
$ctx->text($layout, 10, 10);
[$w, $h] = $layout->extents(); // measured size after wrapping
```

`Attribute` has static builders: `color()`, `rgb(0xRRGGBB)`, `background()`,
`weight()`, `italic()`, `stretch()`, `size()`, `family()`, `underline()`,
`underlineColor()`. The colour builders also take a typed `Color` via
`fromColor()` / `backgroundFromColor()`.

`AttributedString`, `FontDescriptor`, `TextLayout` and `Attribute` hold native
memory; they free themselves on destruction, but you can call `free()` explicitly.
See [`examples/text.php`](../examples/text.php) and
[`examples/markdown.php`](../examples/markdown.php).

### `RichText` + `TextStyle` — styled text without the dance

For styled runs, `TextStyle` bundles a font + colour + attributes, and `RichText`
assembles them into a measurable `TextLayout` without building the
`AttributedString` by hand:

```php
use Libui\Color;
use Libui\Text\RichText;
use Libui\Text\TextStyle;
use Libui\Generated\Enum\TextWeight;

$rich = RichText::create(new TextStyle(family: 'Helvetica', size: 16.0))
    ->append('Bold ', new TextStyle(weight: TextWeight::Bold, color: Color::rgb(0x312B90)))
    ->append('and plain.');

[$w, $h] = $rich->measure(width: 400.0); // lay out + measure in one call
$ctx->text($rich->layout(400.0), 10, 10); // or draw it
```

`TextStyle` is immutable — derive variants with `->with(...)`. `RichText::height()`
is a shortcut for the measured height (handy for wrapping text in a custom Area).

### OpenType features — ligatures, small caps, …

`Text\OpenTypeFeatures` is a bag of four-character OpenType feature tags (e.g.
`liga`, `smcp`, `tnum`) mapped to integer values. Build one, then apply it over a
range with an `AttributeType::Features` attribute:

```php
use Libui\Text\AttributedString;
use Libui\Text\Attribute;
use Libui\Text\OpenTypeFeatures;
use Libui\Generated\Enum\AttributeType;

$features = (new OpenTypeFeatures())
    ->add('liga', 1)   // enable standard ligatures
    ->add('smcp', 1);  // small caps

$value = $features->get('liga'); // 1, or null if the tag isn't present

$string = new AttributedString();
$string->append('Office', new Attribute(AttributeType::Features, 0, 0, $features));
```

The tag must be exactly four characters (anything else throws
`InvalidArgumentException`). libui *clones* the features into the attribute, so the
`OpenTypeFeatures` keeps ownership of its own native handle — it frees itself on
destruction, or call `free()` explicitly (idempotent).

---

## Tables (data grids)

For static data, skip the delegate entirely — `Table::fromRows()` (positional) and
`Table::fromAssoc()` (associative, headers from the keys) build the model and
columns for you:

```php
use Libui\Table;

$table = Table::fromRows(
    [['Ada', '36'], ['Alan', '41']],
    headers: ['Name', 'Age'],
);

// or from associative rows — headers come from the keys
$table = Table::fromAssoc([
    ['name' => 'Ada', 'age' => 36],
    ['name' => 'Alan', 'age' => 41],
]);
```

For dynamic or computed data, implement a model by extending
`TableModelDelegate`:

```php
use Libui\Table;
use Libui\TableModelDelegate;
use Libui\Generated\Enum\TableValueType;

$delegate = new class extends TableModelDelegate {
    private array $rows = [['Ada', 36], ['Alan', 41]];

    public function numColumns(): int { return 2; }
    public function numRows(): int    { return count($this->rows); }

    public function columnType(int $column): TableValueType
    {
        return $column === 1 ? TableValueType::Int : TableValueType::String;
    }

    public function cellValue(int $row, int $column): string|int
    {
        return $this->rows[$row][$column];
    }
};

$table = Table::fromDelegate($delegate);
$table->appendTextColumn('Name', 0);
$table->appendTextColumn('Age', 1);
```

Column builders: `appendTextColumn()`, `appendImageColumn()`,
`appendImageTextColumn()`, `appendCheckboxColumn()`, `appendCheckboxTextColumn()`,
`appendProgressBarColumn()`, `appendButtonColumn()`.

Selection and rows:

```php
use Libui\Generated\Enum\TableSelectionMode;

$table->setSelectionMode(TableSelectionMode::ZeroOrMany);
$rows = $table->selectedRows();                 // int[] of selected row indices
// Handlers receive the Table; read the current selection from it:
$table->onSelectionChanged(fn (Table $t) => print implode(',', $t->selectedRows()));
$table->onRowClicked(fn (Table $t) => /* … */ null);
$table->onRowDoubleClicked(fn (Table $t) => /* … */ null);
```

### Sortable columns

libui doesn't sort for you — it tells you when a header is clicked and draws the
arrow you ask it to. You sort the underlying data and reflect the direction with a
sort indicator:

```php
use Libui\Generated\Enum\SortIndicator;

$ascending = true;

$table->onHeaderClicked(function (Table $t, int $column) use (&$ascending, $delegate) {
    $ascending = ! $ascending;

    // clear arrows on every column, then set the active one
    for ($c = 0; $c < $delegate->numColumns(); $c++) {
        $t->setSortIndicator($c, SortIndicator::None);
    }
    $t->setSortIndicator(
        $column,
        $ascending ? SortIndicator::Ascending : SortIndicator::Descending,
    );

    $delegate->sortByColumn($column, $ascending); // your own data sort + model notify
});

$current = $table->sortIndicator(0); // SortIndicator::{None,Ascending,Descending}
```

`onHeaderClicked(fn (Table $t, int $column))` fires with the clicked column index;
`setSortIndicator($column, $indicator)` and `sortIndicator($column)` get/set the
arrow. After reordering your rows, notify the model so libui re-reads the cells.

For an editable text column, pass the editable model column and override
`cellEditable()` + `setCellValue()` in your delegate.

### Model lifetime is handled for you

> libui **aborts the process** if a `TableModel` is leaked past `uiUninit()` (its
> leak checker) — or freed while its `Table` is still alive. You no longer have to
> manage this: every `TableModel` registers itself, and `Ffi::uninit()` frees any
> still-live models just before tearing libui down (after your windows have
> closed). Calling `$table->model()->free()` yourself still works and is idempotent.

See [`examples/table.php`](../examples/table.php).

---

## Images

`Image` wraps a libui bitmap for table image columns or area drawing.

```php
use Libui\Image;

$logo = Image::fromPng('assets/logo.png');           // needs ext-gd
$tile = Image::fromRgba($rgbaBytes, 16, 16);         // raw RGBA, 4 bytes/pixel
```

- `fromPng()` decodes via GD and converts to straight (non-premultiplied) RGBA.
  It throws if `ext-gd` is missing or the file can't be read.
- `fromRgba($bytes, $w, $h)` requires **exactly** `w * h * 4` bytes (it validates).
- Pixel data is **copied** into libui synchronously, so the source buffer doesn't
  need to outlive the call.
- Call `free()` when done; it's idempotent.

---

## Clipboard

libui has no clipboard API, so `Utils\Clipboard` shells out to the platform tool.
It is **best-effort**: it returns `false`/`null` if no tool is installed.

```php
use Libui\Utils\Clipboard;

Clipboard::copy('hello');     // pbcopy / clip / wl-copy / xclip / xsel
$text = Clipboard::paste();   // null if unavailable
```

On Linux it tries Wayland (`wl-copy`), then X11 (`xclip`, `xsel`) in turn.

---

## Testing your UI headlessly

You can't move a real mouse in CI, but you *can* assert that a handler fired and
inspect a control's flags — without `Ffi::main()`. The `Libui\Testing` namespace
has two tiny tools for this.

**`CallbackSpy`** is an invokable test double that records every call. Register it
anywhere a handler is expected; libui (or your test) calls it like any closure, and
it tallies the arguments:

```php
use Libui\Testing\CallbackSpy;

$spy = new CallbackSpy();
$button->onClicked($spy);

$spy(); // simulate a click (a test invokes the spy directly)

$this->assertTrue($spy->called());
$this->assertSame(1, $spy->count());
$args = $spy->lastArgs();           // arguments of the most recent call
$first = $spy->argsOf(0);           // arguments of call #0 (negative = from end)

// Wrap a real handler to record *and* run it:
$spy = new CallbackSpy(fn (Table $t, int $col) => $t->setSortIndicator($col, $ind));
```

A `CallbackSpy` never touches FFI or the loop — it only observes calls dispatched
to it. It can't *trigger* a native event on its own; a test invokes it to simulate
one.

**`Inspect`** wraps the read-only `uiControl` verbs as intent-revealing assertions,
plus a delta helper that confirms a registration retained a trampoline:

```php
use Libui\Testing\Inspect;

$this->assertTrue(Inspect::isVisible($window));
$this->assertTrue(Inspect::isEnabled($button));
$this->assertTrue(Inspect::isToplevel($window));

$n = Inspect::callbacksRegisteredBy(fn () => $button->onClicked($spy));
$this->assertSame(1, $n); // wiring the handler retained one callback
```

What this **can't** do (libui exposes no C API, so the harness won't fake it):
enumerate a container's children, map a retained callback back to its widget, read
rendered geometry, or fire native events. For event assertions, use a
`CallbackSpy`. Tests live in `tests/` and extend `Libui\Tests\LibuiTestCase`
(its `setUpBeforeClass()` runs `Ffi::init()`).

---

## The raw FFI escape hatch

Not every libui function has a typed wrapper, but **all 299 are callable**. Reach
the raw handle and call the C function directly:

```php
$ffi = Ffi::get();
$ffi->uiControlShow($widget->asControl());
```

Helpers on `Ffi` for the FFI boundary:

- `Ffi::new('uiSomeStruct')` — allocate a C struct/value.
- `Ffi::control($handle)` — upcast any widget handle to `uiControl *`.
- `Ffi::ownedString($ptr)` — copy a libui-owned `char *` into PHP and free it.
- `Ffi::borrowedString($ptr)` — copy a borrowed/const `char *` without freeing.

If you find yourself wrapping the same raw call repeatedly, add a sugar method to
the hand-written subclass (e.g. `src/Slider.php`) rather than editing the
generated parent.

---

## Gotchas

### Closures passed to C are kept forever
Every callback you register is retained statically for the process lifetime. libui
holds a raw C pointer to it; if PHP garbage-collected the closure the next event
would crash. This is automatic, but it means callbacks (and anything they capture
via `use`) are never freed. Avoid capturing large objects you expect to be
collected.

### Exceptions can't cross the C boundary
A PHP exception thrown inside any libui callback (widget handlers, `Area`
delegate, `Loop`/timer callbacks, table model) is caught and printed to STDERR,
**not** propagated. There is no PHP frame above the callback to catch it. Handle
recoverable errors inside the callback.

### `echo` is not an expression
`fn () => echo "x"` is a syntax error. Use `fn () => print "x"` or a block body.

### `Ffi::uninit()` ends libui
After `Ffi::main()` returns, libui is torn down. You can't reuse widgets created
before `uninit()`. `Window::run()` and `App::run()` handle this for you (and now
do so in a `try/finally`, so a throwing cleanup hook still uninits).

### Table model lifetime is automatic
See [above](#model-lifetime-is-handled-for-you) — `Ffi::uninit()` frees registered
models for you; an explicit `model()->free()` is optional and idempotent.

### `DrawContext` is single-use
It's only valid during the one `draw()` call it's handed to. Don't store it across
frames; request a repaint with `Area::queueRedrawAll()` instead.

### Native resources need freeing
`Image`, `AttributedString`, `FontDescriptor`, `TextLayout`, `OpenTypeFeatures`,
and `TableModel` hold native memory. Most free themselves on destruction, but for
long-running apps that churn through many of them, call `free()` explicitly to
avoid leaks.

### `\FFI` vs `Libui\Ffi`
The class names collide case-insensitively. In library code `\FFI` (the global) is
always fully qualified. If you `use Libui\Ffi;` don't also import `\FFI`.

---

## Platform notes

- **macOS** (arm64 + x86_64): a universal prebuilt dylib ships in the package —
  nothing to install.
- **Linux** (x86_64 + aarch64): a prebuilt `libui.so` ships in the package (the
  x86_64 one is built on Ubuntu 22.04 for broad glibc compatibility). It just needs
  **GTK 3** installed at runtime (`apt install libgtk-3-0`); on an incompatible
  distro, `composer build-lib` or `$LIBUI_LIB`. GUI tests run headless under `xvfb`.
- **Windows** (x86_64): a prebuilt `libui.dll` (built with MSVC) ships in the
  package under `lib/windows-x86_64/` — nothing to install; works out of the box.

Override the library path at any time with the `$LIBUI_LIB` environment variable.
See the README's [Platform support](../README.md#platform-support) table for the
current status of prebuilt binaries.
