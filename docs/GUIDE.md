# Libui for PHP — User Guide

A task-by-task guide to building native desktop apps with `helgesverre/libui`. It
assumes you've installed the package (`composer require helgesverre/libui`) and
have PHP 8.3+ with FFI. For *why* the library is built the way it is, read
[ARCHITECTURE.md](ARCHITECTURE.md); for the API reference, generate the API docs
(`composer docs:api`) or read the docblocks in your IDE.

## Contents

- [Hello, window](#hello-window)
- [Application lifecycle](#application-lifecycle)
- [Widgets](#widgets)
- [Layout: boxes, grids, forms, tabs](#layout-boxes-grids-forms-tabs)
- [Events and callbacks](#events-and-callbacks)
- [Dialogs](#dialogs)
- [Menus](#menus)
- [Async — the event loop](#async--the-event-loop)
- [Custom drawing](#custom-drawing)
- [Attributed text](#attributed-text)
- [Tables (data grids)](#tables-data-grids)
- [Images](#images)
- [Clipboard](#clipboard)
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
window's child controls (see [the table gotcha](#tables-must-outlive-their-model)):

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

`append($child, $stretchy = 0)` — pass `1` (or use `appendStretchy()`) to let a
child expand.

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
// append(label, control, stretchy)
$form->append('Name', $nameEntry, 0);
$form->append('Bio', $bioEntry, 1); // 1 = this row grows vertically
```

### Tab — paged container

```php
use Libui\Tab;

$tab = (new Tab())
    ->append('General', $generalBox)
    ->append('Advanced', $advancedBox);
```

### Group — titled frame

```php
use Libui\Group;

$group = (new Group('Settings'))->setChild($box);
```

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

Menus are application-global and **must be created before the first window**:

```php
use Libui\Menu;
use Libui\Generated\Ui;

$file = new Menu('File');
$open = $file->appendItem('Open');
$file->appendQuitItem();

$open->onClicked(function () use ($window) {
    $path = Ui::openFile($window);
    if ($path !== '') {
        Ui::msgBox($window, 'You picked', $path);
    }
});

// Construct the window with a menubar:
$window = new Window('App', 640, 480, hasMenubar: true);
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

## Custom drawing

For anything libui doesn't have a widget for — charts, canvases, custom controls
— use an `Area` driven by an `AreaDelegate`.

```php
use Libui\Area;
use Libui\AreaDelegate;
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
        $ctx->drawString('Hello', new FontDescriptor('Helvetica', 24.0), [1.0, 1.0, 1.0], 20, 20);
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

See [`examples/canvas.php`](../examples/canvas.php),
[`examples/flowfield.php`](../examples/flowfield.php), and
[`examples/clock.php`](../examples/clock.php).

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
`weight()`, `italic()`, `size()`, `family()`, `underline()`, `underlineColor()`.

`AttributedString`, `FontDescriptor`, `TextLayout` and `Attribute` hold native
memory; they free themselves on destruction, but you can call `free()` explicitly.
See [`examples/text.php`](../examples/text.php) and
[`examples/markdown.php`](../examples/markdown.php).

---

## Tables (data grids)

A `Table` displays data pulled lazily from a model you implement by extending
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

For an editable text column, pass the editable model column and override
`cellEditable()` + `setCellValue()` in your delegate.

> ### Tables must outlive their model
> libui **aborts the process** if a `TableModel` is freed while its `Table` is
> still alive. Free the model *after* the loop returns and the table is destroyed
> — use the `$afterClose` hook:
>
> ```php
> $window->run(function () use ($table) {
>     $table->model()->free();
> });
> ```

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

### Tables must outlive their model
See [above](#tables-must-outlive-their-model) — free the model in `$afterClose`,
never mid-loop.

### `DrawContext` is single-use
It's only valid during the one `draw()` call it's handed to. Don't store it across
frames; request a repaint with `Area::queueRedrawAll()` instead.

### Native resources need freeing
`Image`, `AttributedString`, `FontDescriptor`, `TextLayout`, and `TableModel` hold
native memory. Most free themselves on destruction, but for long-running apps that
churn through many of them, call `free()` explicitly to avoid leaks.

### `\FFI` vs `Libui\Ffi`
The class names collide case-insensitively. In library code `\FFI` (the global) is
always fully qualified. If you `use Libui\Ffi;` don't also import `\FFI`.

---

## Platform notes

- **macOS** (arm64 + x86_64): a universal prebuilt dylib ships in the package —
  nothing to install.
- **Linux**: needs **GTK 3** at runtime. Build `libui.so` (`composer build-lib`)
  or point `$LIBUI_LIB` at a system-installed one. GUI tests run headless under
  `xvfb`.
- **Windows**: build `libui.dll` or supply a prebuilt one; point `$LIBUI_LIB` at
  it. Currently experimental in CI.

Override the library path at any time with the `$LIBUI_LIB` environment variable.
See the README's [Platform support](../README.md#platform-support) table for the
current status of prebuilt binaries.
