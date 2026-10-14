# Repository agent guidance

These instructions apply to the entire repository. This project is a PHP 8.5+
binding to `libui-ng` through FFI and ships prebuilt native libraries for every
supported platform.

## Native library source and artifacts

The bundled libraries under `lib/<platform>/` come from the maintained
[`HelgeSverre/libui-ng`](https://github.com/HelgeSverre/libui-ng) fork. The exact
source commit is pinned as `LIBUI_REF` in `build-libui.sh`; keep the explicit
header reference in `.github/workflows/ci.yml` in sync with it.

`third_party/libui-ng/` is an ignored source checkout with its own Git history.
For native C, Objective-C, GTK, or Win32 changes:

1. Make and test the change in `third_party/libui-ng/`.
2. Commit it to the maintained fork and open or update its pull request.
3. Update `LIBUI_REF` and the CI header reference to the reviewed native commit.
4. Rebuild only the artifacts for platforms that the native change affects.
5. Commit rebuilt files under `lib/`; they are intentional release artifacts.

The files in `patches/` mirror maintained-fork fixes for history and review.
`build-libui.sh` checks each patch and either applies it or recognizes that the
pinned fork already contains it. Keep a changed patch consistent with both the
fork commit and `patches/README.md`.

`composer build-lib` builds for the current host platform. A changed macOS
library must be Developer ID signed and notarized with `composer notarize`
before release. Use the native build workflows for other platforms rather than
replacing their artifacts with a cross-compiled binary.

## Generated code

Do not hand-edit `src/Generated/**` or `src/Native/libui.gen.h`. Change
`tools/generate.php`, its annotations, or the pinned native header, then run:

```sh
composer regen
composer docs:api
```

Commit all resulting generated changes and verify that running the generators a
second time produces no diff.

## Runtime invariants

- PHP 8.5 or newer and `ext-ffi` are required.
- Initialize through the managed lifecycle before constructing native resources.
- Preserve the wrapper ownership tree. Do not destroy or reparent managed native
  pointers through raw FFI calls.
- Native callback trampolines retained through `Control::keep()` currently live
  until lifecycle shutdown. Do not release them earlier without proving that C
  can no longer call them.
- Text offsets passed to libui are UTF-8 byte offsets and must fall on character
  boundaries.
- Drawing contexts are valid only during their callback, and native resources
  must outlive every control or layout that references them.
- A scrolling `uiArea` can report a zero area size; paint its draw-event clip
  rectangle when filling the visible background.

## Verification

Install dependencies with `composer install`. For ordinary PHP changes, run the
relevant focused tests followed by:

```sh
composer format:check
composer lint
composer stan
composer gate
composer test
```

Linux GUI tests require an X server; CI runs them with `xvfb-run`. CI also tests
macOS arm64, Windows x86_64, Linux x86_64, Linux aarch64, generated-code drift,
and the native ASan/UBSan ownership fixture. Visual rendering changes still need
manual inspection on each affected backend.

Update `CHANGELOG.md` for user-visible changes. Tags matching `v*` publish the
GitHub release from that changelog section and attach the committed native
libraries plus checksums; Packagist updates from the tag webhook.
