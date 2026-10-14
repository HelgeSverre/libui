# libui-ng source patches

These diffs mirror fixes in our maintained [libui-ng fork](https://github.com/HelgeSverre/libui-ng),
pinned by `LIBUI_REF` in [`../build-libui.sh`](../build-libui.sh). The build checks
each patch and skips fixes already present in the pinned source. The standalone
diffs keep each change reviewable and detect a revision bump that drops a fix.

## How they're applied

`build-libui.sh`, immediately after the pinned `git checkout $LIBUI_REF`, loops over
`patches/*.patch` and applies each to `third_party/libui-ng`. The step is idempotent
and fail-loud:

- if a patch is already present (`git apply --reverse --check` succeeds), it is skipped;
- else if it applies cleanly (`git apply --check` succeeds), it is applied;
- otherwise the build **aborts** so a `LIBUI_REF` bump cannot silently drop the fix.

Patches are unified diffs relative to the `third_party/libui-ng` work tree root
(paths like `windows/drawtext.cpp`), produced with:

```sh
git -C third_party/libui-ng diff -- windows/drawtext.cpp > patches/windows-color-emoji.patch
```

## Patches

### `windows-color-emoji.patch`

- **What:** Makes color-emoji fonts (e.g. Segoe UI Emoji) render in **color** on the
  Windows (Direct2D/DirectWrite) backend instead of monochrome.
- **Why:** libui-ng's custom `textRenderer::DrawGlyphRun`
  (`windows/drawtext.cpp`) drew every glyph run with a single solid brush via
  `ID2D1RenderTarget::DrawGlyphRun`, which only ever produces monochrome output.
  macOS (CoreText) already renders the same emoji in color, so the platforms diverged.
- **Fix:** Decompose each glyph run into its color layers with
  `IDWriteFactory2::TranslateColorGlyphRun` (COLR/CPAL layered fonts, Windows 8.1+)
  and draw each layer with its own brush — the existing foreground brush for layers
  whose `paletteIndex == 0xFFFF`, a temporary solid brush of `runColor` otherwise.
  On `DWRITE_E_NOCOLOR` / `E_NOTIMPL` (not a color font) or when the v2 factory is
  unavailable (pre-8.1), it falls back byte-for-byte to the original monochrome draw,
  so normal text is unaffected. Adds `#include <dwrite_2.h>` and a cached
  `IDWriteFactory2*` QI on the renderer. Scope is limited to COLR/CPAL fonts; CBDT/sbix
  bitmap and SVG color fonts (which need `ID2D1DeviceContext4`) are out of scope.
- **Upstream issue:** [helgesverre/libui#1](https://github.com/helgesverre/libui/issues/1)
  == [libui-ng#344](https://github.com/libui-ng/libui-ng/issues/344). Remove this patch
  once the fix lands upstream and `LIBUI_REF` is bumped past it.
- **Scope:** custom-drawn `uiArea` text. Native entry controls are covered by
  `windows-entry-color.patch` below.

### `windows-entry-color.patch`

- **What:** Renders color emoji in native Windows `uiEntry` and
  `uiMultilineEntry` controls ([issue #2](https://github.com/HelgeSverre/libui/issues/2)).
- **Fix:** Retain the system EDIT control for input, selection, scrolling,
  clipboard, and accessibility. After its normal paint, draw only color-font
  clusters with DirectWrite at EDIT's own character positions. Preserve the
  native image and clipping region; ordinary text is unchanged. Selected
  clusters retain native selection painting, masked fields never expose their
  underlying glyphs, and active IME composition uses the system painter.
- **Compatibility:** Uses the same COLR/CPAL color-font API as the Area renderer.
  If that API or a suitable render target is unavailable, native painting remains.
- **Verification:** `scripts/windows-entry-color-test.cpp` captures actual
  visible controls. It fails the color checks with the old DLL and passes all
  14 checks with the patched DLL, including selection, scrolling, masking
  enabled after construction, and change callbacks. CI runs it against the
  committed Windows DLL.

### `darwin-accessible-drag.patch`

- **What:** Allows complete accessibility-generated mouse event sequences to
  move a window through libui's existing manual tracker. Hardware mouse drags
  retain AppKit's native drag operation and Spaces support.
- **Verification:** The palette opened centered, moved by `(100, 60)` points
  after a header drag, accepted typing after the move, and exited cleanly.

### `common-attribute-leak.patch`

- **What:** Stops `uiAttribute` objects from leaking when the same attribute value
  is applied over adjacent/overlapping ranges of a `uiAttributedString`.
- **Why:** In `uiprivAttrListInsertAttribute` (`common/attrlist.c`), when the
  attribute being inserted is value-equal to an adjacent existing span, libui
  grows the existing span (`attrGrow`) and returns **without** retaining or
  storing the passed-in attribute — and without freeing it. That attribute stays
  user-owned (`refcount 0`) and is orphaned, so libui's leak checker reports it at
  `uiUninit` (`darwin/alloc.m:47` "Some data was leaked … uiAttribute"). Applying
  identical size/colour/weight to consecutive text spans is common (e.g. the
  `markdown` example's `inline()` helper), so this leaked one attribute per span.
- **Fix:** Call `uiFreeAttribute(val)` in that equal-value branch before returning.
  `val` is never retained on this path, so freeing it is safe (no double-free); the
  normal insert path is unchanged. Complements the binding-side
  `AttributedString::__destruct` fix (commit `e0d13ff`), which already frees applied
  attributes via `uiFreeAttributedString` — this covers the *orphaned* ones, which
  the PHP layer cannot reach.
- **Upstream issue:** php-gui issue #4 (leaked `uiAttribute` on close). A PHP-only
  fix isn't possible: the binding cannot tell whether libui retained the attribute
  (normal path) or orphaned it (equal path), so the free must live in libui itself.

## Maintenance

These patches are pinned to a specific `LIBUI_REF`. **On every `LIBUI_REF` bump,
re-validate each patch:** run `build-libui.sh` (or `git -C third_party/libui-ng apply
--check patches/*.patch`). If a patch no longer applies, regenerate it against the new
ref or drop it if upstream has merged the fix.
