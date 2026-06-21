# php-gui (helgesverre/libui) — Prioritized Improvement Report

## Executive summary

The binding is mature: a generator-plus-hand-sugar architecture, typed widgets, RAII wrappers for most native resources, and a broad test suite. This audit found that the remaining issues cluster into a few honest categories:

1. **Two real runtime bugs hidden behind the PHPStan baseline.** `Table::appendImageTextColumn` and `appendCheckboxTextColumn` pass too few FFI arguments — PHP FFI requires every declared C argument, so these throw the moment they are called. The arity mismatch is *baselined* rather than fixed (`phpstan-baseline.neon:369-379`). No test or example calls them, so the bug is latent.
2. **A small FFI memory-safety cluster in Text/Draw.** `DrawContext::drawString()` leaks a `uiAttributedString` on every Area repaint; `AttributedString` has neither a destructor nor a double-free guard; `TextLayout` does not retain the `AttributedString` it points at. These three are a single chain — fixing them in the wrong order introduces a use-after-free.
3. **One unguarded callback.** `Table::onSelectionChanged` is the lone Table handler without a try/catch; a throwing handler hard-fatals the process.
4. **No generator drift detection.** `GeneratorRegressionTest` only syntax-checks output and inspects one hardcoded file (`Button.php`); CI never regens-and-diffs. A generator change can silently ship.
5. **Mechanical doc + convention debt.** Windows is documented as "experimental" though the prebuilt DLL ships; a broken in-page anchor; the shipped `RichText`/`TextStyle` facade is undocumented everywhere; and `Window::focused()`/`Tab::margined()` leak `int` instead of `bool`.

Items below are deduplicated across dimensions and ranked by impact-per-effort.

---

## API ergonomics

### A1. Grid::append() exposes a 9-positional-arg call with raw int booleans between two Align enums — HIGH / M
`src/Generated/Grid.php:42` `append(Control, int $left, int $top, int $xspan, int $yspan, int $hexpand, Align $halign, int $vexpand, Align $valign)`; `src/Grid.php:11` is an empty subclass (no facade). Callers must pass 1/0 for expand and remember four positional ints around two enums.
**Fix:** add hand-sugar to `src/Grid.php` (keep generated `append()` intact): `appendAt(Control $c, int $left, int $top, int $xspan = 1, int $yspan = 1, bool $hexpand = false, Align $halign = Align::Fill, bool $vexpand = false, Align $valign = Align::Fill)` casting bools to int, plus `place(Control $c, int $col, int $row)` for the common 1×1 cell. Mirrors `Box::appendStretchy()` (`src/Box.php:37`).

### A2. Box/Form append() still take an int boolean $stretchy; Form has no facade — MEDIUM / S
`src/Generated/Box.php:45` and `src/Generated/Form.php:36` declare `int $stretchy`. `src/Box.php:31` keeps `int $stretchy = 0`; `src/Form.php:11` is empty, so `examples/gallery.php:61-66` passes raw `0` six times. Inconsistent with `Checkbox::setChecked(bool)`, `Window::setMargined(bool)`.
**Fix:** change `src/Box.php` `append` to `bool $stretchy = false` (cast to int when delegating); add `src/Form.php` `append(string $label, Control $c, bool $stretchy = false)` + `appendStretchy()`; drop the literal `0`s in the example.

### A3. FontButton has no typed value get/set; font() takes a raw out-param and FontDescriptor is write-only — HIGH / M
(Overlaps the FFI leak F5.) `src/Generated/FontButton.php:34` `font(\FFI\CData $desc)`; `src/FontButton.php:11` empty. `src/Text/FontDescriptor.php` has setters + `toCData()` (:53-91) but **no getters and no fromCData()**, so even after filling a descriptor via `font()` you cannot read the values.
**Fix:** add readonly accessors (`family()`, `size()`, `weight()`, `italic()`, `stretch()`) and static `fromCData()` to `FontDescriptor`; add `src/FontButton.php` `getFont(): FontDescriptor` that allocates a `uiFontDescriptor`, calls `parent::font()`, wraps via `fromCData`, then calls `uiFreeFontButtonFont` (fixing the leak in F5). Mirrors `ColorButton::getColor()`.

