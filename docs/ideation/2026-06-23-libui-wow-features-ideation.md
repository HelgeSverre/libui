---
date: 2026-06-23
topic: libui-wow-features
focus: ambitious wow-factor features for the libui-ng fork + PHP binding
mode: repo-grounded
---

# Ideation: Make it awesome — showstopper features for the libui-ng fork

## Grounding Context

Grounding comes from this session's work, not fresh agents:
- **Demand signal:** exhaustive triage of 273 open issues across `libui-ng/libui-ng` (101) and `andlabs/libui` (172). ~120 were feature-requests — the demand corpus referenced below.
- **Fork prior art:** `petabyt/libui-dev` (OpenGL area, Qt5, drag-drop PR), `kojix2/libui-ng@dev` (already implements `uiImageView` + `uiDrawImage`).
- **Platform rendering stacks:** Windows = Direct2D/DirectWrite/DWM (Mica/Acrylic backdrops, color fonts — we already shipped COLR + OpenType); macOS = CoreGraphics/CoreText/CoreAnimation/`NSVisualEffectView`; Linux = Cairo/Pango/GTK3.
- **Product framing:** the deliverable is a *PHP* GUI library. The "awe" is delivering native capabilities **no other PHP option has** — translucent windows, GPU-smooth animation, tray + notifications, webviews.

## Decision (2026-06-23)
Feasible roadmap selected: **#7 custom-chrome borderless windows → #1 vibrancy/dark mode → #6 webview**. Starting with **#7** (easiest; builds on the `uiAreaBeginUserWindowMove/Resize` already shipped in v0.7.0). #7 handed off to `ce-brainstorm`.

## Topic Axes
- Window compositing & chrome
- GPU drawing & animation
- Containers & layout
- Text & media richness
- Desktop / OS integration

## Ranked Ideas

### 1. Translucent "vibrancy" windows + first-class dark mode & accent color
**Description:** A `uiWindowSetBackdrop(Mica | Acrylic | Vibrant | Solid)` API plus automatic dark-mode + system-accent theming. The single biggest "this looks like a 2026 native app" lever — frosted/translucent window backgrounds with the OS blurring whatever is behind them.
**Axis:** Window compositing & chrome
**Basis:** `direct:` libui-ng #29 (dark mode API), andlabs #79 (borderless/transparent windows). `external:` Win11 `DwmSetWindowAttribute(DWMWA_SYSTEMBACKDROP_TYPE)` (Mica/Acrylic) + `DWMWA_USE_IMMERSIVE_DARK_MODE`; macOS `NSVisualEffectView` as a window-content underlay + `NSAppearance`; GTK CSS/`prefers-dark`.
**Rationale:** Native translucency is the visual signature of modern OS apps and is impossible to fake from a typical C GUI lib. It instantly differentiates every screenshot.
**Downsides:** Win32 child controls don't auto-adopt dark theme — making *controls* (not just the window) dark on Windows is the hard, fiddly part (per-control owner-draw / `SetWindowTheme`). Linux translucency is compositor-dependent. Per-OS version gating.
**Confidence:** 80%
**Complexity:** High
**Status:** Unexplored

### 2. `uiScroll` — a real scrollable container
**Description:** The most-requested missing layout primitive: a single-child container that scrolls when its content exceeds the viewport.
**Axis:** Containers & layout
**Basis:** `direct:` libui-ng #200, #178, andlabs #319; an in-flight (incomplete) community attempt exists as PR #281/#280. `external:` `NSScrollView` (macOS), `GtkScrolledWindow` (unix) make it nearly free; Windows needs a custom scroll host (`WS_VSCROLL`/`WM_VSCROLL` + child clipping).
**Rationale:** Without scrolling, any non-trivial form/dashboard breaks on small screens. It's foundational, broadly demanded, and unblocks whole classes of apps.
**Downsides:** The existing PR has no Windows backend; Windows custom scrolling is the real work. Interaction with libui's min-size layout needs care.
**Confidence:** 85%
**Complexity:** Medium
**Status:** Unexplored

### 3. System tray icon + native notifications
**Description:** `uiTrayIcon` (menubar/tray presence with menu) + `uiNotify`/toast notifications. Turns a window app into a "real desktop app" that lives in the tray and talks to the OS notification center.
**Axis:** Desktop / OS integration
**Basis:** `direct:` libui-ng #28 (uiTrayIcon), #26 (uiNotify), andlabs #338, #216. `external:` Win `Shell_NotifyIcon` + toast; macOS `NSStatusItem` + `UNUserNotificationCenter`; Linux StatusNotifierItem/AppIndicator + libnotify.
**Rationale:** Background utilities, monitors, and chat-style apps need this; it's a recurring ask and a strong "real product" signal — and again, unheard-of for a PHP GUI.
**Downsides:** Linux tray is fragmented (SNI vs legacy XEmbed; desktop-dependent). macOS notifications increasingly want a signed/bundled app. Highest cross-platform-parity risk in this set.
**Confidence:** 70%
**Complexity:** Medium-High
**Status:** Unexplored

