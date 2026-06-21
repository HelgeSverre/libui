# Tables facade — implementation-ready spec

## Goal
Remove four frictions in the Tables facade. Strictly **additive / non-breaking** (library is v0.1.0, PHP >=8.5). Reuse the existing `Libui\Color` value type for all colour. Mirror existing patterns: `Control::keep` for callback retention, `TableModel::$callbacks` for the handler vtable, `TableModel::guard()` for throw-into-C safety, `LibuiTestCase` for FFI tests.

Grounding (verified in the tree):
- `src/Table.php` already has `onRowClicked`/`onRowDoubleClicked` whose trampolines receive `($t, $row, $data)` but discard `$row` (call `$cb($this)`).
- `src/Native/libui.gen.h`: `uiTableOnRowClicked`/`uiTableOnRowDoubleClicked` callback is `void(*)(uiTable*, int row, void*)`. There is **no** `uiTableOnButtonClicked`. Button/checkbox edits arrive via the handler's `SetCellValue(mh, m, row, column, uiTableValue*)` (null value for button press).
- `uiTableParams { uiTableModel *Model; int RowBackgroundColorModelColumn; }` — the bg column is read once by `uiNewTable()`; currently hardcoded to `-1` (`Table::NO_ROW_BACKGROUND`).
- `uiTableTextColumnOptionalParams { int ColorModelColumn; }` — `uiTableAppendTextColumn` accepts it as 5th arg (currently passed `null`).
- `TableModel::makeHandler()` `CellValue` only emits Int/String today.
- The crash: `uiFreeTableModel` must be called **exactly once**, **after** the `uiTable` is destroyed and **before** `uiUninit()`; otherwise libui's leak checker `__builtin_trap()`s in `uiUninit()` (SIGTRAP, macOS). Today users must remember `Window::run(afterClose: fn () => $table->model()->free())` (see `examples/table.php`).
- `Ffi::uninit()` calls `uiUninit()` then resets `$initialized`. `Window::run()`/`App::run()` both call `Ffi::uninit()` in a `finally` after the loop and after windows are destroyed.

---

## 1. Crash-proof lifetime (no more SIGTRAP on a forgotten free)

### New: `src/Lifecycle.php`
A process-wide registry of live `TableModel`s, drained right before `uiUninit()`.

```php
<?php
declare(strict_types=1);
namespace Libui;

/**
 * Process-wide registry of native resources that must be released before
 * uiUninit(). Today: uiTableModels — libui's leak checker aborts in uiUninit()
 * if a model is left unfreed, so Ffi::uninit() drains this registry first.
 */
final class Lifecycle
{
    /** @var \SplObjectStorage<TableModel,null> */
    private static ?\SplObjectStorage $models = null;

    public static function registerModel(TableModel $model): void
    {
        self::$models ??= new \SplObjectStorage();
        self::$models->attach($model);
    }

    public static function unregisterModel(TableModel $model): void
    {
        self::$models?->detach($model);
    }

    /**
     * Free every still-live registered model exactly once.
     * Called by Ffi::uninit() immediately before uiUninit(). TableModel::free()
     * is idempotent and de-registers, so manual free()s earlier are safe.
     */
    public static function freeAll(): void
    {
        if (self::$models === null) {
            return;
        }
        // Snapshot: free() mutates the storage via unregisterModel().
        foreach (iterator_to_array(self::$models) as $model) {
            $model->free();
        }
        self::$models = new \SplObjectStorage();
    }
}
```

### Modify: `src/Ffi.php` — `uninit()`
Drain the registry before tearing libui down:
```php
public static function uninit(): void
{
    Lifecycle::freeAll();          // <-- ADDED: free any forgotten TableModels first
    self::get()->uiUninit();
    self::$initialized = false;
}
```
(Add `use` only if needed; same namespace `Libui`, so no import required.)

### Modify: `src/TableModel.php`
- In `__construct()`, after `$this->model = $ffi->uiNewTableModel(...)`, add `Lifecycle::registerModel($this);`.
- In `free()`, after setting `$this->freed = true;`, add `Lifecycle::unregisterModel($this);` (call it inside the method regardless of early-return: if already freed, return as today; on the real free path de-register).