### A4. DateTimePicker exposes only raw struct tm CData with no DateTimeImmutable bridge — HIGH / M
`src/Generated/DateTimePicker.php:54,68` require allocating/reading a C `struct tm` (tm_year−1900, tm_mon 0-based); `src/DateTimePicker.php:11` empty. This is the one widget whose entire purpose is its value.
**Fix:** add `getValue(): \DateTimeImmutable` and `setValue(\DateTimeInterface)` to `src/DateTimePicker.php` (set `tm_isdst = -1` per the warning at `:64`). Keep raw CData methods.

### A5. Window position()/contentSize() force callers to allocate FFI out-pointers — MEDIUM / S
`src/Generated/Window.php:67,119` take two out-pointers and return `$this`. The private `windowSize()` at `src/Window.php:100-109` already proves the int[2] dance is awkward and is unusable by callers (private).
**Fix:** add public `getContentSize(): array` (`@return array{int,int}`, promote the existing private logic) and `getPosition(): array` to `src/Window.php`. Keep raw methods.

### A6. MenuItem::onClicked() leaks raw uiWindow* CData into the user callback — LOW / S
`src/Generated/MenuItem.php:49` calls `$cb($this, $window)` passing the raw `uiWindow*`; every other handler passes only `$this`. Caused by the `menuitem` deviation (`tools/annotations.php:94`).
**Fix:** wrap into a `Libui\Window` via `Window::wrap($window)` (or drop the second arg), update the docblock at `MenuItem.php:42`, add a test.

### A7. Single-callback handlers with no add/remove; trampolines accumulate forever — MEDIUM / M
Every `onXxx` carries "Only one callback can be registered at a time." and re-calling silently replaces the prior pointer; retained closures pile into `Control::$callbacks` (`src/Control.php:26`, appended by `keep()` :65-69) for the process lifetime.
**Fix (low-risk first):** document replace-semantics in the generated docblock and add a test asserting a second `onClicked()` supersedes the first. Fuller fix: a per-(widget,event) multiplexing trampoline in `src/Control.php` with `on()` returning an unsubscribe closure.

### A8. No uniform value contract across widgets — LOW / M
`text()`/`value()`/`checked()`/`selected()`/`getColor()` all differ, forcing generic form code to special-case widgets.
**Fix:** optional `HasValue` interface (`getValue()/setValue()`) implemented on the hand-sugar classes; purely additive.

### A9. Generated out-pointer getters sit publicly next to their facades — LOW / S
`ColorButton::color()` (`src/Generated/ColorButton.php:37`) and similar remain public next to `getColor()`. **Fix:** emit `@internal` on scalarOut-CData getters and document the supported facade.

### A10. Spinbox/Slider are int-only (binding limitation, not a gap) — LOW / S (doc)
`third_party/libui-ng/ui.h` has no `uiNewSpinboxDouble` (grep empty), so decimal spin is unreachable in this build. **Fix:** document in `docs/API.md`/GUIDE; add a double factory only if the submodule is later bumped.

---

## PHPDoc & generator

### G1. Multi-line @note/@param/@returns truncated to their first line — HIGH / M
`docFromBlock()` (`tools/generate.php:208-256`) processes each line independently; continuation lines hit the `summary === ''` branch (:253) and are dropped. `ui.h:1400-1401` → `Combobox.php:58` cut mid-sentence; `ui.h:1453-1454` → `Combobox.php:120` ends in a trailing comma; same at `Tab.php:57`.
**Fix:** track a "current tag" reference while iterating; append non-`@` continuation lines (space-joined) to it instead of falling through to summary; clear on new `@tag`/blank; run `sanitizeDocText` on the joined value.

### G2. DateTimePicker factory summaries are rotated/wrong (upstream typo passed through) — MEDIUM / S
`ui.h:1670/1678/1686` document the three constructors with rotated summaries; harvested verbatim into `DateTimePicker.php:18/28/38` (all three wrong for the method they document).
**Fix:** add a `doc_overrides` map to `tools/annotations.php` keyed by ui function name; merge override summary over the harvested one in `docBlock()` (`tools/generate.php:351`). Reusable for other upstream typos.

