# Changelog

## Unreleased

### Fixed

- Track native control ownership and recursively invalidate descendants; reject stale handles, duplicate parents, ownership cycles, and invalid container indices before native calls.
- Prevent PHP cloning of native owners, which aliases pointers and can cause double frees.
- Destroy tracked controls before releasing models and drawing resources at shutdown; make repeated shutdown safe.
- Reject table-model teardown while a live table still uses it.
- Validate attributed-text ranges, UTF-8 boundaries, and input encoding; free unused attributes and reject repeated ownership transfer.
- Guard expired drawing contexts, finalized paths, and drag/resize operations outside a mouse-down callback.
- Run window cleanup hooks when closing through `App::run()`.
- Claim GTK floating references before destroying never-parented widgets.
- Reject resource construction outside an initialized session, nested main loops, teardown during an active loop, and restarting a shut-down session.
- Remove GTK timer event sources before freeing their callback data at teardown.
- Rebuild native text layouts when changing their width or font, and prevent source strings from being freed while a layout still uses them.
- Guarantee lifecycle teardown after setup errors, reject empty/duplicate-window apps, and run window cleanup hooks once for both native-close and quit-driven shutdown.

## [0.8.0] - 2026-10-14

### Added

- Custom window chrome: borderless windows, resize handles, keep-above state, rounded corners, and custom titlebar controls.
- Mouse-wheel and trackpad events through `AreaDelegate::scroll()` and `AreaScrollEvent`.
- Palette command actions, clickable rows, and scrolling selection.
- Native Windows rendering regression checks in CI.
- `composer notarize` for signing and notarizing rebuilt macOS libraries.

### Fixed

- Color emoji in Windows entry and multiline-entry controls, preserving native editing, selection, scrolling, password masking, and IME composition (#2).
- macOS download policy errors by shipping a Developer ID signed and notarized universal library (#3).
- Markdown preview background on scrolling areas and native attribute leaks during shutdown (#4).
- macOS palette centering, keyboard focus, header dragging, and resizing (#5).
- Window cleanup when quitting without a native close event, and repeated destruction after close.
- Platform-specific `struct tm` allocation, preventing native date-picker calls from corrupting FFI memory on macOS and Linux.

### Changed

- Rebuilt packaged libraries for macOS, Windows x86_64, Linux x86_64, and Linux aarch64 from the pinned maintained native fork.
- Release assets now use the verified packaged binaries, including the notarized macOS library.
- Updated development tooling to PHPStan 2.3.1, PHPUnit 13.4.2, and Mago 1.56.3, with compatible transitive updates. Runtime requirements remain PHP 8.5+ and FFI.

[0.8.0]: https://github.com/HelgeSverre/libui/compare/v0.7.1...v0.8.0

Earlier releases: [GitHub release history](https://github.com/HelgeSverre/libui/releases).
