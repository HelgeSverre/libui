# libui-ng source patches

Local patches applied on top of the pinned upstream `libui-ng` checkout
(`LIBUI_REF` in [`../build-libui.sh`](../build-libui.sh)) during the native build.
They are kept here, rather than forked into `third_party/libui-ng`, so the vendored
clone stays at a clean upstream ref and each fix is reviewable in isolation pending
an upstream merge.

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

## Maintenance

These patches are pinned to a specific `LIBUI_REF`. **On every `LIBUI_REF` bump,
re-validate each patch:** run `build-libui.sh` (or `git -C third_party/libui-ng apply
--check patches/*.patch`). If a patch no longer applies, regenerate it against the new
ref or drop it if upstream has merged the fix.
