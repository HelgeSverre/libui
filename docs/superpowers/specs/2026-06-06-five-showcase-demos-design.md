# Design: five showcase demos for Libui for PHP

**Date:** 2026-06-06
**Status:** approved (build-and-discover)

## Context

The library has 7 single-file examples. We want 5 more *impressive* ones to
expand the README gallery **and** to prove libui-via-PHP can build real apps.
The user chose a **balanced spread** — ~2 screenshot-stunning, ~2 real tools,
~1 hybrid. A second, explicit goal: **surface issues**. Each demo pushes libui
in a new direction, so the build doubles as a hardening pass that will produce
the next round of API papercuts / library improvements.

## The five demos

Each is a single file under `examples/` (plus a tiny helper where noted), built
on the public `Libui\` API. Build order is low → high risk so early wins
de-risk the later ones.

1. **Flow field** — `examples/flowfield.php` *(generative)*
   Hundreds of particles drift through an animated Perlin-noise vector field,
   leaving fading gradient trails. Flexes `Area` + `Draw` strokes, gradient
   `Brush`, `Ffi::timer` animation. Perlin noise: ~30 lines of PHP. **Risk:
   low.** The gallery centerpiece.

2. **Live system monitor** — `examples/monitor.php` *(viz + practical)*
   A real dashboard: CPU-load line chart (history), a memory gauge (arc), and
   per-core sparklines, updated live from `sys_getloadavg()` / `vm_stat` (with a
   synthetic fallback). Flexes `Area` + `Draw` (paths, arcs, filled areas),
   `Text` labels, `timer`. **Risk: low–med** (platform stat sources differ).

3. **Ray** — `examples/ray.php` + `examples/ray-helper.php` *(real tool)*
   A native window that receives `ray($var)` dumps over a local socket from any
   PHP process and lists them live — type-coloured, timestamped, with caller
   file:line. Flexes non-blocking `stream_socket_server` polled by `timer`, a
   scrolling custom-drawn `Area` (or `Table`). The `ray()` sender is a ~15-line
   helper. **Risk: low–med** (socket plumbing + a tiny wire format).

4. **Command palette** — `examples/palette.php` *(real tool, riskiest)*
   A borderless, centered launcher: fuzzy-filter a list of actions, ↑/↓/enter to
   run, esc to dismiss. libui's `Entry` doesn't surface arrow keys and selection
   can't move across widgets, so the honest design is **one full-window `Area`**
   that custom-draws the input + result rows and handles every key in its
   `KeyEvent`. **Risk: highest.** Descope target: fuzzy filter + up/down/enter/
   esc + run-an-action. If it bloats, swap for a CSV/JSON data viewer.

5. **Markdown editor + live preview** — `examples/markdown.php` *(hybrid)*
   Split window: `MultilineEntry` markdown source on the left, a live
   rich-rendered pane on the right (headings sized/bold, **bold**/*italic*/
   `code`, bullet lists, rules), re-rendered on every keystroke. Flexes
   `MultilineEntry` onChanged → an `Area` that lays out parsed markdown via the
   `Text` layer (`AttributedString` / `FontDescriptor` / `TextLayout`, measuring
   block heights to stack them). Self-contained markdown subset (no commonmark
   dep). **Risk: medium.**

## APIs to verify first (de-risk before building #4/#5)

- **Borderless window** — is `uiWindowSetBorderless` exposed on `Window`? (palette)
- **Window position / centering** — `uiWindowSetPosition` / `uiWindowContentSize`? (palette, monitor)
- **Text measurement** — `uiDrawTextLayoutExtents` (or layout width/height) for
  stacking markdown blocks. (markdown)
- **Area key/mouse events** already exist (`AreaDelegate::key/mouse`).

## Success criteria

- Each demo runs and renders; each gets a clean window-only, centered-on-black
  screenshot in `docs/`, added to the README gallery.
- Every libui/API limitation hit is logged as a follow-up (the hardening output).

## Out of scope

- New native widgets or generator changes *unless* a demo proves one is needed
  (then it's logged, and done only if small).
- Windows-specific behaviour (CI Windows job stays experimental).