```php
public function free(): void
{
    if ($this->freed) {
        return;
    }
    Ffi::get()->uiFreeTableModel($this->model);
    $this->freed = true;
    Lifecycle::unregisterModel($this);
}
```

`free()` stays public, idempotent, and documented — existing `examples/table.php` (`afterClose: fn () => $table->model()->free()`) keeps working: it frees + de-registers, so the later `freeAll()` in `uninit()` is a no-op.

**Why this is the correct ordering:** `Lifecycle::freeAll()` runs inside `Ffi::uninit()`. In every supported entrypoint the loop has returned and the window/control tree (hence the `uiTable`) is already destroyed before `uinit()` is reached. So freeing models there satisfies libui's "table dead before model" rule. This neutralises the footgun without changing any public call site.

---

## 2. Zero-boilerplate read-only data

### New: `src/ArrayTableModelDelegate.php`
A concrete `TableModelDelegate` over an in-memory row array, with per-column typing and headers.

```php
<?php
declare(strict_types=1);
namespace Libui;

use Libui\Generated\Enum\TableValueType;

final class ArrayTableModelDelegate extends TableModelDelegate
{
    /**
     * @param list<list<string|int|bool|Color|Image|null>> $rows  row-major cells
     * @param list<string>                                  $headers column titles (drives numColumns)
     * @param array<int,TableValueType>                     $types   per-column type override (default String)
     */
    public function __construct(
        private array $rows,
        private array $headers,
        private array $types = [],
    ) {}

    /** @return list<string> */
    public function headers(): array { return $this->headers; }

    public function numColumns(): int { return \count($this->headers); }
    public function numRows(): int { return \count($this->rows); }

    public function columnType(int $column): TableValueType
    {
        return $this->types[$column] ?? TableValueType::String;
    }

    public function cellValue(int $row, int $column): string|int|bool|Color|Image|null
    {
        return $this->rows[$row][$column] ?? '';
    }
}
```

### Modify: `src/Table.php` — factories
Add (keep existing `fromDelegate`/`fromModel`/`fromRows`-free state untouched):

```php
/**
 * Build a read-only table from a list of positional rows.
 *
 * @param list<list<string|int>> $rows   row-major scalar cells
 * @param list<string>           $headers column titles; if empty, one column per
 *                                        first-row cell named "Column 1".."Column N"
 */
public static function fromRows(array $rows, array $headers = []): static
{
    if ($headers === []) {
        $width = $rows === [] ? 0 : \count($rows[0]);
        $headers = array_map(static fn (int $i) => 'Column ' . ($i + 1), range(0, max(0, $width - 1)));
        if ($width === 0) { $headers = []; }
    }
    $delegate = new ArrayTableModelDelegate(array_map('array_values', $rows), array_values($headers));
    $table = self::fromDelegate($delegate);
    foreach ($headers as $i => $name) {
        $table->appendTextColumn($name, $i);
    }
    return $table;
}

/**
 * Build a read-only table from a list of associative rows.
 *
 * @param list<array<string,string|int>> $rows
 * @param list<string>|null $columns column keys to show, in order; defaults to
 *                                   array_keys() of the first row. Header = key.
 */
public static function fromAssoc(array $rows, ?array $columns = null): static
{
    $columns ??= $rows === [] ? [] : array_keys($rows[0]);
    $columns = array_values($columns);
    $positional = array_map(
        static fn (array $row) => array_map(static fn (string $k) => $row[$k] ?? '', $columns),
        $rows,
    );
    return self::fromRows($positional, $columns);
}
```

Notes for implementer:
- `fromRows`/`fromAssoc` return a `Table` with text columns already appended; callers can still chain typed columns or selection config afterward.
- Because `ArrayTableModelDelegate` and the appended columns share the same column indexing, no extra mapping is needed.

---

## 3. Row-index in click callbacks (additive widening)

### Modify: `src/Table.php`
Thread the `int $row` the C trampoline already receives:

```php
public function onRowClicked(callable $cb): static
{
    $fn = Control::keep(function ($t, $row) use ($cb) {
        $cb($this, $row);
    });
    Ffi::get()->uiTableOnRowClicked($this->handle, $fn, null);
    return $this;
}

public function onRowDoubleClicked(callable $cb): static
{
    $fn = Control::keep(function ($t, $row) use ($cb) {
        $cb($this, $row);
    });
    Ffi::get()->uiTableOnRowDoubleClicked($this->handle, $fn, null);
    return $this;
}
```

