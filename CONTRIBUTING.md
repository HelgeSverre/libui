# Contributing

Thanks for helping out. This binding is mostly machine-generated from libui-ng's
`ui.h`, so the most important thing to know is **what you may edit and what you
must not**.

## Generated vs. hand-written

The library is split into two layers:

- **`src/Generated/**` is machine-written. Never edit it by hand.** The generator
  (`tools/generate.php`) rewrites every file under `src/Generated/` on each run.
  Any change you make there is lost the next time someone runs `composer regen`.
  This includes `Generated\<Widget>` classes, `Generated\Enum\*`, `Generated\Flags\*`,
  and `Generated\Ui` (the dialog facade).

- **Public sugar classes are hand-editable.** Each widget has a thin public class
  `Libui\<Widget>` in `src/<Widget>.php` that `extends Generated\<Widget>`. These
  exist precisely so you have somewhere safe to add convenience methods, overrides,
  and documentation that survives regeneration. Edit these freely.

- The hand-written runtime — `src/Ffi.php`, `src/Control.php`, `src/Area.php`,
  `src/AreaDelegate.php`, `src/Draw/**`, `src/Text/**`, the table subsystem
  (`src/Table.php`, `src/TableModel.php`, `src/TableModelDelegate.php`), and the
  `src/Color.php` value type — is also hand-maintained. The generator scaffolds a
  sugar stub only when one is missing; it never clobbers an existing hand-written file.

## Regenerating

The full pipeline from a clean checkout, in order:

```sh
composer build-lib   # build/refresh lib/libui.dylib from third_party/libui-ng
composer regen       # regenerate src/Native/libui.gen.h + src/Generated/**
composer gate        # assert FFI::cdef accepts the full generated header
```

`composer build-lib` re-clones `third_party/libui-ng` if needed (it is not
tracked in git — see `.gitignore`), so you need `meson` and `ninja` installed
(`brew install meson ninja`). Run `composer gate` (and ideally `composer smoke`)
after any regen to confirm the header still binds and widgets still construct.

Before sending a change, also run the static analysis and tests:

```sh
composer test        # the full PHPUnit suite (tests/, the Libui\Tests namespace)
composer stan        # PHPStan (level 6, with FFI-dynamic errors baselined)
```

Tests are PHPUnit. `composer gate` and `composer smoke` run focused subsets via
`@group gate` / `@group smoke`; new tests extend `Libui\Tests\LibuiTestCase`
(which initialises libui once for the process).

## Adding a widget convenience method

Add it to the **sugar class**, not the generated one. For example, to add a
helper to the button:

```php
// src/Button.php
namespace Libui;

class Button extends Generated\Button
{
    /** Set the label and return $this for chaining. */
    public function label(string $text): static
    {
        return $this->setText($text);
    }
}
```

Because `Libui\Button` extends `Generated\Button`, you inherit the full generated
API and your method rides on top. The generated class keeps its `DO NOT EDIT`
header for a reason — put your code here instead.

## Key conventions

Follow these throughout `src/`:

- **`declare(strict_types=1);`** at the top of every PHP file.
- **`namespace Libui;`** (sub-namespaces `Libui\Generated`, `Libui\Draw`, etc.).
- **Always use a leading backslash for the global FFI class: `\FFI`,
  `\FFI\CData`, `\FFI::addr()`, `\FFI::string()`.** Our own class is
  `Libui\Ffi`, which collides case-insensitively with PHP's built-in `FFI`.
  Writing `FFI` unqualified inside `namespace Libui` resolves to the wrong thing
  on some setups — the leading `\` is mandatory.
- **Retain callbacks or the native trampoline is freed.** A PHP closure handed to
  C as a function pointer will be garbage-collected while libui still holds the
  pointer, crashing mid-event-loop. Always wrap it in `Control::keep($cb)` (which
  stores it for the process lifetime) before passing it to a `uiXOn*` function.
- **libui structs are passed by pointer.** Build a struct with `Ffi::get()->new()`
  or `Ffi::new()`, take its address with `\FFI::addr(...)`, and **keep the
  `\FFI\CData` alive** (e.g. assign it to a retained property) for as long as C
  may dereference the pointer. See `src/Draw/Brush.php` for the pattern of holding
  both the struct and any backing array on the object.
- **Owned `char *` returns must be freed.** Functions like `uiButtonText` return
  a heap string libui owns; copy it into PHP and free it with `uiFreeText` via
  `Ffi::ownedString($ptr)`. Use `Ffi::borrowedString($ptr)` for strings libui
  does **not** hand ownership of (do not free those).

## The ~2% irregular cases

The generator maps ~98% of libui mechanically from the naming convention. The
remaining irregular cases live in **`tools/annotations.php`** — a hand-maintained
table covering: types to skip (`uiControl`, `uiArea`, `uiTable`), multi-constructor
types and their factory methods (e.g. `Box::horizontal()`, `Entry::password()`),
`int`-as-`bool` functions, bit-flag enums, the dialog facade function list, and
callbacks with a non-standard trampoline shape (`uiWindowOnClosing`,
`uiMenuItemOnClicked`). If you are wrapping a libui feature the generator gets
wrong, the fix usually belongs there — not in the generated output.