### G3. Boolean predicates/params left as int while prose says TRUE/FALSE — MEDIUM / M
(Overlaps T2/A2.) `Box.php:41`, `Form.php:32`, `Grid.php:35/37/56/58`, `Tab.php:147`, returns `Window.php:236` (`focused(): int`), `Tab.php:134/138` (`margined(): int`). Param coercion at `generate.php:879` only fires for Set-suffix last-arg, so multi-arg cases can't be coerced via bool_funcs.
**Fix:** (1) add `uiWindowFocused` + `uiTabMargined` to `bool_funcs` (return path :911 handles getters). (2) Generalize param coercion at :879: coerce `kind=='int'` params to bool whenever the harvested `@param` text matches `/^`?TRUE`?\b/`. Add a regression assertion that `Grid::append`'s hexpand param is typed `bool`.

### G4. sanitizeDocText only strips the TODO default placeholder — LOW / S
`tools/generate.php:321` requires `TODO` inside the brackets; real `[Default ...]` fragments leak: `Slider.php:58`, `Window.php:144/287/312`, `Entry.php:96`, `Checkbox.php:79`, `MenuItem.php:63`, `Combobox.php:94`, `ProgressBar.php:30`.
**Fix:** broaden to `preg_replace('/\s*`?\[Default:?[^\]]*\]`?/i', '', $text)`.

### G5. Out-pointer params documented as opaque "Output pointer written by libui" — MEDIUM / S
`methodParamDoc()` (`tools/generate.php:727-728`) gives no hint to allocate an `int*` and read `->cdata`. `Window.php:61-62,113-114`. The intended usage is shown only in `GeneratorRegressionTest.php:46-60`.
**Fix:** replace the suffix with an actionable one referencing `\Libui\Ffi::get()->new('int')` and `->cdata`.

### G6. @note/@warning interleaved between @param and @return — LOW / S
`emitMethod()` array_merges note tags onto docTags (`tools/generate.php:898`) before appending the return (:916), so notes appear before `@return` (e.g. `Ui.php:18-19`).
**Fix:** keep paramTags/noteTags/returnTag separate and concatenate in a consistent order.

### G7. Every docblock ends in a bare `@see uiFunctionName` resolving to no PHP symbol — LOW / S
`docBlock()` (`tools/generate.php:356,373`) emits `@see {C name}` (e.g. `Box.php:43 @see uiBoxAppend`), non-navigable and duplicating the class-level `@generated` banner.
**Fix:** relabel as prose (`libui: {name}`) or `@link` to upstream docs; reserve `@see` for real PHP symbols.

### G8. "control neither destroyed nor freed" grammar typo copied from ui.h:639 — LOW / S
`Box.php:67` reads the broken form while siblings `Form.php:56`/`Tab.php:108` (from corrected upstream lines) read correctly.
**Fix:** exact-string fixup in `sanitizeDocText()` or the `doc_overrides` hook.

### G9. No test asserts any docblock content — MEDIUM / S
`tests/GeneratorRegressionTest.php` has 0 prose assertions; it already reads generated source (`:81`). G1 and G2 would each have been caught by one assertion.
**Fix:** add a case iterating `src/Generated/*.php` asserting no dangling truncations/trailing commas on `@note/@param/@return` lines, no `[Default` on doc lines, and `DateTimePicker::dateOnly`'s summary contains "date".

---

## Docs (apply immediately)

### D1. GUIDE Windows note still says "experimental" — HIGH / S
`docs/GUIDE.md:677-678` ("build `libui.dll`… Currently experimental in CI") contradicts `README.md:26,158` and the shipped `lib/windows-x86_64/libui.dll` (263168 bytes) resolved at `src/Ffi.php:80`.
**Fix:** "**Windows** (x86_64): a prebuilt `libui.dll` (built with MSVC) ships in the package under `lib/windows-x86_64/` — nothing to install; works out of the box."