This is non-breaking: the parameter type is still `callable`. Old callbacks declared as `fn ($table)` ignore the extra arg; new callbacks use `fn (Table $t, int $row)`. Wrap the body in the same defensive pattern if desired (a throw here would be a hard fatal — match `onSelectionChanged`, which does not currently try/catch; keep consistent, but a try/catch reporting to STDERR is acceptable and recommended). `onSelectionChanged` stays `fn(Table $t): void`.

---

## 4. Typed columns + handlers + per-row background colour

### 4a. Extend the CellValue marshaller — `src/TableModel.php` `makeHandler()`
Replace the `CellValue` closure body so it emits Int **and** String **and** Image **and** Color, driven by `columnType`:

```php
$this->callbacks['CellValue'] = static fn ($mh, $m, $row, $column) => self::guard(static function () use ($delegate, $ffi, $row, $column) {
    $type  = $delegate->columnType($column);
    $value = $delegate->cellValue($row, $column);

    return match ($type) {
        TableValueType::Int   => $ffi->uiNewTableValueInt((int) $value),
        TableValueType::Color => $value instanceof Color
            ? $ffi->uiNewTableValueColor($value->r, $value->g, $value->b, $value->a)
            : null, // null = "no colour" for this row's bg column
        TableValueType::Image => $value instanceof Image && $value->handle() !== null
            ? $ffi->uiNewTableValueImage($value->handle())
            : null,
        default               => $ffi->uiNewTableValueString((string) $value), // String + bool->"0"/"1" handled by caller convention
    };
}, null);
```

Important details:
- Checkbox/progress columns are **Int** under the hood (libui reads 0/1 for checkbox, 0..100 for progress) — delegates return `int` (or `bool`, cast via the Int branch). No new TableValueType.
- `uiNewTableValueColor` takes the four 0..1 doubles directly from `Color`'s public readonly `$r/$g/$b/$a`.
- `uiNewTableValueImage` takes `Image::handle()` (a `uiImage*`); the Image must outlive the table — the delegate holds the reference.
- libui frees the returned value; we always mint fresh — never cache.

`use Libui\Generated\Enum\TableValueType;` is already imported. Add `Color`/`Image` are same-namespace, no import needed.

### 4b. Optional column args — `src/Table.php`
Make the column builders expose the C parameters they were hiding. All new args default to a value that reproduces today's behaviour.

```php
public function appendTextColumn(string $name, int $modelColumn, ?int $editableModelColumn = null, ?int $colorModelColumn = null): static
{
    $params = null;
    if ($colorModelColumn !== null) {
        $params = Ffi::get()->new('uiTableTextColumnOptionalParams');
        $params->ColorModelColumn = $colorModelColumn;
        $this->keepStruct($params); // retain so the pointer stays valid (see note)
        $params = \FFI::addr($params);
    }
    Ffi::get()->uiTableAppendTextColumn(
        $this->handle, $name, $modelColumn,
        $editableModelColumn ?? self::NEVER_EDITABLE,
        $params,
    );
    return $this;
}

public function appendCheckboxColumn(string $name, int $modelColumn, ?int $editableModelColumn = null): static
{
    Ffi::get()->uiTableAppendCheckboxColumn($this->handle, $name, $modelColumn, $editableModelColumn ?? self::NEVER_EDITABLE);
    return $this;
}

public function appendButtonColumn(string $name, int $modelColumn, ?int $clickableModelColumn = null): static
{
    // libui delivers a button press through the model's SetCellValue(row, $modelColumn, null);
    // route click handling through the delegate (see CallbackTableModelDelegate / setCellValue).
    Ffi::get()->uiTableAppendButtonColumn($this->handle, $name, $modelColumn, $clickableModelColumn ?? self::NEVER_EDITABLE);
    return $this;
}
```

`keepStruct()`: add a small private retention array on `Table` (`private array $retainedStructs = [];`) and `private function keepStruct(\FFI\CData $s): void { $this->retainedStructs[] = $s; }` so the `uiTableTextColumnOptionalParams` struct isn't GC'd while libui holds the pointer (mirrors how `$this->params` is retained as a property today). Only needed when `$colorModelColumn !== null`.

