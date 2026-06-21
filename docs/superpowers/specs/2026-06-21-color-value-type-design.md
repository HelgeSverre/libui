# Design: `Color` value type

**Date:** 2026-06-21
**Status:** Approved (design)
**Scope:** A single, foundational facade — the `Libui\Color` value type — plus additive integration into the existing color-consuming APIs.

## Context

A survey of the binding's developer experience found that color is represented
inconsistently across the API. The same RGBA value appears as:

- `[r, g, b, a]` float arrays — `DrawContext::drawString()`, `Attribute::color()`
- `Brush::rgb(0xRRGGBB)` hex **and** `Brush::solid(r, g, b, a)` floats
- four positional floats — `ColorButton::setColor()`
- gradient stop tuples `[pos, r, g, b, a]` (a genuine miscount footgun)
- raw-only for per-row table background color

A single immutable `Color` value type is the connective tissue: every other
planned facade (Tables, Menus, Drawing) consumes color. Building it first means
the rest read cleanly. This spec covers **only** `Color` and its integration —
the gradient `Stop` type and other drawing sugar are deferred to the Drawing spec.

This is an additive, non-breaking change: the library shipped `v0.1.0`, so every
existing float/hex color API stays working. We only **add** `Color`-accepting
paths and route the existing hex helpers through `Color` internally.

## Goals

- One typed, immutable way to express a color, constructible the way people
  actually write colors (hex int, hex string, 0..1 floats, 8-bit ints, named).
- Accepted everywhere a color is consumed today, without breaking current call sites.
- Pure-PHP and FFI-free in the type itself, so it is trivially unit-testable.

## Non-goals

- Color-space math (HSL/HSV conversion, blending, contrast). YAGNI for now.
- A large named palette. Three names only (`black`, `white`, `transparent`).
- The gradient `Stop` type and other drawing/path sugar (separate Drawing spec).
- Replacing or deprecating the existing float/hex APIs.

## The type

**Location:** `Libui\Color` (top-level — shared by `Draw/`, `Text/`, and widgets,
so it does not belong under any one of them).

**Shape:** `final class Color`, immutable, with `public readonly float $r, $g, $b, $a`
stored normalized in `0..1` (libui-native units). Private constructor; all
construction goes through the named static factories.

### Construction

```php
Color::rgb(int $hex, float $a = 1.0): self        // 0x312B90
Color::rgba(float $r, float $g, float $b, float $a = 1.0): self  // 0..1
Color::rgb255(int $r, int $g, int $b, float $a = 1.0): self      // 0-255
Color::hex(string $hex): self                     // '#RGB' | '#RRGGBB' | '#RRGGBBAA' (leading '#' optional)
Color::black(): self                              // rgba(0,0,0,1)
Color::white(): self                              // rgba(1,1,1,1)
Color::transparent(): self                        // rgba(0,0,0,0)
```

`Color::hex()` accepts, case-insensitively, with or without a leading `#`:
- `#RGB` → expanded (`#abc` → `#aabbcc`)
- `#RRGGBB` → alpha defaults to 1.0
- `#RRGGBBAA` → alpha from the last byte

### Derivation (immutable)

```php
$c->withAlpha(float $a): self     // returns a new Color; clamps $a to 0..1
```

### Interop

```php
$c->toArray(): array{float, float, float, float}   // [r, g, b, a] for float-array consumers
$c->toHex(): int                                   // 0xRRGGBB (alpha dropped)
```

### Validation

- `rgb255`: each channel must be `0..255`, else `\InvalidArgumentException`.
- `hex` / `Color::rgb` hex int: must be in range (`hex` string must match the
  allowed shapes; `rgb` int must be `0x000000..0xFFFFFF`), else `\InvalidArgumentException`.
- Float inputs (`rgba`, the `$a` on every factory, `withAlpha`): **clamped** to
  `0..1`, no throw. Float math drifts; clamping is forgiving and matches what
  libui expects at the boundary.

## Integration (additive, non-breaking)

Each consumer gains a `Color`-accepting path; existing signatures are untouched.
Where a hex helper already exists, it is reimplemented in terms of `Color` so the
conversion math lives in exactly one place.