### D2. Broken in-page anchor — MEDIUM / S
`docs/GUIDE.md:80` links `#tables-must-outlive-their-model`, which no header matches. The working reference at `:652` uses `#model-lifetime-is-handled-for-you` (header at `:558`).
**Fix:** change `:80` target to `#model-lifetime-is-handled-for-you`.

### D3. RichText/TextStyle facade shipped but undocumented — MEDIUM / M
`src/Text/RichText.php` and `src/Text/TextStyle.php` are public and appear in `docs/API.md:791-829`, but grep over README/GUIDE/ARCHITECTURE/CONTRIBUTING returns nothing. GUIDE "Attributed text" (`:453-483`) still teaches the verbose AttributedString dance RichText removes.
**Fix:** add a RichText subsection to GUIDE after `:483` using the real `create/append/layout/measure/height` signatures; add a README Features clause naming `RichText`/`TextStyle`.

### D4. CONTRIBUTING hand-written list omits Text + Table subsystems — MEDIUM / S
`CONTRIBUTING.md:22-24` lists `Ffi/Control/Area/AreaDelegate/Draw` and stops, implying `src/Text/**` and the Table subsystem are regenerated. `docs/ARCHITECTURE.md:296-301` lists them correctly.
**Fix:** extend the list to include `src/Text/**` and `src/Table.php`/`TableModel.php`/`TableModelDelegate.php`.

### D5. GUIDE Tables never documents Color columns / per-row background — LOW / S
GUIDE Tables (`:487-565`) documents only Int/String column types. The capability is real: `src/TableModelDelegate.php:29,42` (`cellValue` returns `…|Color|Image|null`), `TableValueType::Color` (`API.md:882`), and `setRowBackground` (`API.md:535`, constructor-only).
**Fix:** add a short delegate example returning `TableValueType::Color` + a `Libui\Color`, and the `rowBackgroundModelColumn` constructor arg; note that `setRowBackground()` always throws.

---

## Type safety

### T1. appendImageTextColumn/appendCheckboxTextColumn pass too few FFI args — they throw at runtime; baseline masks the bug — HIGH / M
`phpstan-baseline.neon:369-379` baselines "invoked with 3 parameters, 7/6 required". Stubs declare full arity (`stubs/FFI.php:263,265`). `src/Table.php:187-195` passes only handle+name+imageModelColumn; `:216-224` only handle+name+modelColumn. PHP FFI requires every declared C arg, so both throw. No test/example calls them.
**Fix:** remove the two baseline entries; widen both methods to pass all required args (reuse the `keepStruct`/`uiTableTextColumnOptionalParams` pattern at `Table.php:123-147`); add a smoke test constructing each column type against a real model.

### T2. uiWindowFocused missing from bool_funcs — Window::focused() returns int — MEDIUM / S
(Dedup of three dimensions.) `src/Generated/Window.php:240-243` returns raw int while siblings (`borderless`/`margined`/`fullscreen`/`resizeable`) return `bool` via `!== 0`. `tools/annotations.php:45-72` omits it.
**Fix:** add `uiWindowFocused` to `bool_funcs` and regen. (Same edit recommended for `uiTabMargined`/`uiTabSetMargined` — see G3/A-Tab.)

### T3. FfiFunctions interface is a dead byte-identical duplicate of the stub — LOW / S
`src/Generated/FfiFunctions.php` carries 299 `@method` lines identical to `stubs/FFI.php`, is never implemented, and PHPStan registers only the stub (`phpstan.neon:19-20`). Two files from one source (`generate.php:1196,1216`).
**Fix:** delete `emitFfiFunctionsInterface()` + the file, repoint the two `@see` tags (`src/Ffi.php:12,119`) to the stub. If kept for IDE docs, add a generator assertion that the two share `ffiMethodLines()` output.

### T4. Six missingType.iterableValue baseline entries are fixable with phpdoc — MEDIUM / S
`phpstan-baseline.neon:40-43,328-331,418-421` + three `tests/TableFunctionalTest.php` anon `$data` params. Source lacks value-type phpdoc (`Control.php:26`, `Ffi.php:31`, `TableModel.php:34` — string-keyed per `:115-149`).
**Fix:** add `@var list<callable>` / `@var array<string, callable>` and type the test params; drop the six entries.