`appendCheckboxTextColumn`, `appendProgressBarColumn`, `appendImageColumn`, `appendImageTextColumn` keep their existing signatures (no required change). Optionally add the `editableModelColumn`/text-params variants for parity, but that is **out of scope** unless trivial.

### 4c. Per-row background colour
`RowBackgroundColorModelColumn` is read once by `uiNewTable()` and cannot change afterward. Therefore expose it at **construction time**, not as a live setter.

Design (implementer: choose form A; it is additive):

**Form A — optional constructor arg + guard on the setter.**
```php
public function __construct(TableModel $model, ?int $rowBackgroundModelColumn = null)
{
    // ... existing body ...
    $this->params->RowBackgroundColorModelColumn = $rowBackgroundModelColumn ?? self::NO_ROW_BACKGROUND;
    $this->handle = $ffi->uiNewTable(\FFI::addr($this->params));
}

/**
 * Point the table at a Color model column for per-row background. The column's
 * columnType() must be TableValueType::Color and cellValue() must return a
 * Libui\Color or null. MUST be called before the native table exists, i.e. only
 * via the constructor — this fluent form throws if the handle is already built.
 */
public function setRowBackground(int $colorModelColumn): static
{
    throw new \LogicException(
        'Row background must be set at construction: new Table($model, rowBackgroundModelColumn: N). '
        . 'uiTableParams.RowBackgroundColorModelColumn is read once by uiNewTable() and cannot change afterward.'
    );
}
```
The `setRowBackground()` method exists for discoverability and points the user at the constructor arg. (Alternatively omit the method entirely and document the constructor arg only — implementer's call; do NOT ship a setter that silently no-ops.)

Add a factory convenience: `Table::fromRows(..., rowBackground: ?callable)` is **descoped** for v1 (keep factories simple); users wanting row backgrounds build the delegate by hand with a Color column and pass `rowBackgroundModelColumn` to the constructor. Document this in the spec/example.

`fromDelegate`/`fromModel` gain an optional `?int $rowBackgroundModelColumn = null` pass-through to the constructor (additive).

### 4d. Click/toggle handlers (optional convenience delegate)
Because button/checkbox interactions arrive through `SetCellValue`, the clean public ergonomic is a delegate that maps writes to handlers. Provide it **only if time allows** — the primitive (override `setCellValue`) already works and is the documented path. If implemented:

`CallbackTableModelDelegate extends ArrayTableModelDelegate` with:
- `onButtonClick(int $column, callable $cb)` — invoked from `setCellValue($row,$column,null)`.
- `onCheckboxToggle(int $column, callable $cb)` — invoked from `setCellValue($row,$column,$boolish)`, also updates the backing array.

This is additive and optional; mark as **stretch** in the plan. The required deliverable is that `setCellValue` routing works (it already does) and the column builders accept the editable/clickable columns.

---

## Tests (TDD)

### Pure-unit (plain `TestCase`, no libui) — `tests/TableFacadeTest.php`
- `testFromRowsBuildsOneColumnPerHeader` — model `numColumns()==2`, `headers()===['X','Y']`.
- `testFromRowsWithoutHeadersUsesFirstRowArity` — `numColumns()===count(first row)`; default header names.
- `testFromRowsCellValuesMatch` — scalars preserved (string stays string, int stays int).
- `testFromAssocUsesKeysAsHeaders` — headers from first-row keys; `cellValue(0,1)===30`.
- `testFromAssocExplicitColumnsSubsetAndOrder` — `columns:['age','name']` reorders/subsets.
- `testFromAssocRaggedRowsMissingKeyYieldsEmpty` — missing key -> `''`, no warning.
- `testArrayDelegateColumnTypeDefaultsString` / `...HonoursTypesMap`.
- `testArrayDelegateNumRowsReflectsArray` (empty -> 0).

> Build the delegate directly (`new ArrayTableModelDelegate(...)`) for the pure-unit assertions; `Table::fromRows()` itself constructs a `Table` which needs FFI — so split: assert delegate behaviour purely, and assert factory wiring under `LibuiTestCase`. (`Table::fromRows` calls `uiNewTable`; put any test that actually constructs a `Table` in a `LibuiTestCase`.)

### Lifecycle unit — `tests/TableFacadeTest.php` or `tests/LifecycleTest.php`
Use a spy: a tiny `TableModel` is hard to fake (ctor needs FFI). Two options — pick (a):
(a) Under `LibuiTestCase`, build a real `TableModel`, assert it's freed by `Lifecycle::freeAll()` (then `Ffi::init()` again is NOT needed; freeAll doesn't uninit). Assert `free()` second call is a no-op.
- `testFreeAllFreesRegisteredModelOnce`
- `testUnregisterRemovesFromFreeAll`
- `testFreeAllIsIdempotent`

