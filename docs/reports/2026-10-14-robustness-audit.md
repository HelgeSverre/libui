# Robustness audit — 2026-10-14

The audit compared managed-wrapper behavior with v0.8.0, reproduced failures in
isolated PHP processes, and added regression tests before checking all supported
platforms. The focus was native ownership, shutdown ordering, callback scope,
UTF-8 editing, and invalid API inputs. This is a bounded audit, not proof that
all native backends or every input are safe.

## Findings and fixes

| Severity | Finding | Baseline evidence | Result |
| --- | --- | --- | --- |
| High | A parent frees children without invalidating their PHP wrappers | Parent then child destruction exited with SIGSEGV; child still reported live | Track ownership and recursively invalidate descendants; guard generated and hand-written handle access |
| High | Cloning duplicates an owning native pointer | Destroying original and cloned button exited with SIGSEGV | Native controls, models, images, text, attributes, features, layouts, and paths reject cloning |
| High | Duplicate parents and attached-child destruction enter invalid native states | Duplicate adoption exited with SIGTRAP | Reject duplicate parents, top-level children, cycles, and attached destruction; detach before reparenting |
| High | Menus inherit unsafe uiControl operations | `Menu::destroy()` exited with SIGSEGV | Reject control operations on menus; invalidate menu handles at shutdown |
| High | Invalid collection indices enter native array APIs | Empty-box deletion exited with SIGABRT | Validate box/form/tab deletion, tab insertion/margins, and combobox insertion/deletion |
| High | Invalid text byte ranges read/write outside native allocations | Insertion/deletion past the string end exited with SIGSEGV | Validate bounds, ordering, UTF-8 boundaries, input encoding, and embedded NUL bytes before mutation |
| High | A model can be freed while a table still uses it | Native model teardown exited with SIGTRAP | Track live tables per model; reject premature free and operations on freed models |
| High | Shutdown leaves controls or unused attributes alive | Native leak checker exited with SIGTRAP | Destroy tracked control trees first; sweep registered resources; free unused attributes; make shutdown idempotent |
| High | GTK teardown frees timer data without removing its event source | Native source review found a scheduled callback dereferencing freed timer data | Cancel the GLib source before freeing data; add a cross-session native sanitizer regression |
| Medium | Never-parented GTK widgets retain floating references until destruction | 19 isolated Linux test failures reported floating-object finalization | Sink only floating references before native destruction; rebuild both Linux architectures |
| Medium | `App::run()` skips per-window `onClose()` cleanup | Closing callback installed by App did not dispatch the configured window hook | Dispatch cleanup before native close; add an actual Windows WM_CLOSE regression |
| Medium | Drawing operations can use expired contexts or invalid path state | Wrappers exposed native tokens without checking their scope/state | Expire contexts when draw returns; require finalized paths for drawing and reject later mutation |
| Medium | Freed resource pointers remain accessible; attributes can be transferred again | Code inspection found handle getters and operations returning freed/consumed pointers | Guard resource methods and enforce one ownership transfer per attribute |
| Medium | Constructors and loop helpers can enter native code outside an initialized session | Native API precondition was documented but unenforced | Reject these calls in PHP before native allocation or dispatch |
| Medium | `TextLayout::setWidth()` and `setFont()` update PHP state without changing native layout | Source review and native extent measurements showed unchanged layout behavior | Rebuild the native layout and guard its source string from premature free |
| Medium | Setup errors and quit-driven shutdown can skip cleanup hooks or native teardown | Lifecycle review found setup outside `finally` and cleanup only in close callbacks | Cover setup with teardown, invoke cleanup exactly once on every accepted shutdown path, and reject invalid app composition |

The GTK fix follows [GObject reference-sinking semantics](https://docs.gtk.org/gobject/method.Object.ref_sink.html).
Its native commit is `ab7d0ff7f7eea5417705c39edc94fe1c51214b93`
([libui-ng PR #1](https://github.com/HelgeSverre/libui-ng/pull/1)).
The additions are excluded on macOS and Windows; stripping those conditional
blocks produces the previous `common/control.c` byte for byte. Their signed or
verified packaged libraries therefore remain unchanged.

## Verification

The suite grew from 725 to 783 cases. New ownership/resource tests run in separate
processes so native aborts and diagnostics cannot hide behind a shared test
process. Tests exercise valid recovery after rejected operations, not just the
exception messages. A fixed random seed drives 200 UTF-8 edits and checks native
contents and byte lengths after every edit. Repeated shutdown verifies stale-object state; attempting to restart a closed
session is rejected. Nested main loops and teardown inside an active loop are
also rejected. Native backends retain run-loop state, so managed UI sessions
use one initialization lifetime per PHP process.

| Check | Observed result |
| --- | --- |
| macOS arm64, PHP 8.5 | 783 tests; 1746 assertions; 2 platform-specific skips; exit 0 |
| Linux aarch64, PHP 8.5 | 783 tests; 1741 assertions; 5 platform-specific skips; exit 0 |
| Linux x86_64, PHP 8.5 | 783 tests; 1741 assertions; 5 platform-specific skips; exit 0 |
| Native Linux x86_64 ownership/text executable | 200 cycles and timer teardown; ASan + UBSan; GTK warnings fatal; exit 0 |
| PHPStan | No errors, without new baseline suppressions |
| Mago formatting and lint | Pass |
| Generated code | Regenerated from the pinned header; syntax/drift gate included in CI |
| Windows x86_64, PHP 8.5 | 783 tests; 1739 assertions; 6 platform-specific skips; real WM_CLOSE cleanup and 14 native emoji rendering checks; exit 0 |

Sanitizers cover a separately compiled native executable and are now a CI job.
The existing PHP binary rejected sanitizer preloading at startup, so the PHP
suite runs normally; this audit does not claim an instrumented PHP interpreter.
LeakSanitizer is disabled for GTK's process-global caches. Native allocation
leak checks and explicit cleanup regressions provide separate teardown evidence.

## Remaining gaps

- **Callback retention:** creating/destroying 1000 callback-bearing buttons left
  1000 PHP trampolines retained until shutdown; shutdown cleared them all. This
  is the current safety policy against C calling collected closures. Long-lived
  interfaces that repeatedly rebuild controls can grow PHP memory. A follow-up
  should retire callbacks per owner at a proven safe event-loop boundary.
- **Raw FFI escape hatch:** direct native lifetime/parent mutations bypass wrapper
  tracking. Do not mix raw destruction or ownership changes with managed trees.
  Public wrappers cannot make arbitrary native pointers safe.
- **Display placement:** automatic centering still supports only the primary
  macOS display and caches its size. Windows/Linux require explicit dimensions;
  display changes and secondary-display placement need further work.
- **Windows rendering coverage:** broad IME, bidirectional text, mixed DPI, and
  unusual font configurations still need interactive coverage beyond the
  rendering regression cases. No new rendering defect was reproduced here.
- **Native coverage:** the sanitizer executable is targeted ownership/text
  coverage, not exhaustive native fuzzing. GTK global leak accounting, thread
  misuse, and every backend's callback reentrancy behavior remain outside this pass.

Review verdict: the reproduced failures have fixes and regressions. The complete
cross-platform CI passed, including the native sanitizer job.