### T5. ~35% of baseline is test-assertion alreadyNarrowedType noise — LOW / M
59 `alreadyNarrowedType` entries (of 171 total), all in tests (e.g. `phpstan-baseline.neon:496-511,598-601,910-919`) — redundant `assertIsBool`/`method_exists` on statically-known values. Unrelated to FFI dynamic-access noise.
**Fix:** either set `treatPhpDocTypesAsCertain: false` in `phpstan.neon`, or replace defensive asserts with value assertions; extend the `phpstan.neon:22-26` comment to distinguish FFI noise from test noise.

### T6. Brush $gradient default [] conflicts with its 5-tuple shape — LOW / S
`src/Draw/Brush.php:37` `private readonly array $gradient = []` typed `array{float×5}` → `phpstan-baseline.neon:123-127,141-145`. Private constructor; all callers pass explicit 5-tuples or omit.
**Fix:** change to `?array $gradient = null` and the guard at `:116` to `!== null`; drop both baseline entries.

### T7. FFI stub callbacks typed as bare `callable` — LOW / M
`stubs/FFI.php:45` + ~30 On*/Timer methods declare `callable $cb` with no shape; `ffiTypeForParam()` (`generate.php:643-647`) returns literal `'callable'`. The `deviating_callbacks` map (`annotations.php:92-95`) records the two non-standard shapes.
**Fix:** emit shaped callables keyed by the deviation map (default `callable(?\FFI\CData, ?\FFI\CData): void`; `: int` for `uiWindowOnClosing`; 3-arg for menuitem). Low risk — only tightens what PHPStan reads.

### T8. Table::selectedRows() phpdoc int[] instead of list<int> — LOW / S
`src/Table.php:308` returns `int[]` but the builder appends sequentially (`:316-322`). Codebase prefers `list<>` (`ArrayTableModelDelegate.php:20-30`, `Brush.php:96`).
**Fix:** change to `@return list<int>`.

### T9. property.onlyWritten retention props better silenced inline — LOW / M
9 entries for genuine GC-retention props (`Control::$callbacks`, `Ffi::$retained`, `Brush::$cdata/$stopsArray`, `StrokeParams::$cdata/$dashArray`, `Table::$retainedStructs`, `FontDescriptor::$familyBuffer`, `TextLayout::$params`). Baselining by file+message is brittle.
**Fix:** replace with inline `@phpstan-ignore property.onlyWritten (retained so libui's C pointer stays valid)` on each declaration. (The 81 `property.notFound` FFI-struct entries should stay in the baseline.)

---

## FFI safety

### F1. Table::onSelectionChanged callback not exception-guarded — a throw hard-fatals — HIGH / S
`src/Table.php:368-375` registers the callback with no try/catch; siblings `onRowClicked` (`:384-395`) and `onRowDoubleClicked` (`:404-415`) both wrap `$cb` in `try/catch (\Throwable)`. `TableModel.php:18-20` documents that a throw escaping an FFI callback is a hard fatal.
**Fix:** wrap the inner call identically; add a regression test registering a throwing handler and asserting the process survives.

### F2. DrawContext::drawString() leaks a uiAttributedString per repaint — HIGH / S
`src/Draw/DrawContext.php:150-153` builds an `AttributedString`, draws, and returns without freeing it. `AttributedString` has no destructor (`src/Text/AttributedString.php:92-95`), so the native string from `uiNewAttributedString` (`:22`) is never released. `drawString` is the documented Area-draw convenience (`:132-138`) and fires every repaint → unbounded leak.
**Fix:** wrap in `try { … } finally { $string->free(); }` (libui copies at `uiDrawText` time, so freeing after `text()` is safe). Cleaner long-term: the F3 destructor covers all callers. Add a test asserting the string is freed.