### 4. Display-synced animation timer for `uiArea` (GPU-smooth 60–120 fps)
**Description:** `uiAreaAnimate(area, callback)` driven by the platform's vsync/display-link instead of a wall-clock timer, so custom-drawn UIs animate buttery-smooth and tear-free.
**Axis:** GPU drawing & animation
**Basis:** `reasoned:` our showcase demos (flowfield, clock) already custom-draw via `uiArea`; the only thing between them and showreel-quality is a refresh-synced tick. `external:` `CVDisplayLink`/`CADisplayLink` (macOS), `gdk_frame_clock` (GTK), DWM/compositor timing or a high-res `Ffi::timer` (Windows).
**Rationale:** Smooth animation is the difference between "tech demo" and "wow." It directly elevates the assets we already lead with, and pairs with the binding's existing async `Loop`.
**Downsides:** Callback marshalling/threading per platform; need to throttle to avoid pegging the CPU; vsync APIs differ enough to need three implementations.
**Confidence:** 75%
**Complexity:** Medium
**Status:** Unexplored

### 5. Images everywhere — `uiImageView` + `uiDrawImage` + SVG
**Description:** A real image widget and a draw-an-image primitive, plus scalable SVG rendering. Highest value-to-effort ratio because the first half already exists in a fork.
**Axis:** Text & media richness
**Basis:** `direct:` libui-ng #31 (uiImageView), #30 (uiDrawBitmap), #340 (cross-platform image), #229. `external:` `kojix2/libui-ng@dev` already implements `uiImageView` + `uiDrawImage` (clean cherry-pick); SVG via Direct2D `ID2D1SvgDocument`, `NSImage`/Core SVG, `librsvg`/Cairo.
**Rationale:** "Display an image / icon" is table-stakes that libui-ng lacks; cherry-picking kojix2 gets the widget cheaply, and SVG makes it crisp at any DPI (synergizes with the DPI-awareness fix we just landed).
**Downsides:** SVG backends diverge (and Windows `ID2D1SvgDocument` is a partial SVG profile). Need to reconcile kojix2's premultiply assumptions with our straight-RGBA contract (we already hit this in #425).
**Confidence:** 85%
**Complexity:** Low (widget cherry-pick) → Medium (SVG)
**Status:** Unexplored

### 6. Embedded `uiWebView`
**Description:** A native web-content control. The biggest capability swing in the set: a PHP desktop app embedding live HTML/JS/maps/charts via the OS web engine.
**Axis:** Containers & layout
**Basis:** `direct:` libui-ng #5 (uiWebView). `external:` WebView2 (Windows), `WKWebView` (macOS), WebKitGTK (Linux) — all mature, all expose a navigate + JS-bridge surface.
**Rationale:** A webview unlocks an enormous space (dashboards, rich content, hybrid UIs) and is the kind of feature that makes people say "PHP can do *that*?" It's the moonshot of this list.
**Downsides:** Heaviest maintenance burden by far; WebView2 needs the Evergreen runtime on the user's machine; security surface (JS bridge); large per-platform code. Genuinely a multi-release commitment.
**Confidence:** 55%
**Complexity:** High
**Status:** Unexplored

### 7. Custom-chrome / borderless windows with drag regions, rounded corners & shadow
**Description:** First-class support for borderless windows with app-defined titlebars: declarable drag regions, native rounded corners, and drop shadow — i.e. Raycast/Spotlight-style windows.
**Axis:** Window compositing & chrome
**Basis:** `direct:` andlabs #79; our own showcase "command palette" demo. `external:`/`reasoned:` we **already shipped** `uiAreaBeginUserWindowMove`/`Resize` in v0.7.0 — this is the natural completion (corner radius via DWM/`NSWindow` masks/GTK CSS; shadow via DWM/native).
**Rationale:** Lowest marginal cost (builds directly on what we already added) and, paired with #1 vibrancy, produces the single most striking screenshot a PHP app could ship.
**Downsides:** Hit-testing/resize edges on borderless Windows is fiddly; rounded corners differ across OS versions; accessibility/keyboard-move parity needs thought.
**Confidence:** 70%
**Complexity:** Medium
**Status:** Explored — selected as first build; handed off to ce-brainstorm 2026-06-23

## Rejection Summary

| # | Idea | Reason Rejected |
|---|------|-----------------|
| 1 | Dark mode as a standalone feature | Folded into survivor #1 (vibrancy + dark + accent) — stronger together |
| 2 | Drag-and-drop (files/text) | Real demand (#217/#245) but high effort, lower wow-per-effort; petabyt's 2264-line PR is untested — track as a PR-adoption item, not a headliner |
| 3 | Editable rich-text / text editor (#174) | Killer but very heavy + high ongoing maintenance for a fork; better as a dedicated future bet than a near-term "awe" play |
| 4 | `uiTree` control (#130) | High effort (NSOutlineView/TreeView/GtkTreeView), modest visual wow |
| 5 | `uiSwitch` toggle (#27) | Below the ambition floor as a headliner; bundle into a small "modern widgets" pass |
| 6 | Direct2D/CoreImage blur & shadow effects in drawing | Striking but Cairo-weak → poor cross-platform parity; narrower demand than #1 |
| 7 | Window icon / min-max state / window events (#185/#129/#219) | Useful but incremental, not awe — do as a small batch |
| 8 | Float spinbox / per-widget min-size / tab rename (#201/#329/#330) | Minor API gaps (already in the PR-triage Tier-2 backlog), low wow |
| 9 | Variable fonts / COLRv1 glyphs | Low demand; we already shipped COLR + OpenType features |
| 10 | OpenGL area (petabyt has it) | Niche audience for a PHP GUI lib; webview (#6) is the higher-leverage "embed something rich" bet |
