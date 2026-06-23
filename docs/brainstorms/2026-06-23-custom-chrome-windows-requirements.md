# Custom-chrome windows — requirements

*Brainstorm 2026-06-23. Seed: ideation idea #7 (`docs/ideation/2026-06-23-libui-wow-features-ideation.md`). Target: the libui-ng fork (`HelgeSverre/libui-ng@maintained`) + the PHP binding (`helgesverre/libui`). Tier: Deep — feature.*

## Summary

Let apps build Raycast/Spotlight-style **custom-chrome windows**: borderless, with an app-designated control acting as the titlebar (drag handle), a native corner-style preset, and a drop shadow — while keeping resize, min/max, and OS snap. It completes the surface opened by `uiAreaBeginUserWindowMove`/`uiAreaBeginUserWindowResize` (shipped v0.7.0) and is the flagship the planned vibrancy work (#1) will sit inside.

## Problem Frame

libui-ng windows are all-or-nothing: a standard OS titlebar, or a bare borderless window with no way to move it, no rounded corners, and no shadow. Building a modern frameless app (the showcase `examples/palette.php` command palette is the canonical example) currently means hand-wiring low-level Area mouse events and accepting square, shadowless windows. Demand is long-standing (libui-ng/andlabs #79). No fork addresses it. The native capability exists on every platform — it just isn't exposed.

## Requirements

- **R1 — Custom-chrome capability.** A window can be configured as custom-chrome (borderless + the chrome controls below). Reuses the existing borderless window rather than introducing a new window type.
- **R2 — Control-as-drag-handle.** The app designates a control (typically a top `Box`) as the titlebar; dragging anywhere on it moves the window. Nested interactive controls (buttons, entries, sliders) remain interactive and do **not** initiate a drag.
- **R3 — Multiple drag handles.** More than one control may be marked draggable (full-toolkit scope).
- **R4 — Corner style.** A window-level preset enum `{ None, Rounded, RoundedSmall }`, behaving consistently across platforms.
- **R5 — Drop shadow.** On/off toggle; **defaults ON** for custom-chrome windows (which otherwise lose the OS shadow).
- **R6 — Resize + min/max.** Custom-chrome windows are resizable from edges/corners (honoring the existing `uiWindowResizeable`), respecting min/max sizes.
- **R7 — OS snap.** Native snap (Windows Aero Snap; macOS/GTK equivalents) works where the platform provides it, as a consequence of correct hit-testing — no custom snap engine.
- **R8 — Low-level escape hatch.** `uiAreaBeginUserWindowMove`/`Resize` remain available for fully custom, drawn titlebars (e.g. a titlebar painted in a `uiArea`).
- **R9 — Graceful degradation.** Where a platform/compositor can't deliver corners, shadow, or programmatic move, the window still functions correctly; the gap is documented in a support matrix. No hard failure, no thrown error.
- **R10 — PHP facade.** Typed `Window` methods with fluent ergonomics and enums (e.g. mark-draggable, corner-style, shadow), composing with existing `setBorderless`/`setResizable`.
- **R11 — C API.** Corresponding `uiWindow*` functions + a corner-style enum in `ui.h`, implemented on all three backends and flowing through the generator into `Generated\Window` + the hand-written `Window` facade.

## Key Decisions

- **D1 — Drag model = control-as-handle** (not pixel rectangles, not drag-everywhere). Layout-robust (survives resize/relayout) and matches libui's control-tree model. Pixel rects were rejected as fragile; drag-everywhere as too imprecise.
- **D2 — Corners = preset enum everywhere** (not arbitrary pixel radius). Windows' DWM `DWMWA_WINDOW_CORNER_PREFERENCE` only offers presets; choosing presets buys full cross-platform consistency at the cost of pixel-exact corners on macOS/GTK (which could do arbitrary radius). Revisit arbitrary-radius only if demand appears.
- **D3 — Extend borderless, don't add a window type.** Custom-chrome = existing `uiWindowSetBorderless` + the new chrome controls. Smaller API surface, no new construction path.
- **D4 — Degradation = silent best-effort + documented matrix.** No capability-query API in v1; keeps the surface simple. A `Window` capabilities query can be added later if apps need to adapt layout.
- **D5 — Shadow defaults ON.** Restoring the OS shadow a borderless window loses is the expected "chrome"; off is opt-out.
- **D6 — Keep the low-level Area move/resize API.** Already shipped; it's the escape hatch for fully custom titlebars and shouldn't be hidden behind the high-level model.

## Per-platform mechanics

(Included because the brainstorm is inherently a cross-platform technical surface; exact symbols/structs are for planning to finalize.)

- **Windows (Direct2D/DWM):** corners via `DWMWA_WINDOW_CORNER_PREFERENCE` (Win11; older Windows → square, documented); shadow via DWM frame extension on the borderless window; drag/resize via `WM_NCHITTEST` returning `HTCAPTION`/`HTLEFT`/… over handle and edge regions — Aero Snap then "just works."
- **macOS (Cocoa):** borderless `NSWindow` styleMask; corners via `contentView.layer.cornerRadius` + `masksToBounds`; `window.hasShadow`; drag via `performWindowDragWithEvent:`/`mouseDownCanMoveWindow` on the handle; resize via resizable styleMask.
- **Linux (GTK3):** `gtk_window_set_decorated(FALSE)` for CSD; corners via window CSS `border-radius`; shadow via CSS `box-shadow` (compositor-dependent); drag via `gtk_window_begin_move_drag`; resize via `gtk_window_begin_resize_drag`. **Wayland caveats:** no programmatic positioning, and corners/shadow are compositor-governed — primary degradation surface (R9).

## Scope Boundaries

**In scope:** R1–R11 above (the full custom-titlebar toolkit), landed in the fork and surfaced through the PHP binding.

**Deferred (next / later):**
- Window **vibrancy/translucency** (#1) — the immediate next roadmap item; this work lands the chrome it will sit inside, but no backdrop yet.
- **Webview** (#6) — separate roadmap item.
- Arbitrary (pixel) corner radius + a capabilities-query API — revisit if demanded (D2/D4).
- Custom snap/tiling logic beyond what the OS provides (R7).

**Outside this product's identity:** not a general theming/CSS engine; not non-rectangular / per-pixel custom-shaped windows in v1.

## Success Criteria

- `examples/palette.php` (the command-palette showcase) is rebuilt on the real public API — borderless, draggable from a handle, rounded, shadowed — with no low-level Area mouse wiring.
- All three backends compile and the fork's all-platform CI build is green.
- Manual per-platform visual check: window drags from the handle; nested controls still click; corners + shadow render on Win11/macOS/supported GTK, and degrade gracefully (square/no-shadow, still movable where possible) on unsupported Linux/Wayland compositors.

## Dependencies / Assumptions

- Builds on v0.7.0 `uiAreaBeginUserWindowMove`/`Resize` and `uiWindowSetBorderless`.
- Lands first in the maintained fork, then flows to the binding via a rebuilt prebuilt library + `composer regen` (new `uiWindow*` functions + corner enum picked up by `tools/generate.php` → `Generated\Window` + `Window` facade).
- Windows corner presets require Win11; older Windows degrades to square corners (documented, R9).
- Assumes the fork remains our native base (the adoption decision flagged earlier).

## Open Questions (for planning)

- Exact PHP facade naming (`setTitleBar($control)` vs `markDraggable($control)`; `setCornerStyle(CornerStyle)`, `setShadow(bool)`).
- Whether multiple drag handles (R3) need explicit add/remove, or a single setter suffices for v1.
- Edge/corner resize grip width and whether it's configurable.