| Consumer | Added | Kept (unchanged) |
|---|---|---|
| `Libui\Draw\Brush` | `Brush::color(Color $c): self` | `solid(r,g,b,a)`, `rgb(int $hex, $a)` (now delegate to `Color`) |
| `Libui\Text\Attribute` | `Attribute::fromColor(Color $c): self`, `Attribute::backgroundFromColor(Color $c): self` | `color(...)`, `background(...)`, `rgb(int $hex, $a)` (now delegates to `Color`) |
| `Libui\Draw\DrawContext::drawString` | accepts `Color\|array{float,float,float,float}` for the color arg | `array` form still works |
| `Libui\ColorButton` (hand-written sugar, currently empty) | `getColor(): Color`, `setColor(Color $c): self` | generated float `setColor(r,g,b,a)` stays |

Notes:
- `Brush::rgb()` and `Attribute::rgb()` keep their existing signatures and return
  types (`Brush` / `Attribute`); only their internal implementation changes to
  build a `Color` and read its channels. No call-site churn.
- `DrawContext::drawString()` widens its color parameter to a union; an `array`
  is normalized via a private helper, a `Color` via `->toArray()`.
- `ColorButton::getColor()` wraps the awkward generated getter (which writes into
  four output pointers) and returns a `Color`. `setColor(Color)` forwards to the
  generated float setter.

## Components & boundaries

- **`Libui\Color`** — pure value type. No dependencies on FFI or any other
  Libui class. Knows: how to construct/validate/normalize, and how to export
  (`toArray`, `toHex`). This is the entire unit; everything else just consumes it.
- **Consumers** (`Brush`, `Attribute`, `DrawContext`, `ColorButton`) depend on
  `Color`; `Color` depends on nothing. One-directional, no cycles.

## Error handling

- Invalid `rgb255` channel or malformed `hex` → `\InvalidArgumentException` with a
  message naming the bad value (e.g. `"Color::rgb255() channel out of range: 300"`).
- Out-of-range floats are clamped silently (documented behavior, not an error).

## Testing

`Color` itself is FFI-free, so the bulk is fast pure-unit tests (a new
`tests/ColorTest.php`, extending `PHPUnit\Framework\TestCase`):

- **Equivalence:** `Color::rgb(0x312B90)`, `Color::rgb255(49, 43, 144)`, and
  `Color::hex('#312B90')` all produce equal channel values.
- **Round-trips:** `toHex()` of a hex-constructed color returns the original int;
  `toArray()` returns `[r, g, b, a]` in order.
- **Hex parsing:** `#RGB` expansion, `#RRGGBB`, `#RRGGBBAA` alpha, optional `#`,
  case-insensitivity.
- **Alpha & immutability:** `withAlpha()` returns a new instance and leaves the
  original unchanged; alpha clamping.
- **Validation:** `rgb255(300, …)` and malformed `hex` throw
  `\InvalidArgumentException`; out-of-range floats clamp rather than throw.
- **Named colors:** `black/white/transparent` channel values.

Integration coverage (in the relevant existing test files, FFI-backed where the
class needs libui):

- `Brush::color(Color)` constructs a usable brush; `Brush::rgb()` still behaves
  identically after the internal refactor (regression).
- `Attribute::fromColor(Color)` produces a valid attribute; `Attribute::rgb()`
  unchanged.
- `DrawContext::drawString()` accepts both a `Color` and an `array`.
- `ColorButton` set/get round-trip: `setColor(Color)` then `getColor()` returns an
  equal color (within float tolerance).

All work must keep the gate green: `composer test`, `composer stan` (level 6),
`composer format:check`, `composer lint`. `Color` is hand-written (not generated),
so no `composer regen` interaction.

## Rollout

1. Add `src/Color.php` + `tests/ColorTest.php` (pure type, fully tested in isolation).
2. Wire each consumer additively, with its regression/round-trip test, one class
   at a time.
3. Update docs: a short "Colors" note in `docs/GUIDE.md`; `docs/API.md` regenerates
   via `composer docs:api`.
4. Mention `Color` in the README feature list.

Follow-up specs (not this one) build on `Color`: **Tables** (row background color,
`fromRows`, auto-lifetime, callbacks), **Menus** (ordering safety, dialog helpers),
**Drawing** (gradient `Stop`, path shape sugar, `StrokeParams` builder,
`DrawContext` fill helpers).