### F3. AttributedString has no destructor and no double-free guard — MEDIUM / S
`src/Text/AttributedString.php:92-95` `free()` is unconditional with no `$freed` flag and no `__destruct`, unlike `TextLayout` (`:23,103-115`) and `Path` (`Draw/Path.php:219-232`). Causes the F2 leak and any RichText drop (`RichText.php:15,20` never frees), and double-frees if `free()` is called twice.
**Fix:** add `private bool $freed`, guard `free()`, add `__destruct`. **MUST land with F4** so a string isn't GC-freed while a live layout references it.

### F4. TextLayout doesn't retain the AttributedString it points at — latent use-after-free — MEDIUM / S
`src/Text/TextLayout.php:35-43` sets `$params->String = $string->handle()` but stores only `$this->params` and `$this->width` — never the `$string` object. Safe today only because `AttributedString` leaks instead of being freed. The moment F3's destructor lands, a layout outliving its string → use-after-free.
**Fix:** store `private AttributedString $string;` in the constructor. No API change. Land together with (or before) F3.

### F5. FontButton::font() leaks the descriptor libui allocates — MEDIUM / M
(Overlaps A3.) `src/Generated/FontButton.php:34-38` calls `uiFontButtonFont` but never `uiFreeFontButtonFont`, despite its own docblock warning (`:30-31`); the heap-allocated `Family` char* leaks every call. `src/FontButton.php:11` empty; no wrapper exposes `uiFreeFontButtonFont`.
**Fix:** the A3 `getFont()` facade (allocate → fill → copy → free). Until then, expose `uiFreeFontButtonFont` and document the requirement. Add a round-trip test.

### F6. Retained callback/closure stores never cleared on uninit() — LOW / S
`Ffi::$retained` (`src/Ffi.php:31`, appended `:241,261,279`) and `Control::$callbacks` (`src/Control.php:26,67`) accumulate for the process lifetime. `Ffi::uninit()` (`:217-223`) frees TableModels and resets the menu lock but never clears either array, though uninit explicitly supports re-init (`:215`).
**Fix:** after `uiUninit()` clear `self::$retained = []` and add `Control::resetCallbacks()` (mirroring `Window::resetMenuLock()` at `:222`); comment that this is safe only after teardown. Add a growth test over repeated init/uninit.

### F7. Image has no destructor — dropped Images leak the native uiImage — LOW / M
`src/Image.php:39-45` `free()` is manual-only, no `__destruct`, unlike `Path`/`TextLayout`. Note the TableModel fallback (`src/TableModel.php:202`) is deliberately pinned and must not be auto-freed while a live Table/Area may draw it.
**Fix (lower risk):** document caller-managed lifetime in the class docblock (must outlive any Table/Area column). If auto-free is wanted, a guarded `__destruct` that frees only when never handed to libui (a `$borrowed` flag), keeping the fallback pinned.

---

## Tests & infra

### I1. GeneratorRegressionTest doesn't detect generator drift — HIGH / M
`tests/GeneratorRegressionTest.php` only `php -l`s `src/Generated/*` (`:24-44`), does two Window round-trips (`:46-74`), and asserts `try {`/`catch (\Throwable` in **one hardcoded file** `Button.php` (`:76-86`). It never regens or diffs; `tools/generate.php` has no `--check` mode; no CI job runs `composer regen` + `git diff`. A generator change dropping the guard on the other 22 widgets passes green.
**Fix:** add to CI after `composer gate`: `composer regen && git diff --exit-code -- src/Generated src/Native/libui.gen.h || (echo 'Generated files are stale' && exit 1)`. Generalize `testGeneratedClickCallbackIsGuarded` to iterate every `src/Generated` file declaring an `on*` callback.

### I2. build-libui.sh clones libui-ng HEAD with no pinned commit — non-reproducible — HIGH / S
`build-libui.sh:25-28` `git clone --depth 1 … libui-ng.git` with no `--branch`/checkout; no `.gitmodules`; the existing-checkout guard (`:25`) skips updating stale clones. Both `ci.yml:97` and `release-build.yml:34,62,101` (release assets) link whatever HEAD was that day, while the generator parses a checked-in `ui.h` that can silently diverge.
**Fix:** add `LIBUI_REF` and clone with `--branch` (tag) or fetch+checkout (sha); re-checkout when `$SRC/.git` exists; document the ref in CONTRIBUTING so regen and build stay in lock-step.

