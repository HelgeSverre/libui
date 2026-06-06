# Architecture

This document explains how `helgesverre/libui` turns libui-ng's C header into a
typed, object-oriented PHP API — and the handful of runtime rules you have to
respect when you touch the FFI boundary.

It is the companion to [CONTRIBUTING.md](../CONTRIBUTING.md), which is the
practical "what may I edit" guide. This one is the "why is it shaped this way".

---

## 1. The problem

PHP's official desktop-GUI story is the [`ext-ui` PECL extension](https://www.php.net/manual/en/book.ui.php),
which is itself a binding to [`libui`](https://github.com/andlabs/libui). It is
**abandoned and PHP 7-only**: the last release (`2.0.0`) is from July 2018, its C
is written against PHP 7's Zend API, and it does not compile on PHP 8.x — `pecl
install ui` fails at `configure`. There is no maintained native-GUI extension for
modern PHP.

The opportunity: the actively-maintained fork [`libui-ng`](https://github.com/libui-ng/libui-ng)
still ships a clean C library, and PHP 8.3+ has a capable built-in **FFI**
extension. So instead of compiling a PHP extension, we load `libui-ng`'s shared
library at runtime and call it directly from PHP. No C toolchain on the user's
machine, no PECL, no Zend API — just a `.dylib`/`.so`/`.dll` and a header.

The catch with raw FFI is ergonomics: you would be writing
`$ffi->uiButtonSetText($handle, 'Save')` everywhere, juggling `\FFI\CData`
pointers, freeing C strings by hand, and keeping callbacks alive yourself. The
goal of this library is to make `(new Button('Save'))->onClicked(...)` work
without giving up on the 299 raw functions underneath.

---

## 2. The key insight: one parse, three tiers

libui's header is **~98% mechanically regular**. Almost every function follows
the shape `ui<Type><Verb>(<Type> *self, ...)`, constructors are `uiNew<Type>`,
getters are `ui<Type><Prop>`, setters are `ui<Type>Set<Prop>`, and events are
`ui<Type>On<Event>`. That regularity means a single generator can parse `ui.h`
**once** and emit both the FFI header and a typed OO class for every widget.

```
third_party/libui-ng/ui.h
        │
        │  tools/generate.php  (one parse)
        ▼
  ┌─────────────────────────────────────────────────────────────────────┐
  │ TIER 0 — cleaned FFI header (generated, committed)                  │
  │   src/Native/libui.gen.h     all 299 functions callable via FFI     │
  ├─────────────────────────────────────────────────────────────────────┤
  │ TIER 1 — typed OO layer (generated, committed)                      │
  │   src/Generated/<Widget>.php   23 widget classes                    │
  │   src/Generated/Enum/*.php     19 backed enums                      │
  │   src/Generated/Flags/*.php    bit-flag const classes (uiModifiers) │
  │   src/Generated/Ui.php         dialog/free-function facade          │
  ├─────────────────────────────────────────────────────────────────────┤
  │ TIER 2 — hand-written runtime + hard subsystems (never regenerated) │
  │   src/Ffi.php  src/Control.php           singleton FFI, base class  │
  │   src/Area.php src/AreaDelegate.php      custom-draw surface        │
  │   src/Draw/**                            Path/Brush/Stroke/Context  │
  │   src/Text/**                            attributed strings + layout│
  │   src/Table.php src/TableModel*.php      data-grid model adapter    │
  │   src/<Widget>.php                       hand-editable sugar stubs  │
  └─────────────────────────────────────────────────────────────────────┘
```

- **Tier 0** (`src/Native/libui.gen.h`) is a version of `ui.h` that PHP's
  `FFI::cdef()` accepts. Once bound, **all 299 functions are callable**, even the
  ones that never get a sugar wrapper — they are always reachable as
  `Ffi::get()->uiSomething(...)`.

- **Tier 1** (`src/Generated/**`) is the mechanical OO layer: one class per
  widget, one PHP enum per C enum, one facade for the free functions (dialogs).
  Every file carries a `DO NOT EDIT` header — the generator rewrites them
  wholesale on each `composer regen`.

- **Tier 2** is everything a parser can't infer: the FFI runtime
  (`Ffi`, `Control`), the public sugar classes (`Libui\Button extends
  Generated\Button`), and the two "hard subsystems" that are vtable-/struct-heavy
  rather than naming-regular — custom 2D drawing (`Area` + `Draw\*`) and the data
  grid (`Table` + `TableModel`). The text layer (`Text\*`) lives here too, since
  it leans on by-pointer structs with lifetime concerns. None of these are ever
  regenerated.

The split is enforced by namespace: `Libui\Generated\*` is machine-written;
`Libui\{Ffi,Control,Area,Button,…}`, `Libui\Draw\*`, and `Libui\Text\*` are
hand-written.

---

## 3. The generator pipeline

`tools/generate.php` runs four stages: **clean → parse → group-by-type → emit**.

### 3.1 `cleanHeader()` — `ui.h` → `libui.gen.h`

PHP's `FFI::cdef()` is not a full C preprocessor, so the raw header has to be
massaged into something it will parse. This is pure PHP — no `cpp` dependency:

1. **Expand the enum macro.** `_UI_ENUM(uiAlign)` → `typedef unsigned int uiAlign;
   enum`. The enum bodies (including explicit values like `= 1 << 0` and `= 100`)
   stay valid.
2. **Strip the visibility token** `_UI_EXTERN` from every declaration.
3. **Remove comments** (block then line). The human-readable summaries are
   harvested from the *raw* header before this happens (see §3.5).
4. **Give `struct tm` a concrete layout.** `ui.h` forward-declares it for
   `uiDateTimePicker`; we inject the 9 standard `int` fields. This is pointer-safe
   because libui only ever passes it by pointer and touches those fields.
5. **Drop preprocessor lines** (`#include`, `#ifdef`, `#pragma`), the C++
   `extern "C" {` guard and its lone closing `}`. The only includes are
   `<stddef.h>`/`<stdint.h>`, whose types (`size_t`, `uintN_t`) FFI already knows.

The 26 `#define uiX(this) ((uiX*)(this))` cast macros are also dropped — they are
call-site-only and no declaration references them. But they are not thrown away:
they are the **authoritative widget list** (see §3.3).

The result is written to `src/Native/libui.gen.h`. Proving that `FFI::cdef()`
accepts this header over all 299 functions, the enums, the function-pointers in
structs (`uiControl`, `uiAreaHandler`, `uiTableModelHandler`), and the injected
`struct tm` is the project's pivotal de-risking gate — see `composer gate`
(the PHPUnit `@group gate`, `tests/HeaderGateTest.php`).

### 3.2 Parsing

- `parseFunctions()` collapses struct/enum bodies to `{}`, splits the header on
  `;`, and matches each function prototype into `{name, ret, params}`. Splitting
  on `;` first is important: ~30 declarations span multiple physical lines
  (e.g. `uiWindowOnClosing`), and a line-by-line parser would break them.
- `parseParams()` splits a parameter list on **top-level** commas only, tracking
  paren depth so the inner commas of a function-pointer parameter
  (`void (*f)(uiButton *, void *)`) don't split it.
- `parseEnums()` reads the expanded `typedef unsigned int NAME; enum { ... }`
  blocks and evaluates each member's value (decimal, hex, `A << B`, or the running
  counter).

### 3.3 Grouping by type (longest-prefix match)

The 26 cast-macro names from `ui.h` are the canonical widget list, sorted
**longest-first**. Each function is assigned to the widget whose name is the
**longest prefix** of the function name (with the next char upper-case). Longest-
first matters: `uiEditableComboboxText` must bind to `EditableCombobox`, not
`Combobox`; `uiMenuItemAppend...` to `MenuItem`, not `Menu`.

Functions that match no widget (`uiMsgBox`, `uiOpenFile`, `uiMain`, `uiTimer`,
`uiDraw*`, `uiNew*Attribute`, …) are "free functions". A curated subset of them
(the dialogs) is emitted onto the static `Generated\Ui` facade; the rest stay
raw-callable through `Ffi::get()`.

### 3.4 The convention → method mapping

| C function shape | PHP emitted | Notes |
|---|---|---|
| `uiNew<T>(args)` | `__construct(args)` | the `self` arg is the new handle |
| extra `uiNew<X>` for the same `T` | static factory | e.g. `Box::horizontal()`, `Entry::password()` |
| `ui<T>Set<P>(self, v)` | `setP(v): static` | fluent — returns `$this` |
| `ui<T><P>(self)` | `p(): T` | getter |
| `ui<T>On<E>(self, fn, data)` | `onE(callable): static` | callback wrapped in `Control::keep` |
| `ui<T><Verb>(self, …)` | `verb(…)` | actions: `append`/`delete`/`clear`/… |

Argument and return marshalling is type-driven (`classify()`):
`const char *` in → `string`; an **owned** `char *` return → copied into PHP and
freed with `uiFreeText` (`Ffi::ownedString`); a **borrowed** `const char *`
return → copied, not freed (`Ffi::borrowedString`); an enum param/return → the
generated PHP enum (`->value` / `::from()`); a cross-type `uiX *child`
parameter → a typed `Control`, upcast at the call site via
`Ffi::control($child->handle())`; bool-ish `int`s → `bool`.

Setters and `void` functions are emitted as **fluent** (returning `$this`), which
is why `(new Box())->setPadded(true)->append(...)` chains.

### 3.5 Doc-comment harvesting

`harvestDocs()` reads the **raw** header (before comments are stripped) and pulls
a one-line summary from the comment block immediately preceding each documented
function/enum. It handles both libui's doxygen `/** … */` blocks and older `//`
line-comment runs, sanitises the text (collapses whitespace, caps length, and
neutralises any `*/` so a summary can't close the docblock early), and folds it
into the emitted method's docblock. Symbols with no usable comment fall back to a
bare `/** @see uiX */`. This is what gives `Generated\Button::setText()` the
docblock "Sets the button label text."

---

## 4. `tools/annotations.php` — the irregular ~2%

The remaining ~2% the convention can't express lives in one hand-maintained
table. It is small on purpose; if you are wrapping a libui feature the generator
gets wrong, the fix usually belongs here, not in generated output.

- **`skip_types`** — `uiControl`, `uiArea`, `uiTable`. These get no generated
  class: `uiControl` is the hand-written base, `uiArea` and `uiTable` are
  hand-written adapters. Their member functions stay raw-callable.
- **`constructors`** — multi-constructor types and their factory methods, since
  one C type can have several `uiNew*`:
  - `uiBox` → `new Box()` (vertical) + `Box::horizontal()`
  - `uiSeparator` → horizontal default + `Separator::vertical()`
  - `uiEntry` → plain + `Entry::password()` + `Entry::search()`
  - `uiMultilineEntry` → wrapping default + `MultilineEntry::nonWrapping()`
  - `uiDateTimePicker` → datetime default + `DateTimePicker::dateOnly()` +
    `DateTimePicker::timeOnly()` (named `timeOnly` to avoid clashing with the
    `time()` getter)
- **`bool_funcs`** — `int`-typed getters/setters that are semantically `bool`
  (`uiBoxSetPadded`, `uiCheckboxChecked`, …). Cosmetic: anything not listed stays
  `int` and still works.
- **`flag_enums`** — `['uiModifiers']`. Bit-flags can't be PHP backed enums (you
  can't OR them), so this one is emitted as a `final class` of `const int` plus a
  `has($mask, $flag)` helper.
- **`facade_funcs`** — the free functions exposed on `Generated\Ui` (`uiMsgBox`,
  `uiMsgBoxError`, `uiOpenFile`, `uiOpenFolder`, `uiSaveFile`).
- **`deviating_callbacks`** — the two events whose trampoline isn't the usual
  `void (*)(sender, data)`:
  - `uiWindowOnClosing` → callback returns `int`; the trampoline coerces a PHP
    `bool`/`int` return (default `1` = destroy).
  - `uiMenuItemOnClicked` → callback gets an extra `uiWindow *`; the trampoline
    passes that window through to the PHP callback as a second argument.

  (Two further deviations are structural, not table entries: the `uiTable` `On*`
  callbacks `(uiTable*, int row, void*)` are handled inside the hand-written
  `Table`/`TableModel`, and the `uiDraw*`/`uiNew*Attribute` constructors belong to
  the hand-written `Draw\*`/`Text\*` classes and are only emitted raw.)

---

## 5. Runtime rules (the FFI boundary)

These are the non-negotiable invariants. Most crashes at the FFI boundary trace
back to violating one of them.

### 5.1 `\FFI` vs `Libui\Ffi` — the case-collision

Our singleton handle class is `Libui\Ffi`. PHP's built-in FFI class is `FFI`.
These collide **case-insensitively**, and inside `namespace Libui` an unqualified
`FFI` resolves to the wrong one on some setups. **Always write the global class
with a leading backslash**: `\FFI::addr()`, `\FFI::string()`, `\FFI\CData`. This
is a hard rule throughout `src/`.

### 5.2 Callbacks must be retained, or the trampoline is freed

When you hand a PHP closure to C as a function pointer, FFI builds a native
trampoline for it. If PHP garbage-collects the closure while libui still holds the
pointer, the next event fires into freed memory and the process crashes
mid-loop — the single most common failure mode.

The fix is to keep the closure alive for as long as C might call it:

- Widget events use `Control::keep($cb)`, a **static** store on `Control`. Static
  on purpose: even if the owning widget object is GC'd, the live native trampoline
  survives.
- Event-loop helpers (`Ffi::queueMain`, `Ffi::timer`, `Ffi::onShouldQuit`) retain
  their closures in `Ffi::$retained` for the process lifetime.
- The drawing and table adapters keep their vtable closures on the owning object
  (`Area`, `TableModel`).

### 5.3 Structs are passed by pointer — keep the `CData` alive

libui takes most structs by pointer. The pattern (see `Draw\Brush`,
`Text\FontDescriptor`, `Table`): build the struct with `Ffi::get()->new(...)`,
take its address with `\FFI::addr(...)`, pass that pointer — and **retain the
`\FFI\CData`** (and any backing C array, like a gradient's stops or a font's
family buffer) on a property for as long as C may dereference it. A struct that
goes out of scope between `addr()` and the C call is a use-after-free.

### 5.4 Owned vs borrowed strings

A C function that returns a heap `char *` libui owns (e.g. `uiButtonText`,
`uiEntryText`) must be **copied into PHP and freed** with `uiFreeText` —
`Ffi::ownedString($ptr)` does both. A `const char *` libui does **not** hand
ownership of must be copied but **not** freed — `Ffi::borrowedString($ptr)`. Get
this wrong and you leak (forgot to free an owned string) or double-free/crash
(freed a borrowed one). The generator picks the right helper from the `const`
qualifier; the few exceptions are annotated.

One related historical bug this codifies: `uiInit` returns an error string freed
by **`uiFreeInitError`**, not `uiFreeText` (`Ffi::init()` gets this right).

### 5.5 Never let an exception escape into a C callback

A PHP `\Throwable` propagating out of an FFI callback is a hard fatal
("throwing from FFI callbacks is not allowed"). Every trampoline that runs inside
libui's event loop — area handlers, table-model handlers, `queueMain`/`timer`/
`onShouldQuit` — wraps the user code in a `guard()`/`try` that reports to STDERR
and returns a safe fallback instead of throwing.

---

## 6. Generated vs hand-written, and the regen workflow

**Generated (never edit — rewritten every `composer regen`):**

- `src/Native/libui.gen.h`
- `src/Generated/**` — widget classes, enums, flags, the `Ui` facade

**Hand-written (the generator never clobbers these):**

- Runtime: `src/Ffi.php`, `src/Control.php`
- Drawing subsystem: `src/Area.php`, `src/AreaDelegate.php`, `src/Draw/**`
- Text subsystem: `src/Text/**`
- Table subsystem: `src/Table.php`, `src/TableModel.php`,
  `src/TableModelDelegate.php`
- Public sugar: `src/<Widget>.php`, each `extends Generated\<Widget>`. The
  generator scaffolds one of these **only if it's absent**, so any convenience
  method you add survives regeneration. Add methods here, not in `Generated`.

**The regen pipeline** (from a clean checkout, in order):

```sh
composer build-lib   # build/refresh lib/libui.* from third_party/libui-ng (needs meson + ninja)
composer regen       # ui.h -> src/Native/libui.gen.h + src/Generated/**
composer test        # the full PHPUnit suite
composer gate        # PHPUnit @group gate — FFI::cdef accepts the header  (the pivot)
composer smoke       # PHPUnit @group smoke — construct widgets, no event loop
composer stan        # PHPStan level 6 (FFI-dynamic errors baselined)
```

`build-lib` re-clones `third_party/libui-ng` if needed (it is not tracked in git).
`gate` is the decisive check: if FFI rejects any construct in the cleaned header,
the whole layer is blocked, so it is kept isolated and fast to iterate.

See [CONTRIBUTING.md](../CONTRIBUTING.md) for the day-to-day rules.
