# Third-party software

This package binds and ships a prebuilt build of **libui-ng**, and generates its
PHP layer from that library's public header.

## libui-ng

- **Project:** https://github.com/libui-ng/libui-ng
- **License:** MIT
- **Used as:** the native GUI toolkit. We build it from source
  (`build-libui.sh`) and vendor the resulting shared library per platform under
  `lib/<platform>/` (`libui.dylib` / `libui.so` / `libui.dll`). The generated
  PHP classes in `src/Generated/` and the FFI header `src/Native/libui.gen.h`
  are derived mechanically from libui-ng's `ui.h`.
- **Upstream system dependencies:** libui-ng links the host GUI stack — Cocoa on
  macOS and the Win32 API on Windows (both always present), and **GTK 3** on
  Linux (consumers must have the GTK 3 runtime installed).

libui-ng is itself a maintained fork of Pietro Gagliardi's original `libui`.

The full upstream license text travels with the source checkout under
`third_party/libui-ng/LICENSE.md` after `composer build-lib`.