### I3. testAppFullLifecycleInSubprocess never asserts completion/exit 0 — MEDIUM / S
`tests/AppTest.php:110-190` spawns a detached process with trailing `&` (`:165-168`), asserts only `$started` (`:180`), and unlinks the completion flag in `finally` (`:188`) without reading it; child exit code (`:153`) is discarded.
**Fix:** poll for `/tmp/libui_app_test_completed` and assert it, or use `proc_open` + `assertSame(0, proc_close($proc))`.

### I4. AppTest has 7 no-op assertTrue(true) assertions — MEDIUM / S
`tests/AppTest.php` lines 51/81/103/203/214/228/276 assert literal true (suite-wide: 36 across 8 files).
**Fix:** assert `assertSame($app, $result)` for fluent setters (the established pattern at `:231-240,251-261`); use reflection to assert stored callables; use `expectNotToPerformAssertions()` where the only observable is "did not throw".

### I5. DialogsTest never exercises the facade's only behaviour (empty→null) — MEDIUM / S
`src/Dialogs.php:62-65` `nullIfEmpty()` is the entire value-add; `tests/DialogsTest.php:18-67` only checks construction/arity/return-type, never the conversion (`nullIfEmpty` is private + dialogs block).
**Fix:** make `nullIfEmpty` `private static` (stateless) and test it via reflection, or extract to a pure helper.

### I6. PHPStan runs on macOS only — Linux/Windows guarded branches never analysed — LOW / S
`ci.yml` `static` job is `macos-latest`/`['8.5']`; `src/Window.php:121-154` guards macOS-only CoreGraphics FFI behind `PHP_OS_FAMILY !== 'Darwin'` (`:132`) with `@phpstan-ignore-next-line` (`:146`). The `test` matrix already spans 4 OSes but runs no `composer stan`.
**Fix:** add a `composer stan` step to the `test` job (before the suite, `~:101`).

### I7. HeaderGate is a string grep, not a resolve check — LOW / M
`tests/HeaderGateTest.php:31-73` greps 16 hardcoded symbols as `symbol(` in the `.h` text. A symbol present in the header but missing/renamed in the dylib still passes.
**Fix:** for each symbol, probe `Ffi::get()->{$symbol}` resolves against the loaded library; keep the grep as a fast pre-filter.

### I8. EnumComplete/Values tests pin PHP to itself, not to ui.h — LOW / M
`tests/EnumValuesTest.php` / `EnumCompleteTest.php` (and `DrawTest.php:361-387`) assert hardcoded ints against generated enums; neither parses `ui.h`/`libui.gen.h` despite docstrings claiming to guard against generator drift. An upstream reorder that regen absorbs is silently re-validated.
**Fix:** add one test that parses `src/Native/libui.gen.h` (or upstream `ui.h`) for a few enums and asserts the PHP cases match the C-derived values.

### I9. No CI guard that committed lib/ binaries match a fresh pinned build — MEDIUM / M
`ci.yml:91-94` tests committed macOS/Windows binaries; only Linux builds from source. No job verifies the committed `lib/darwin/libui.dylib`/`lib/windows-x86_64/libui.dll` came from the same upstream as the checked-in `ui.h`. Combined with the unpinned clone (I2), a stale committed dylib drifts undetected.
**Fix:** add a scheduled/release-gated job that rebuilds from the pinned ref and diffs exported symbol sets (`nm -gU` / `dumpbin /exports`) against the committed artifact, failing if any declared symbol is missing. Compare symbol sets, not bytes, to tolerate build nondeterminism.

---

## Sequencing note

The Text-subsystem memory items have an ordering constraint: **F4 (TextLayout retains its string) must land with or before F3 (AttributedString destructor)**, and **F2 (drawString leak)** is fixed either by its own `finally` or automatically once F3 lands. Treat F2/F3/F4 as one PR. Everything else is independent.