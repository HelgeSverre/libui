#!/usr/bin/env bash
#
# Build libui-ng as a shared library and vendor it into lib/<platform>/ for the
# current OS + architecture.
#
# libui links the platform GUI stack (Cocoa on macOS, GTK 3 on Linux, Win32 on
# Windows) and builds with meson, so — unlike a self-contained C library — it
# cannot be cross-compiled with zig/clang from one host. Each platform's
# artifact is built ON that platform (locally or on a matching CI runner) and
# committed under lib/, so consumers need no compiler:
#
#   macOS   (Darwin)  -> lib/darwin/libui.dylib        (universal if built arm64+x86_64)
#   Linux   x86_64    -> lib/linux-x86_64/libui.so     (needs GTK 3 at runtime)
#   Linux   aarch64   -> lib/linux-aarch64/libui.so
#   Windows x86_64    -> lib/windows-x86_64/libui.dll
#
# Requirements (macOS):  brew install meson ninja
# Requirements (Linux):  meson + ninja + GTK 3 dev headers (apt install libgtk-3-dev)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
SRC="$ROOT/third_party/libui-ng"
BUILD="$SRC/build"

if [ ! -d "$SRC/.git" ]; then
  echo "==> Cloning libui-ng…"
  git clone --depth 1 https://github.com/libui-ng/libui-ng.git "$SRC"
fi

echo "==> Configuring (shared library, no tests/examples)…"
# Pass both build dir AND source dir explicitly — on a fresh checkout there is no
# preconfigured build/ to infer the source from, and the script may run from the
# repo root (e.g. in CI), not from inside $SRC.
MESON_ARGS=(--buildtype=release --default-library=shared -Dtests=false -Dexamples=false)

case "$(uname -s)" in
  MINGW* | MSYS* | CYGWIN*)
    # libui-ng only emits a shared DLL with MSVC — MinGW is static-only and meson
    # errors out. --vsenv makes meson activate and use the Visual Studio (cl.exe)
    # toolchain (paired with a Developer environment, e.g. ilammy/msvc-dev-cmd in CI).
    MESON_ARGS+=(--vsenv)
    ;;
esac

meson setup "$BUILD" "$SRC" "${MESON_ARGS[@]}" --reconfigure 2>/dev/null \
  || meson setup "$BUILD" "$SRC" "${MESON_ARGS[@]}"

echo "==> Building…"
case "$(uname -s)" in
  # meson compile re-activates the VS environment captured by --vsenv; a bare
  # `ninja` call would not see cl.exe/link.exe.
  MINGW* | MSYS* | CYGWIN*) meson compile -C "$BUILD" ;;
  *) ninja -C "$BUILD" ;;
esac

OUT="$BUILD/meson-out"

# Normalise the architecture into the lib/ directory naming.
arch="$(uname -m)"
case "$arch" in
  arm64 | aarch64) arch="aarch64" ;;
  x86_64 | amd64)  arch="x86_64" ;;
esac

case "$(uname -s)" in
  Darwin)
    DEST="$ROOT/lib/darwin/libui.dylib"
    ARTIFACT="$(find "$OUT" -maxdepth 1 -name 'libui.dylib' | head -n1)"
    ;;
  Linux | *BSD | DragonFly | GNU*)
    DEST="$ROOT/lib/linux-$arch/libui.so"
    ARTIFACT="$(find "$OUT" -maxdepth 1 -name 'libui.so' | head -n1)"
    [ -z "$ARTIFACT" ] && ARTIFACT="$(find "$OUT" -maxdepth 1 -name 'libui.so.*' | sort | head -n1)"
    ;;
  MINGW* | MSYS* | CYGWIN*)
    DEST="$ROOT/lib/windows-$arch/libui.dll"
    ARTIFACT="$(find "$OUT" -maxdepth 1 -name 'libui.dll' | head -n1)"
    ;;
  *)
    echo "!! Unrecognised platform '$(uname -s)'; defaulting to the linux layout" >&2
    DEST="$ROOT/lib/linux-$arch/libui.so"
    ARTIFACT="$(find "$OUT" -maxdepth 1 \( -name 'libui.so' -o -name 'libui.so.*' \) | sort | head -n1)"
    ;;
esac

if [ -z "${ARTIFACT:-}" ] || [ ! -f "$ARTIFACT" ]; then
  echo "!! Could not find a built shared library in $OUT" >&2
  echo "   Contents:" >&2
  ls -1 "$OUT" >&2 || true
  exit 1
fi

mkdir -p "$(dirname "$DEST")"
cp "$ARTIFACT" "$DEST"
echo "==> Done: ${DEST#"$ROOT"/}"