### Subprocess lifecycle — `tests/table_lifecycle.php` + `tests/TableModelTest.php`
Add an `'auto'` mode to `table_lifecycle.php`: build table, `$table->destroy();`, then **do not** call `free()` — just `Ffi::uninit();`. Expect exit 0 (Lifecycle freed it).
```php
case 'auto':
    // No explicit free(): Ffi::uninit() must free via Lifecycle and exit clean.
    break;
```
New test:
- `TableModelTest::testForgottenFreeDoesNotAbort` — `assertSame(0, runLifecycle('auto'))`.
Keep `freed`, `double-free`, `leak` modes green (the `leak` negative control must bypass registration — see below).

**`leak` mode must stay a true negative control.** Today `leak` relies on the model never being freed. With auto-registration, `Ffi::uninit()` would now free it and the abort would never fire, breaking the negative control. Fix: in `'leak'` mode, after building, call `Lifecycle::unregisterModel($table->model())` (or have the runner construct the model and immediately unregister) so the leak detector still fires. Document this in the runner. Add:
- `TableModelTest::testLeakDetectorStillLiveWhenUnregistered` — `assertNotSame(0, runLifecycle('leak'))` (macOS-only, as today).

### FFI-backed — `tests/TableSelectionTest.php` (extend) / `tests/TableFunctionalTest.php`
- `testAppendTextColumnColorArgIsOptional` — `Table::fromDelegate($d)->appendTextColumn('N',0)` builds without error (back-compat).
- `testOnRowClickedForwardsRowIndex` — there is no headless "click row" API, so assert structurally: register `onRowClicked(fn($t,$row)=>...)`; since we can't synthesize the C event, **descope** the end-to-end and instead unit-test the trampoline shape by invoking the retained closure directly with a fabricated `($sender, 3, null)` and asserting the user callback saw `(Table, 3)`. (Document this limitation.)
- `testConstructorRowBackgroundColumnAccepted` — `new Table($model, rowBackgroundModelColumn: 3)` constructs; assert no throw. (Can't read back the param after `uiNewTable`, so this is a smoke assertion.)

---

## Out of scope / descoped (explicit)
- **Native confirm/click synthesis**: libui exposes no API to programmatically click a table row, so the row-click index is verified structurally (trampoline forwarding), not via a synthetic C event. The C signature guarantees the real index.
- **Live `setRowBackground()` after construction**: impossible — `uiTableParams` is consumed by `uiNewTable()`. Exposed via constructor arg; the fluent method throws with guidance (or is omitted).
- **`uiTableOnButtonClicked`**: does not exist. Button clicks go through `SetCellValue(row, col, null)`; handled via delegate override / optional `CallbackTableModelDelegate`.
- **`CallbackTableModelDelegate`** (sugar for click/toggle handlers): stretch goal; the documented primitive is overriding `setCellValue`.
- **Reading colour back** from a `uiTableValue` (`uiTableValueColor`): not needed for write-only background/colour rendering.

## Back-compat checklist
- No existing public signature changes meaning; only **new optional trailing params** and **widened callback arity**.
- `TableModel::free()` semantics unchanged (still public, idempotent) — now also de-registers.
- `examples/table.php`'s explicit `afterClose` free still works (free + de-register, then `freeAll()` is a no-op). Optionally simplify the example to demonstrate auto-free, but keep at least one doc showing explicit `free()` remains valid.
- Regenerate `phpstan-baseline.neon` after widening `cellValue()`'s return type.
