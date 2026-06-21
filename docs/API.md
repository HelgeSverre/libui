# Libui for PHP — API Reference

> Generated from source by `composer docs:api` (`tools/gen-api-docs.php`).
> For a narrative walkthrough see [GUIDE.md](GUIDE.md); for design see
> [ARCHITECTURE.md](ARCHITECTURE.md).

## Contents

- [Application & lifecycle](#application-lifecycle)
- [Widgets](#widgets)
- [Containers & layout](#containers-layout)
- [Tables](#tables)
- [Drawing](#drawing)
- [Text](#text)
- [Async & utilities](#async-utilities)
- [Dialogs](#dialogs)
- [Enums](#enums)

## Application & lifecycle

### `App`

`Libui\App`

Application lifecycle for richer apps — multiple windows, a should-quit handler, and one place that owns init / main loop / uninit:

- `static new(): App`
- `onShouldQuit(callable $cb): static` — Install a should-quit handler. Return true to allow the app to quit, false to keep it running (e.g. to prompt for unsaved changes).
- `run(): void` — Initialise libui, show the windows, run the loop until quit, then uninit.
- `window(Window $window): static` — Register a window to show when the app runs. The first one drives app lifetime.

### `Control`

`Libui\Control`

Base class for every libui widget.

_No public methods._

### `Ffi`

`Libui\Ffi`

The single FFI handle bound to libui-ng, plus library lifecycle and the low-level marshalling helpers the generated classes and the drawing adapter rely on.

- `static borrowedString(?CData $ptr): string` — Copies a borrowed C string into PHP without freeing it.
- `static control(CData $handle): CData` — Upcasts any libui widget handle to the generic uiControl pointer type.
- `static get(): FFI` — Returns the singleton FFI instance bound to libui-ng.
- `static init(): void` — Initializes the libui library.
- `static isInitialized(): bool` — Checks whether libui has been initialized in the current process.
- `static main(): void` — Runs the libui event loop.
- `static new(string $type, bool $owned = true): CData` — Allocates a C value or struct of the given type.
- `static onShouldQuit(callable $fn): void` — Install the should-quit handler, invoked when the platform asks the app to quit. Return true from $fn to allow the quit, false to veto it.
- `static ownedString(?CData $ptr): string` — Copies an owned C string into PHP and frees it with uiFreeText.
- `static queueMain(callable $fn): void` — Queue a callback to run once on the main thread, on the next loop tick.
- `static quit(): void` — Requests that the event loop quit.
- `static root(): string` — Returns the absolute path to the package root directory.
- `static timer(int $milliseconds, callable $fn): void` — Run a callback repeatedly every $milliseconds on the main thread.
- `static uninit(): void` — Shuts down the libui library.

### `Loop`

`Libui\Loop`

Async event loop integration with libui's native event loop.

- `static cancel(int $id): void` — Cancel a scheduled timer.
- `static defer(callable $callback): void` — Schedule a callback to run once on the next event-loop tick.
- `static delay(int $milliseconds, callable $callback): int` — Schedule a callback to run once after a delay.
- `static isRunning(): bool` — Whether the native event loop is currently running.
- `static repeat(int $milliseconds, callable $callback): int` — Schedule a callback to run repeatedly at a fixed interval.
- `static run(): void` — Run the event loop until {@see Loop::stop()} is called or all windows close.
- `static stop(): void` — Signal the event loop to quit.

### `Window`

`Libui\Window`

Top-level window. Adds lifecycle sugar on top of the generated API: sensible constructor defaults, an onClose() cleanup hook, and a one-call run().

_Plus the common widget verbs from [`Control`](#control)._

- `static menusLocked(): bool` — Whether any Window has been created (after which new Menus are illegal).
- `static resetMenuLock(): void` — Reset the menu-ordering lock so a fresh libui session (after Ffi::uninit()) may build menus again. Called automatically by Ffi::uninit(); also useful directly in tests that need to construct a Menu after a Window already exists.
- `__construct(string $title, int $width = 640, int $height = 480, bool $hasMenubar = false)`
- `borderless(): bool` — Returns whether or not the window is borderless.
- `centered(?int $screenWidth = null, ?int $screenHeight = null): static` — Centre the window on the primary display.
- `contentSize(CData $width, CData $height): static` — Gets the window content size.
- `dialogs(): Dialogs` — A Dialogs facade bound to this window as the parent.
- `focused(): bool` — Returns whether or not the window is focused.
- `fullscreen(): bool` — Returns whether or not the window is full screen.
- `margined(): bool` — Returns whether or not the window has a margin.
- `onClose(callable $cb): static` — Run cleanup when the window is closed, before the app quits. Unlike the raw onClosing(), you don't manage the loop or return a value.
- `onClosing(callable $cb): static` — Registers a callback for when the window is to be closed.
- `onContentSizeChanged(callable $cb): static` — Registers a callback for when the window content size is changed.
- `onFocusChanged(callable $cb): static` — Registers a callback for when the window focus changes.
- `onPositionChanged(callable $cb): static` — Registers a callback for when the window moved.
- `position(CData $x, CData $y): static` — Gets the window position. Coordinates are measured from the top left corner of the screen.
- `resizeable(): bool` — Returns whether or not the window is user resizeable.
- `run(?callable $afterClose = null): void` — Show the window and run the event loop until it closes — the all-in-one entry point for a single-window app. Initialises libui if needed, wires the close button to quit (after any onClose() cleanup), and uninits on exit.
- `setBorderless(bool $borderless): static` — Sets whether or not the window is borderless.
- `setChild(Control $child): static` — Sets the window's child.
- `setContentSize(int $width, int $height): static` — Sets the window content size.
- `setFullscreen(bool $fullscreen): static` — Sets whether or not the window is full screen.
- `setMargined(bool $margined): static` — Sets whether or not the window has a margin. The margin size is determined by the OS defaults.
- `setPosition(int $x, int $y): static` — Moves the window to the specified position. Coordinates are measured from the top left corner of the screen.
- `setResizeable(bool $resizeable): static` — Sets whether or not the window is user resizeable.
- `setTitle(string $title): static` — Sets the window title.
- `title(): string` — Returns the window title.

## Widgets

### `ArrayTableModelDelegate`

`Libui\ArrayTableModelDelegate` — extends `TableModelDelegate`

A ready-made {@see TableModelDelegate} over an in-memory, row-major array.

- `__construct(array $rows, array $headers, array $types = [])`
- `cellValue(int $row, int $column): Color|Image|string|int|bool|null`
- `columnType(int $column): TableValueType`
- `headers(): array`
- `numColumns(): int`
- `numRows(): int`

### `Button`

`Libui\Button`

Button widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\Button.

_Plus the common widget verbs from [`Control`](#control)._

- `__construct(string $text)` — Creates a new button.
- `onClicked(callable $cb): static` — Registers a callback for when the button is clicked.
- `setText(string $text): static` — Sets the button label text.
- `text(): string` — Returns the button label text.

### `Checkbox`

`Libui\Checkbox`

Checkbox widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\Checkbox.

_Plus the common widget verbs from [`Control`](#control)._

- `__construct(string $text)` — Creates a new checkbox.
- `checked(): bool` — Returns whether or the checkbox is checked.
- `onToggled(callable $cb): static` — Registers a callback for when the checkbox is toggled by the user.
- `setChecked(bool $checked): static` — Sets whether or not the checkbox is checked.
- `setText(string $text): static` — Sets the checkbox label text.
- `setValue(mixed $value): static`
- `text(): string` — Returns the checkbox label text.
- `value(): bool`

### `Color`

`Libui\Color`

An immutable RGBA colour, stored as normalized 0..1 channels (libui-native).

- `static black(): Color`
- `static from(Color|array $color): Color` — Coerce a Color or an `[r, g, b]` / `[r, g, b, a]` float array into a Color.
- `static hex(string $hex): Color` — Colour from a `#RGB`, `#RRGGBB`, or `#RRGGBBAA` string (leading `#` optional).
- `static rgb(int $hex, float $a = 1): Color` — Colour from a `0xRRGGBB` integer, with optional 0..1 alpha.
- `static rgb255(int $r, int $g, int $b, float $a = 1): Color` — Colour from 8-bit (0-255) channels, with optional 0..1 alpha.
- `static rgba(float $r, float $g, float $b, float $a = 1): Color` — Colour from 0..1 float channels. Out-of-range values are clamped.
- `static transparent(): Color`
- `static white(): Color`
- `toArray(): array` — The channels as a `[r, g, b, a]` float array, for the float-array APIs.
- `toHex(): int` — The colour as a `0xRRGGBB` integer (alpha dropped).
- `withAlpha(float $a): Color` — A copy with a different alpha (0..1, clamped).

### `ColorButton`

`Libui\ColorButton`

ColorButton widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\ColorButton.

_Plus the common widget verbs from [`Control`](#control)._

- `__construct()` — Creates a new color button.
- `color(CData $r, CData $g, CData $bl, CData $a): static` — Returns the color button color.
- `getColor(): Color` — The currently selected colour as a {@see Color}, wrapping the generated output-pointer getter.
- `onChanged(callable $cb): static` — Registers a callback for when the color is changed.
- `setColor(Color|float $r, float $g = 0, float $b = 0, float $a = 1): static` — Set the button colour from a {@see Color}, or from raw 0..1 float channels (the generated signature still works).
- `setValue(mixed $value): static` — Set the colour from a {@see Color} or an `[r,g,b(,a)]` array.
- `value(): Color` — The selected colour, for generic binding.

### `Combobox`

`Libui\Combobox`

Combobox widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\Combobox.

_Plus the common widget verbs from [`Control`](#control)._

- `__construct()` — Creates a new combo box.
- `append(string $text): static` — Appends an item to the combo box.
- `clear(): static` — Deletes all items from the combo box.
- `delete(int $index): static` — Deletes an item at $index from the combo box.
- `insertAt(int $index, string $text): static` — Inserts an item at $index to the combo box.
- `numItems(): int` — Returns the number of items contained within the combo box.
- `onSelected(callable $cb): static` — Registers a callback for when a combo box item is selected.
- `selected(): int` — Returns the index of the item selected.
- `setSelected(int $index): static` — Sets the item selected.
- `setValue(mixed $value): static`
- `value(): int`

### `DateTimePicker`

`Libui\DateTimePicker`

DateTimePicker widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\DateTimePicker.

_Plus the common widget verbs from [`Control`](#control)._

- `static dateOnly(): static` — Creates a new time picker.
- `static timeOnly(): static` — Creates a new date and time picker.
- `__construct()` — Creates a new date picker.
- `getValue(): DateTimeImmutable` — The picked moment as a DateTimeImmutable (local wall-clock fields).
- `onChanged(callable $cb): static` — Registers a callback for when the date time picker value is changed by the user.
- `setTime(CData $time): static` — Sets date and time of the data time picker.
- `setValue(DateTimeInterface $when): static` — Set the picker to $when (its broken-down local-time fields).
- `time(CData $time): static` — Returns date and time stored in the data time picker.

### `Dialogs`

`Libui\Dialogs`

Dialog helpers bound to a parent Window, so call sites don't repeat $parent.

- `static for(Window $parent): Dialogs`
- `__construct(Window $parent)`
- `error(string $title, string $description): void`
- `msgBox(string $title, string $description): void`
- `openFile(): ?string`
- `openFolder(): ?string`
- `saveFile(): ?string`

### `EditableCombobox`

`Libui\EditableCombobox`

EditableCombobox widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\EditableCombobox.

_Plus the common widget verbs from [`Control`](#control)._

- `__construct()` — Creates a new editable combo box.
- `append(string $text): static` — Appends an item to the editable combo box.
- `onChanged(callable $cb): static` — Registers a callback for when an editable combo box item is selected or user text changed.
- `setText(string $text): static` — Sets the editable combo box text.
- `setValue(mixed $value): static`
- `text(): string` — Returns the text of the editable combo box. This text is either the text of one of the predefined list items or the t...
- `value(): string`

### `Entry`

`Libui\Entry`

Entry widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\Entry.

_Plus the common widget verbs from [`Control`](#control)._

- `static password(): static` — Creates a new entry suitable for sensitive inputs like passwords. The entered text is NOT readable by the user but ma...
- `static search(): static` — Creates a new entry suitable for search. Some systems will deliberately delay the uiEntryOnChanged() callback for a m...
- `__construct()` — Creates a new entry.
- `onChanged(callable $cb): static` — Registers a callback for when the user changes the entry's text.
- `readOnly(): bool` — Returns whether or not the entry's text can be changed.
- `setReadOnly(bool $readonly): static` — Sets whether or not the entry's text is read only.
- `setText(string $text): static` — Sets the entry's text.
- `setValue(mixed $value): static`
- `text(): string` — Returns the entry's text.
- `value(): string`

### `MenuOrderException`

`Libui\Exception\MenuOrderException` — extends `LogicException`

Thrown when a Menu is created after a Window already exists.

_No public methods._

### `FontButton`

`Libui\FontButton`

FontButton widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\FontButton.

_Plus the common widget verbs from [`Control`](#control)._

- `__construct()` — Creates a new font button. The default font is determined by the OS defaults.
- `font(CData $desc): static` — Returns the selected font.
- `getFont(): FontDescriptor` — The currently selected font as a typed {@see FontDescriptor}, wrapping the generated output-pointer getter and freeing libui's allocated copy.
- `onChanged(callable $cb): static` — Registers a callback for when the font is changed.

### `HasValue`

`Libui\HasValue`

An input widget with a single readable/writable value, for generic binding (e.g. {@see Form::values()} / {@see Form::setValues()}).

- `setValue(mixed $value): static`
- `value(): mixed`

### `Label`

`Libui\Label`

Label widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\Label.

_Plus the common widget verbs from [`Control`](#control)._

- `__construct(string $text)` — Creates a new label.
- `setText(string $text): static` — Sets the label text.
- `text(): string` — Returns the label text.

### `Lifecycle`

`Libui\Lifecycle`

Process-wide registry of native resources that must be released before uiUninit(). Today: uiTableModels — libui's leak checker aborts in uiUninit() if a model is left unfreed, so {@see Ffi::uninit()} drains this registry first.

- `static freeAll(): void` — Free every still-live registered model exactly once.
- `static registerModel(TableModel $model): void`
- `static unregisterModel(TableModel $model): void`

### `Menu`

`Libui\Menu`

Menu widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\Menu.

_Plus the common widget verbs from [`Control`](#control)._

- `__construct(string $name)`
- `appendAboutItem(): MenuItem` — The platform About item, as a hand-wrapped {@see MenuItem}.
- `appendCheckItem(string $name, ?callable $onClick = null): MenuItem` — Append a check item, optionally wiring a clean fn(MenuItem $item) handler.
- `appendItem(string $name, ?callable $onClick = null): MenuItem` — Append a clickable item, optionally wiring a clean fn(MenuItem $item) handler.
- `appendPreferencesItem(): MenuItem` — The platform Preferences item, as a hand-wrapped {@see MenuItem}.
- `appendQuitItem(): MenuItem` — The platform Quit item, as a hand-wrapped {@see MenuItem} so `onClick()` is available like every other append helper.
- `appendSeparator(): static` — Appends a new separator.

### `MenuItem`

`Libui\MenuItem`

MenuItem widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\MenuItem.

_Plus the common widget verbs from [`Control`](#control)._

- `static fromGenerated(MenuItem $g): MenuItem` — Re-wrap a generated MenuItem handle as a hand-written Libui\MenuItem.
- `checked(): bool` — Returns whether or not the menu item's checkbox is checked. To be used only with items created via uiMenuAppendCheckI...
- `disable(): static` — Disables the menu item. Menu item is grayed out and user interaction is not possible.
- `enable(): static` — Enables the menu item.
- `onClick(callable $cb): static` — Register a click handler that receives only this typed MenuItem.
- `onClicked(callable $cb): static` — Registers a callback for when the menu item is clicked.
- `setChecked(bool $checked): static` — Sets whether or not the menu item's checkbox is checked. To be used only with items created via uiMenuAppendCheckItem().

### `MultilineEntry`

`Libui\MultilineEntry`

MultilineEntry widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\MultilineEntry.

_Plus the common widget verbs from [`Control`](#control)._

- `static nonWrapping(): static` — Creates a new multi line entry that scrolls horizontally when lines overflow.
- `__construct()` — Creates a new multi line entry that visually wraps text when lines overflow.
- `append(string $text): static` — Appends text to the multi line entry's text.
- `onChanged(callable $cb): static` — Registers a callback for when the user changes the multi line entry's text.
- `readOnly(): bool` — Returns whether or not the multi line entry's text can be changed.
- `setReadOnly(bool $readonly): static` — Sets whether or not the multi line entry's text is read only.
- `setText(string $text): static` — Sets the multi line entry's text.
- `setValue(mixed $value): static`
- `text(): string` — Returns the multi line entry's text.
- `value(): string`

### `ProgressBar`

`Libui\ProgressBar`

ProgressBar widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\ProgressBar.

_Plus the common widget verbs from [`Control`](#control)._

- `__construct()` — Creates a new progress bar.
- `setValue(int $n): static` — Sets the progress bar value. Valid values are `[0, 100]` for displaying a solid bar imitating a percent value. Use a...
- `value(): int` — Returns the progress bar value.

### `RadioButtons`

`Libui\RadioButtons`

RadioButtons widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\RadioButtons.

_Plus the common widget verbs from [`Control`](#control)._

- `__construct()` — Creates a new radio buttons instance.
- `append(string $text): static` — Appends a radio button.
- `onSelected(callable $cb): static` — Registers a callback for when radio button is selected.
- `selected(): int` — Returns the index of the item selected.
- `setSelected(int $index): static` — Sets the item selected.
- `setValue(mixed $value): static`
- `value(): int`

### `Separator`

`Libui\Separator`

Separator widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\Separator.

_Plus the common widget verbs from [`Control`](#control)._

- `static vertical(): static` — Creates a new vertical separator to separate controls being stacked horizontally.
- `__construct()` — Creates a new horizontal separator to separate controls being stacked vertically.

### `Slider`

`Libui\Slider`

Slider widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\Slider.

_Plus the common widget verbs from [`Control`](#control)._

- `__construct(int $min, int $max)` — Creates a new slider. The initial slider value equals the minimum value. In the current implementation $min and $max...
- `hasToolTip(): bool` — Returns whether or not the slider has a tool tip.
- `onChanged(callable $cb): static` — Registers a callback for when the slider value is changed by the user.
- `onReleased(callable $cb): static` — Registers a callback for when the slider is released from dragging.
- `setHasToolTip(bool $hasToolTip): static` — Sets whether or not the slider has a tool tip.
- `setRange(int $min, int $max): static` — Sets the slider range.
- `setValue(mixed $value): static`
- `value(): int` — Returns the slider value.

### `Spinbox`

`Libui\Spinbox`

Spinbox widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\Spinbox.

_Plus the common widget verbs from [`Control`](#control)._

- `__construct(int $min, int $max)` — Creates a new spinbox. The initial spinbox value equals the minimum value. In the current implementation $min and $ma...
- `onChanged(callable $cb): static` — Registers a callback for when the spinbox value is changed by the user.
- `setValue(mixed $value): static`
- `value(): int` — Returns the spinbox value.

## Containers & layout

### `Box`

`Libui\Box`

Stacks children vertically (default) or horizontally. Adds a padded constructor option and a readable stretchy append on top of the generated API.

_Plus the common widget verbs from [`Control`](#control)._

- `static horizontal(bool $padded = false): static`
- `__construct(bool $padded = false)`
- `append(Control $child, int|bool $stretchy = false): static` — Append a child; $stretchy (bool, or the raw 0/1 int) defaults to non-stretching.
- `appendStretchy(Control $child): static` — Append a child that grows to fill the box's main axis.
- `delete(int $index): static` — Removes the control at $index from the box.
- `numChildren(): int` — Returns the number of controls contained within the box.
- `padded(): bool` — Returns whether or not controls within the box are padded. Padding is defined as space between individual controls.
- `setPadded(bool $padded): static` — Sets whether or not controls within the box are padded. Padding is defined as space between individual controls. The...

### `Form`

`Libui\Form`

Form widget — labelled rows of controls. Hand-editable. Inherits the generated API from Generated\\Form.

_Plus the common widget verbs from [`Control`](#control)._

- `__construct()` — Creates a new form.
- `append(string $label, Control $c, int|bool $stretchy = false): static` — Append a labelled field; $stretchy (bool, or the raw 0/1 int) defaults to off.
- `appendStretchy(string $label, Control $c): static` — Append a labelled field that grows to fill vertical space.
- `delete(int $index): static` — Removes the control at $index from the form.
- `numChildren(): int` — Returns the number of controls contained within the form.
- `padded(): bool` — Returns whether or not controls within the form are padded. Padding is defined as space between individual controls.
- `setPadded(bool $padded): static` — Sets whether or not controls within the box are padded. Padding is defined as space between individual controls. The...
- `setValues(array $values): static` — Set fields from `[label => value]`. Unknown labels and non-value controls are ignored, so a partial map is fine.
- `values(): array` — Read every {@see HasValue} field as `[label => value]`. Non-value controls (separators, labels, …) are skipped.

### `Grid`

`Libui\Grid`

Grid widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\Grid.

_Plus the common widget verbs from [`Control`](#control)._

- `__construct()` — Creates a new grid.
- `append(Control $c, int $left, int $top, int $xspan, int $yspan, int $hexpand, Align $halign, int $vexpand, Align $valign): static` — Appends a control to the grid.
- `appendAt(Control $control, int $left, int $top, int $xspan = 1, int $yspan = 1, bool $hexpand = false, Align $halign = Align::Fill, bool $vexpand = false, Align $valign = Align::Fill): static` — Friendlier placement over the generated 9-positional-arg append(): spans default to a single cell, the expand flags are real bools, and alignment defaults to Fill. $left is the column, $top the row.
- `insertAt(Control $c, Control $existing, At $at, int $xspan, int $yspan, int $hexpand, Align $halign, int $vexpand, Align $valign): static` — Inserts a control positioned in relation to another control within the grid.
- `padded(): bool` — Returns whether or not controls within the grid are padded. Padding is defined as space between individual controls.
- `place(Control $control, int $column, int $row): static` — Place a control in a single, non-expanding cell at column $column, row $row.
- `setPadded(bool $padded): static` — Sets whether or not controls within the grid are padded. Padding is defined as space between individual controls. The...

### `Group`

`Libui\Group`

Group widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\Group.

_Plus the common widget verbs from [`Control`](#control)._

- `__construct(string $title)` — Creates a new group.
- `margined(): bool` — Returns whether or not the group has a margin.
- `setChild(Control $c): static` — Sets the group's child.
- `setMargined(bool $margined): static` — Sets whether or not the group has a margin. The margin size is determined by the OS defaults.
- `setTitle(string $title): static` — Sets the group title.
- `title(): string` — Returns the group title.

### `Tab`

`Libui\Tab`

Tab widget. Hand-editable — add convenience methods here. Inherits the generated API from Generated\\Tab.

_Plus the common widget verbs from [`Control`](#control)._

- `__construct()` — Creates a new tab container.
- `append(string $name, Control $c): static` — Appends a control in form of a page/tab with label.
- `delete(int $index): static` — Removes the control at $index.
- `insertAt(string $name, int $index, Control $c): static` — Inserts a control in form of a page/tab with label at $index.
- `margined(int $index): bool` — Returns whether or not the page/tab at $index has a margin.
- `numPages(): int` — Returns the number of pages contained.
- `onSelected(callable $cb): static` — Registers a callback for when a tab is selected.
- `selected(): int` — Returns the index of the tab selected.
- `setMargined(int $index, bool $margined): static` — Sets whether or not the page/tab at $index has a margin. The margin size is determined by the OS defaults.
- `setSelected(int $index): static` — Sets the tab selected.

## Tables

### `Table`

`Libui\Table` — extends `Control`

A data-grid widget backed by a {@see TableModel}.

_Plus the common widget verbs from [`Control`](#control)._

- `static fromAssoc(array $rows, ?array $columns = null): static` — Build a read-only table from a list of associative rows.
- `static fromDelegate(TableModelDelegate $delegate, ?int $rowBackgroundModelColumn = null): Table` — Convenience: build the model from a delegate and wrap it in a table.
- `static fromModel(TableModel $model, ?int $rowBackgroundModelColumn = null): Table` — Convenience: wrap an existing TableModel in a table.
- `static fromRows(array $rows, array $headers = []): static` — Build a read-only table from a list of positional rows.
- `__construct(TableModel $model, ?int $rowBackgroundModelColumn = null)`
- `appendButtonColumn(string $name, int $modelColumn, ?int $clickableModelColumn = null): static` — Append a button column titled $name that reads from model column $modelColumn.
- `appendCheckboxColumn(string $name, int $modelColumn, ?int $editableModelColumn = null): static` — Append a checkbox column titled $name that reads from model column $modelColumn. The model should return bool values for this column.
- `appendCheckboxTextColumn(string $name, int $checkboxModelColumn, int $textModelColumn, ?int $checkboxEditableModelColumn = null, ?int $textEditableModelColumn = null): static` — Append a checkbox+text column titled $name. The checkbox is read from $checkboxModelColumn (model returns bool) and the text from $textModelColumn (model returns string). Pass the editable-column args to allow toggling/editing.
- `appendImageColumn(string $name, int $imageModelColumn): static` — Append a read-only image column titled $name that reads from model column $imageModelColumn. The model should return Image instances or null for this column.
- `appendImageTextColumn(string $name, int $imageModelColumn, int $textModelColumn, ?int $textEditableModelColumn = null): static` — Append an image+text column titled $name. The image is read from $imageModelColumn (model returns Image) and the text from $textModelColumn (model returns string). Pass $textEditableModelColumn to make the text editable.
- `appendProgressBarColumn(string $name, int $modelColumn): static` — Append a progress bar column titled $name that reads from model column $modelColumn. The model should return int values (0-100) for this column.
- `appendTextColumn(string $name, int $modelColumn, ?int $editableModelColumn = null, ?int $colorModelColumn = null): static` — Append a read-only text column titled $name that reads from model column $modelColumn (String or Int values are both rendered as text).
- `headerVisible(): bool` — Whether the column header row is shown.
- `model(): TableModel` — The TableModel backing this table.
- `onRowClicked(callable $cb): static` — Register a callback for when a row is clicked.
- `onRowDoubleClicked(callable $cb): static` — Register a callback for when a row is double-clicked.
- `onSelectionChanged(callable $cb): static` — Register a callback for when the table selection changes.
- `selectedRows(): array` — Get the currently selected rows.
- `selectionMode(): TableSelectionMode` — Get the current selection mode for the table.
- `setColumnWidth(int $column, int $width): static` — Set a column's width in pixels.
- `setHeaderVisible(bool $visible): static`
- `setRowBackground(int $colorModelColumn): static` — Point the table at a Color model column for per-row background. This is NOT a live setter: uiTableParams.RowBackgroundColorModelColumn is read once by uiNewTable() and cannot change afterward. The method exists only to point you at the constructor argument, and always throws.
- `setSelectedRows(array $rows): static` — Set the selected rows programmatically.
- `setSelectionMode(TableSelectionMode $mode): static` — Set the selection mode for the table.

### `TableModel`

`Libui\TableModel`

Bridges a {@see TableModelDelegate} to libui's uiTableModel.

- `static fromDelegate(TableModelDelegate $delegate): TableModel`
- `__construct(TableModelDelegate $delegate)`
- `free(): void` — Release the underlying uiTableModel.
- `handle(): CData` — The raw uiTableModel* — pass this into a {@see Table}.
- `rowChanged(int $index): void` — Notify libui that the row at $index changed so it can repaint it.
- `rowDeleted(int $index): void` — Notify libui that the row at $index was removed.
- `rowInserted(int $index): void` — Notify libui that a new row appeared at $index so it can refresh.

### `TableModelDelegate`

`Libui\TableModelDelegate`

Drives a {@see TableModel} — implement this to feed a {@see Table} its data.

- `cellEditable(int $row, int $column): ?bool` — Whether a cell is editable. Defaults to null (not editable). Return true for editable cells, false for read-only.
- `cellValue(int $row, int $column): Color|Image|string|int|bool|null` — The value to display at a cell. Return a string for String columns, an int for Int/checkbox/progress columns, a {@see Color} for Color columns, or an {@see Image} for Image columns (marshalled into the matching uiTableValue). bool is accepted for checkbox columns and cast to 0/1 via the Int branch.
- `cellValueChanged(int $row, int $column): void` — Called after a cell value has been changed. No-op by default.
- `columnType(int $column): TableValueType` — The value type of a column, deciding how libui renders/marshals it. Defaults to String — override only for Int (or Color) columns.
- `numColumns(): int` — Total number of columns the model exposes.
- `numRows(): int` — Total number of rows currently in the model.
- `setCellValue(int $row, int $column, mixed $value): void` — Persist an edit made in the UI. No-op by default (read-only tables); when a text column is made editable, override this to store $value.

## Drawing

### `Area`

`Libui\Area` — extends `Control`

A custom-drawn surface, driven by an AreaDelegate.

_Plus the common widget verbs from [`Control`](#control)._

- `static scrolling(AreaDelegate $delegate, int $width, int $height): Area`
- `__construct(AreaDelegate $delegate, ?int $scrollWidth = null, ?int $scrollHeight = null)`
- `queueRedrawAll(): void`
- `setSize(int $width, int $height): void`

### `AreaDelegate`

`Libui\AreaDelegate`

Override the methods you need to drive a custom-drawn Area. All default to no-ops so a draw-only delegate just overrides draw().

- `area(): ?Area` — The Area this delegate drives, or null if not yet bound.
- `bindArea(Area $area): void` — Bind this delegate to its Area. Called by {@see Area::__construct()}; not intended for direct use.
- `dragBroken(): void`
- `draw(DrawContext $ctx, AreaDrawParams $params): void`
- `key(AreaKeyEvent $event): bool` — Return true if the key event was handled.
- `mouse(AreaMouseEvent $event): void`
- `mouseCrossed(bool $left): void`
- `redraw(): void` — Queue a full repaint of the bound Area. No-op if the delegate has not been bound to an Area yet. Subclasses call $this->redraw() from event handlers instead of storing an Area and calling queueRedrawAll().

### `Brush`

`Libui\Draw\Brush`

A paint source for filling/stroking. Build one with a factory, then hand it to DrawContext::fill()/stroke().

- `static color(Color $color): Brush` — Build a solid brush from a {@see Color}.
- `static linearGradient(float $x0, float $y0, float $x1, float $y1, array $stops): Brush`
- `static radialGradient(float $cx, float $cy, float $radius, array $stops): Brush` — Radial gradient centred at ($cx, $cy) out to $radius. Stops are {@see Stop} objects or [pos,r,g,b,a] tuples (or a mix).
- `static rgb(int $hex, float $a = 1): Brush` — Build a solid brush from a 0xRRGGBB integer.
- `static solid(float $r, float $g, float $b, float $a = 1): Brush`
- `toCData(): CData`

### `DrawContext`

`Libui\Draw\DrawContext`

The drawing surface handed to an area's draw handler. Wraps a uiDrawContext*; only valid for the duration of that single draw call.

- `__construct(CData $ctx)`
- `clip(Path $path): void` — Intersect the current clip region with the given path.
- `drawString(string $text, FontDescriptor $font, Color|array $color, float $x, float $y, ?float $width = null, DrawTextAlign $align = DrawTextAlign::Left): void` — Convenience for the common case: draw a single string in one colour and font at ($x, $y) — no manual AttributedString / TextLayout dance.
- `fill(Path $path, Brush $brush): void`
- `fillCircle(float $cx, float $cy, float $radius, Brush|Color $paint): void`
- `fillPath(Brush $brush, callable $build, DrawFillMode $fillMode = DrawFillMode::Winding): void` — Build a path with $build, fill it, and free it — no manual end()/free().
- `fillRect(float $x, float $y, float $width, float $height, Brush|Color $paint): void`
- `restore(): void` — Pop the most recently saved clip/transform state.
- `save(): void` — Push the current clip/transform state onto libui's stack.
- `stroke(Path $path, Brush $brush, StrokeParams $stroke): void`
- `strokeCircle(float $cx, float $cy, float $radius, Brush|Color $paint, ?StrokeParams $stroke = null): void`
- `strokePath(Brush $brush, StrokeParams $stroke, callable $build, DrawFillMode $fillMode = DrawFillMode::Winding): void` — Build a path with $build, stroke it, and free it.
- `strokeRect(float $x, float $y, float $width, float $height, Brush|Color $paint, ?StrokeParams $stroke = null): void`
- `text(TextLayout $layout, float $x, float $y): void` — Draw a laid-out text block with its top-left corner at ($x, $y).
- `transform(Matrix $matrix): void` — Compose the given affine transform onto the current matrix.

### `Matrix`

`Libui\Draw\Matrix`

An affine transform, wrapping the uiDrawMatrix struct (M11..M32).

- `__construct()`
- `addr(): CData`
- `invert(): Matrix` — Invert this matrix in place. Returns $this for chaining, or throws if not invertible.
- `multiply(Matrix $src): Matrix` — Multiply this matrix by $src (this becomes this * src).
- `reset(): Matrix` — Reset this matrix to identity.
- `rotate(float $amount): Matrix` — Rotate by $amount radians around the origin (0,0).
- `rotateAround(float $x, float $y, float $amount): Matrix` — Rotate by $amount radians around the point ($x, $y).
- `scale(float $x, float $y): Matrix` — Scale by $x and $y around the origin (0,0).
- `scaleAround(float $xCenter, float $yCenter, float $x, float $y): Matrix` — Scale by $x and $y around point ($xCenter, $yCenter).
- `setIdentity(): Matrix`
- `skew(float $xamount, float $yamount): Matrix` — Skew by $xamount and $yamount around the origin (0,0).
- `skewAround(float $x, float $y, float $xamount, float $yamount): Matrix` — Skew by $xamount and $yamount around point ($x, $y).
- `toCData(): CData`
- `translate(float $x, float $y): Matrix`

### `AreaDrawParams`

`Libui\Draw\Params\AreaDrawParams`

Dimensions passed to an area's draw handler (a PHP view of uiAreaDrawParams).

- `static fromCData(CData $p): AreaDrawParams`
- `__construct(float $areaWidth, float $areaHeight, float $clipX, float $clipY, float $clipWidth, float $clipHeight)`

### `AreaKeyEvent`

`Libui\Draw\Params\AreaKeyEvent`

A PHP view of uiAreaKeyEvent.

- `static fromCData(CData $e): AreaKeyEvent`
- `__construct(int $key, int $extKey, int $modifier, int $modifiers, bool $up)`
- `char(): string` — The pressed character, or '' for an extended (non-printable) key.

### `AreaMouseEvent`

`Libui\Draw\Params\AreaMouseEvent`

A PHP view of uiAreaMouseEvent.

- `static fromCData(CData $e): AreaMouseEvent`
- `__construct(float $x, float $y, float $areaWidth, float $areaHeight, int $down, int $up, int $count, int $modifiers, int $held)`

### `Path`

`Libui\Draw\Path`

A vector path, built then filled/stroked into a DrawContext.

- `__construct(DrawFillMode $fillMode = DrawFillMode::Winding)`
- `addRectangle(float $x, float $y, float $width, float $height): Path`
- `arc(float $xCenter, float $yCenter, float $radius, float $startAngle, float $sweep, bool $negative = false): Path` — Add an arc to the current figure (angles in radians, clockwise; $negative sweeps the other way). This starts a new figure if one isn't active.
- `arcTo(float $xCenter, float $yCenter, float $radius, float $startAngle, float $sweep, bool $negative = false): Path` — Line from the current point to the arc's start, then the arc itself.
- `bezierThrough(float $x0, float $y0, float $c1x, float $c1y, float $c2x, float $c2y, float $endX, float $endY): Path` — A cubic Bézier that also opens the figure at ($x0,$y0). Convenience for the common "move then curve" pair.
- `bezierTo(float $c1x, float $c1y, float $c2x, float $c2y, float $endX, float $endY): Path` — Cubic Bézier curve to (endX, endY) via the two control points.
- `circle(float $cx, float $cy, float $radius): Path` — A full circle as a closed figure (single 0..2π arc).
- `closeFigure(): Path`
- `ellipse(float $cx, float $cy, float $rx, float $ry): Path` — An axis-aligned ellipse approximated with four cubic Béziers (kappa method). Paths have no transform, so a circle-plus-scale is not available here.
- `end(): Path` — Finalise the path; required before it can be drawn.
- `free(): void` — Free the native path. Idempotent, and runs automatically on destruction.
- `handle(): CData`
- `line(float $x0, float $y0, float $x1, float $y1): Path` — A standalone line segment as its own figure.
- `lineTo(float $x, float $y): Path`
- `newFigure(float $x, float $y): Path`
- `newFigureWithArc(float $xCenter, float $yCenter, float $radius, float $startAngle, float $sweep, bool $negative = false): Path` — Start a new figure on an arc (angles in radians, clockwise; $negative sweeps the other way). Combine with closeFigure() for a filled wedge.
- `quadTo(float $cx, float $cy, float $endX, float $endY): Path` — A quadratic Bézier from the current point to ($endX,$endY) via control ($cx,$cy), promoted to libui's cubic bezierTo *exactly* (libui has no native quadratic).
- `roundedRect(float $x, float $y, float $width, float $height, float $radius): Path` — A rectangle with rounded corners. $radius is clamped to min(width,height)/2. Corners are quarter-arcs; edges are straight (arcTo continues the figure).

### `Stop`

`Libui\Draw\Stop`

A single gradient colour stop: a position along the gradient (0..1) and a {@see Color}. The typed replacement for hand-built [pos, r, g, b, a] tuples passed to {@see Brush::linearGradient()} / {@see Brush::radialGradient()}.

- `static at(float $pos, Color $color): Stop`
- `__construct(float $pos, Color $color)`
- `toArray(): array` — The stop as the [pos, r, g, b, a] tuple that {@see Brush::toCData()} already consumes.

### `StrokeParams`

`Libui\Draw\StrokeParams`

Stroke styling for DrawContext::stroke().

- `static solid(float $thickness): StrokeParams`
- `__construct(float $thickness = 1, DrawLineCap $cap = DrawLineCap::Flat, DrawLineJoin $join = DrawLineJoin::Miter, float $miterLimit = 10, array $dashes = [], float $dashPhase = 0)`
- `cap(DrawLineCap $cap): StrokeParams`
- `dashed(array $dashes, float $phase = 0): StrokeParams` — Set the dash on/off pattern and optional phase. Empty $dashes = solid line.
- `join(DrawLineJoin $join): StrokeParams`
- `miterLimit(float $limit): StrokeParams`
- `thickness(float $thickness): StrokeParams`
- `toCData(): CData`

## Text

### `Attribute`

`Libui\Text\Attribute`

A single text attribute (a family, size, weight, colour, …) built via one of the static factories and applied to a range of an AttributedString.

- `static background(float $r, float $g, float $b, float $a = 1): Attribute`
- `static backgroundFromColor(Color $color): Attribute` — Background colour from a {@see Color}.
- `static color(float $r, float $g, float $b, float $a = 1): Attribute`
- `static family(string $family): Attribute`
- `static fromColor(Color $color): Attribute` — Text colour from a {@see Color}.
- `static italic(TextItalic $italic): Attribute`
- `static rgb(int $hex, float $a = 1): Attribute` — Colour from a 0xRRGGBB integer (mirrors Brush::rgb).
- `static size(float $size): Attribute`
- `static stretch(TextStretch $stretch): Attribute`
- `static underline(Underline $underline = Underline::Single): Attribute`
- `static underlineColor(UnderlineColor $color): Attribute`
- `static weight(TextWeight $weight): Attribute`
- `__construct(AttributeType $type, int $start, int $end, mixed ...$params)` — Create an attribute with a range. The attribute type and additional parameters vary by type: - Family: (AttributeType::Family, start, end, string $family) - Size: (AttributeType::Size, start, end, float $size) - Weight: (AttributeType::Weight, start, end, TextWeight $weight) - Italic: (AttributeType::Italic, start, end, TextItalic $italic) - Stretch: (AttributeType::Stretch, start, end, TextStretch $stretch) - Color: (AttributeType::Color, start, end, float $r, float $g, float $b, float $a) - Background: (AttributeType::Background, start, end, float $r, float $g, float $b, float $a) - Underline: (AttributeType::Underline, start, end, Underline $underline) - UnderlineColor: (AttributeType::UnderlineColor, start, end, UnderlineColor $color, [r, g, b, a])
- `free(): void`
- `getEnd(): int`
- `getStart(): int`
- `handle(): CData`

### `AttributedString`

`Libui\Text\AttributedString`

A string with per-range styling, wrapping uiAttributedString*.

- `__construct(string $initial = '')`
- `append(string $text, Attribute ...$attrs): AttributedString` — Append $text and apply each $attrs over exactly that new span.
- `appendUnattributed(string $text): AttributedString`
- `delete_(int $start, int $end): AttributedString`
- `free(): void` — Free the native string. Idempotent, and runs automatically on destruction.
- `handle(): CData`
- `insert(string $text, int $at): AttributedString`
- `len(): int` — Current length in bytes (matches strlen of the underlying UTF-8).
- `length(): int` — Alias for len().
- `setAttribute(Attribute $attribute, ?int $start = null, ?int $end = null): AttributedString`

### `FontDescriptor`

`Libui\Text\FontDescriptor`

The default font for a TextLayout, wrapping the uiFontDescriptor struct {Family char*, Size double, Weight, Italic, Stretch}.

- `static fromCData(CData $descriptor): FontDescriptor` — Build a FontDescriptor from an existing uiFontDescriptor struct (e.g. the one FontButton fills). The family name is copied into a PHP-owned buffer, so the source struct may be freed afterwards (libui's uiFreeFontButtonFont).
- `__construct(string $family = 'Arial', float $size = 14, TextWeight $weight = TextWeight::Normal, TextItalic $italic = TextItalic::Normal, TextStretch $stretch = TextStretch::Normal)`
- `addr(): CData`
- `family(): string`
- `free(): void`
- `handle(): CData`
- `italic(): TextItalic`
- `setFamily(string $family): FontDescriptor`
- `setItalic(TextItalic $italic): FontDescriptor`
- `setSize(float $size): FontDescriptor`
- `setStretch(TextStretch $stretch): FontDescriptor`
- `setWeight(TextWeight $weight): FontDescriptor`
- `size(): float`
- `stretch(): TextStretch`
- `toCData(): CData`
- `weight(): TextWeight`

### `RichText`

`Libui\Text\RichText`

Small facade for building styled text and producing measured TextLayout instances without repeating the AttributedString/FontDescriptor dance.

- `static create(?TextStyle $defaultStyle = null): RichText`
- `append(string $text, ?TextStyle $style = null): RichText`
- `height(float $width, DrawTextAlign $align = DrawTextAlign::Left): float`
- `layout(float $width, DrawTextAlign $align = DrawTextAlign::Left): TextLayout`
- `measure(float $width, DrawTextAlign $align = DrawTextAlign::Left): array`
- `string(): AttributedString`

### `TextLayout`

`Libui\Text\TextLayout`

A laid-out, ready-to-draw block of attributed text, wrapping uiDrawTextLayout*.

- `__construct(AttributedString $string, ?FontDescriptor $font = null, float $width = 0, DrawTextAlign $align = DrawTextAlign::Left)`
- `extents(): array` — Measure the laid-out text. Returns [width, height] in points — the actual extents after wrapping at the layout width. (Wraps uiDrawTextLayoutExtents, whose two `double *` out-params are otherwise awkward to call directly.)
- `extentsCData(): CData` — Get the extents as FFI \FFI\CData (the underlying C array).
- `free(): void` — Free the native layout. Idempotent, and runs automatically on destruction.
- `handle(): CData`
- `height(): float`
- `setFont(FontDescriptor $font): TextLayout`
- `setWidth(float $width): TextLayout`
- `width(): float`

### `TextStyle`

`Libui\Text\TextStyle`

High-level text style that can produce both a default layout font and span attributes for an AttributedString.

- `__construct(?string $family = null, ?float $size = null, ?TextWeight $weight = null, ?TextItalic $italic = null, ?TextStretch $stretch = null, Color|array|null $color = null, Color|array|null $background = null, ?Underline $underline = null)`
- `attributes(): array`
- `font(): FontDescriptor`
- `with(?string $family = null, ?float $size = null, ?TextWeight $weight = null, ?TextItalic $italic = null, ?TextStretch $stretch = null, Color|array|null $color = null, Color|array|null $background = null, ?Underline $underline = null): TextStyle`

## Async & utilities

### `Image`

`Libui\Image`

Image widget and helper for working with uiImage.

- `static fromPng(string $path): static` — Creates an Image from a PNG file.
- `static fromRgba(string $rgbaData, int $width, int $height): static` — Creates an Image from raw RGBA bytes.
- `__construct(float $width, float $height)` — Creates a new empty image with the specified dimensions.
- `append(string $pixels, int $pixelWidth, int $pixelHeight, int $byteStride): void` — Appends RGBA pixel data to the image.
- `free(): void` — Frees the image and releases its resources.
- `handle(): ?CData` — Returns the native uiImage handle.

### `Clipboard`

`Libui\Utils\Clipboard`

Minimal cross-platform clipboard access.

- `static copy(string $text): bool` — Put $text on the system clipboard. Returns false if no tool is available.
- `static paste(): ?string` — Read the clipboard's text contents, or null if unavailable.

## Dialogs

### `Ui`

`Libui\Generated\Ui`

GENERATED facade for libui free functions (dialogs, etc.). DO NOT EDIT.

- `static msgBox(Control $parent, string $title, string $description): void` — Message box dialog window. A message box displayed in a new window indicating a common message.
- `static msgBoxError(Control $parent, string $title, string $description): void` — Error message box dialog window. A message box displayed in a new window indicating an error. On some systems this ma...
- `static openFile(Control $parent): string` — File chooser dialog window to select a single file.
- `static openFolder(Control $parent): string` — Folder chooser dialog window to select a single folder.
- `static saveFile(Control $parent): string` — Save file dialog window. The user is asked to confirm overwriting existing files, should the chosen file path already...

## Enums

- **`Align`** — Fill, Start, Center, End
- **`At`** — Leading, Top, Trailing, Bottom
- **`AttributeType`** — Family, Size, Weight, Italic, Stretch, Color, Background, Underline, UnderlineColor, Features
- **`DrawBrushType`** — Solid, LinearGradient, RadialGradient, Image
- **`DrawFillMode`** — Winding, Alternate
- **`DrawLineCap`** — Flat, Round, Square
- **`DrawLineJoin`** — Miter, Round, Bevel
- **`DrawTextAlign`** — Left, Center, Right
- **`ExtKey`** — Escape, Insert, Delete, Home, End, PageUp, PageDown, Up, Down, Left, Right, F1, F2, F3, F4, F5, F6, F7, F8, F9, F10, F11, F12, N0, N1, N2, N3, N4, N5, N6, N7, N8, N9, NDot, NEnter, NAdd, NSubtract, NMultiply, NDivide
- **`SortIndicator`** — None, Ascending, Descending
- **`TableSelectionMode`** — None, ZeroOrOne, One, ZeroOrMany
- **`TableValueType`** — String, Image, Int, Color
- **`TextItalic`** — Normal, Oblique, Italic
- **`TextStretch`** — UltraCondensed, ExtraCondensed, Condensed, SemiCondensed, Normal, SemiExpanded, Expanded, ExtraExpanded, UltraExpanded
- **`TextWeight`** — Minimum, Thin, UltraLight, Light, Book, Normal, Medium, SemiBold, Bold, UltraBold, Heavy, UltraHeavy, Maximum
- **`UiForEach`** — ForEachContinue, Stop
- **`Underline`** — None, Single, Double, Suggestion
- **`UnderlineColor`** — Custom, Spelling, Grammar, Auxiliary
- **`WindowResizeEdge`** — Left, Top, Right, Bottom, TopLeft, TopRight, BottomLeft, BottomRight
- **`Modifiers`** — flags/constants

