# Spec: Menus & Dialogs Facade Hardening (additive, non-breaking)

## Context & constraints
- Library shipped v0.1.0; package requires PHP >= 8.5. **All changes MUST be additive and non-breaking.** Existing signatures (`Menu::__construct(string $name)`, `MenuItem::onClicked(callable)`, `Generated\Ui::*`) keep working unchanged.
- `Libui\Generated\Ui` and `Libui\Generated\Menu` / `Libui\Generated\MenuItem` are **generated** (`@generated`, "DO NOT EDIT"). All hand edits go in the hand-written subclasses `src/Menu.php`, `src/MenuItem.php`, `src/Window.php`, and a **new** `src/Dialogs.php`. Do **not** edit any file under `src/Generated/`.
- **Color is intentionally not used here.** Menus and dialogs carry no colour in libui's C API (`uiMsgBox`, `uiOpenFile`, `uiNewMenu`, `uiMenuItemOnClicked` — none take a colour). Do not introduce `Libui\Color` into this facade.

## Relevant C signatures (from `src/Native/libui.gen.h`)
```
uiWindow *uiNewWindow(const char *title, int width, int height, int hasMenubar);
uiMenu *uiNewMenu(const char *name);
uiMenuItem *uiMenuAppendItem(uiMenu *m, const char *name);
uiMenuItem *uiMenuAppendCheckItem(uiMenu *m, const char *name);
void uiMenuItemOnClicked(uiMenuItem *m,
    void (*f)(uiMenuItem *sender, uiWindow *window, void *senderData), void *data);
char *uiOpenFile(uiWindow *parent);    // NULL on cancel
char *uiOpenFolder(uiWindow *parent);  // NULL on cancel
char *uiSaveFile(uiWindow *parent);    // NULL on cancel
void uiMsgBox(uiWindow *parent, const char *title, const char *description);
void uiMsgBoxError(uiWindow *parent, const char *title, const char *description);
```
The `onClicked` trampoline's **2nd arg is a raw `uiWindow*` CData**, not a `Libui\Window`. It must never be handed to the dialog facade.

## Existing patterns to mirror
- **Callback retention:** `Control::keep(callable): callable` stores the closure in a process-lifetime static array, preventing GC of the FFI trampoline. `MenuItem::onClicked` already wraps the user callback in `static::keep(...)`. The new `onClick` MUST do the same.
- **Owned-string handling:** `Ffi::ownedString(?\FFI\CData): string` copies a libui-owned `char*` into PHP and frees it with `uiFreeText`, returning `''` for a NULL pointer.
- **Test base:** FFI-backed tests extend `Libui\Tests\LibuiTestCase` (calls `Ffi::init()` in `setUpBeforeClass`). Pure-unit tests that touch no libui call extend `PHPUnit\Framework\TestCase` directly. `CallbackTest` is the template for "bind a callback without error" smoke tests.

---

## 1. Enforce "menus before first window" in code

### `src/Window.php` — add process-wide lock flag
Add a private static flag, set it in the constructor (the exact point `uiNewWindow` freezes libui's menu list), and expose testable accessors.

```php
class Window extends Generated\Window
{
    /** True once any Window has been constructed this process — menus are then locked. */
    private static bool $menusLocked = false;

    public function __construct(string $title, int $width = 640, int $height = 480, bool $hasMenubar = false)
    {
        parent::__construct($title, $width, $height, $hasMenubar);
        $this->width = $width;
        $this->height = $height;
        self::$menusLocked = true; // libui freezes the menu list at first window creation
    }

    /** Whether any Window has been created (after which new Menus are illegal). */
    public static function menusLocked(): bool
    {
        return self::$menusLocked;
    }

    /**
     * Reset the menu-ordering lock. For tests and rare multi-session apps that
     * call Ffi::uninit() and start a fresh libui session.
     * @internal
     */
    public static function resetMenuLockForTesting(): void
    {
        self::$menusLocked = false;
    }

    // ... existing methods unchanged ...

    /** A Dialogs facade bound to this window as the parent. */
    public function dialogs(): Dialogs
    {
        return new Dialogs($this);
    }
}
```
Keep the existing `$width`/`$height` property assignments exactly as they are; only append the flag set and the three new methods.

### `src/Exception/MenuOrderException.php` — create
```php
<?php
declare(strict_types=1);

namespace Libui\Exception;

/**
 * Thrown when a Menu is created after a Window already exists.
 *
 * libui requires every menu to be built BEFORE the first window; violating this
 * silently breaks the menu bar (and can crash). This is a programmer error, so it
 * extends LogicException.
 */
final class MenuOrderException extends \LogicException {}
```
(If no `Libui\Exception` namespace exists yet, this establishes it; confirm `composer.json` PSR-4 maps `Libui\\` to `src/` — it does, so `Libui\Exception\` resolves to `src/Exception/`.)

### `src/Menu.php` — enforce ordering + optional inline onClick
```php
<?php
declare(strict_types=1);

namespace Libui;

use Libui\Exception\MenuOrderException;

class Menu extends Generated\Menu
{
    public function __construct(string $name)
    {
        if (Window::menusLocked()) {
            throw new MenuOrderException(
                "Menu '{$name}' was created after a Window already exists. libui requires "
                . 'every menu to be built BEFORE the first window. Move all `new Menu(...)` '
                . 'calls above your first `new Window(...)`.'
            );
        }
        parent::__construct($name);
    }

    /** Append a clickable item, optionally wiring a clean fn(MenuItem $item) handler. */
    public function appendItem(string $name, ?callable $onClick = null): MenuItem
    {
        /** @var MenuItem $item */
        $item = parent::appendItem($name); // generated returns Generated\MenuItem; Control::wrap uses static::class so this is a Libui\MenuItem
        if ($onClick !== null) {
            $item->onClick($onClick);
        }
        return $item;
    }

    /** Append a check item, optionally wiring a clean fn(MenuItem $item) handler. */
    public function appendCheckItem(string $name, ?callable $onClick = null): MenuItem
    {
        /** @var MenuItem $item */
        $item = parent::appendCheckItem($name);
        if ($onClick !== null) {
            $item->onClick($onClick);
        }
        return $item;
    }
}
```
**Important type note:** `Generated\Menu::appendItem` calls `Generated\MenuItem::wrap(...)`. `Control::wrap` uses `new \ReflectionClass(static::class)`. Because the generated `appendItem` body hardcodes `\Libui\Generated\MenuItem::wrap(...)`, the returned object is a `Generated\MenuItem`, **not** a `Libui\MenuItem`. Therefore `$item->onClick(...)` would not exist on the generated return. To make `onClick` callable on the inline-append path, the override in `src/Menu.php` MUST re-wrap or the implementer MUST instead define `onClick` so it is reachable. **Resolution:** Implement `onClick` on `Libui\MenuItem`, and in `Menu::appendItem`/`appendCheckItem` re-wrap the handle into a `Libui\MenuItem`:
```php
$generated = parent::appendItem($name);
$item = MenuItem::wrap($generated->handle());
```
`MenuItem::wrap` (inherited from `Control`, `protected static`) is accessible from within `Menu` only if same class hierarchy — it is not. So expose a tiny hand-written public factory on `Libui\MenuItem`:
```php
// in Libui\MenuItem
public static function fromGenerated(Generated\MenuItem $g): self
{
    return self::wrap($g->handle());
}
```
Then `Menu::appendItem` does `$item = MenuItem::fromGenerated(parent::appendItem($name));`. This keeps everything additive and gives a real `Libui\MenuItem` with `onClick`.

### `src/MenuItem.php` — clean handler hiding the raw uiWindow*
```php
<?php
declare(strict_types=1);

namespace Libui;

class MenuItem extends Generated\MenuItem
{
    /** Re-wrap a generated MenuItem handle as a hand-written Libui\MenuItem. */
    public static function fromGenerated(Generated\MenuItem $g): self
    {
        return self::wrap($g->handle());
    }

    /**
     * Register a click handler that receives only this typed MenuItem.
     *
     * Unlike the raw onClicked(), this hides libui's raw uiWindow* second
     * argument (which must never be passed to the Dialogs/Ui facade). Capture
     * your typed Window via `use ($window)` if you need it for dialogs.
     *
     *   $item->onClick(fn (MenuItem $item) => $item->setChecked(! $item->checked()));
     *
     * @param callable(MenuItem):void $cb
     */
    public function onClick(callable $cb): static
    {
        $fn = static::keep(function ($sender, $window, $data) use ($cb): void {
            try {
                $cb($this);
            } catch (\Throwable $e) {
                \fwrite(\STDERR, "[onClick] {$e->getMessage()}\n");
            }
        });
        Ffi::get()->uiMenuItemOnClicked($this->handle(), $fn, null);
        return $this;
    }
}
```
`onClicked` (the generated 2-arg form) stays available via inheritance — back-compat preserved.

---

## 2. Parent-bound `Dialogs` facade

### `src/Dialogs.php` — create
```php
<?php
declare(strict_types=1);

namespace Libui;

/**
 * Dialog helpers bound to a parent Window, so call sites don't repeat $parent.
 *
 *   $dialogs = $window->dialogs();         // or Dialogs::for($window)
 *   $dialogs->msgBox('Done', 'Saved.');
 *   $path = $dialogs->openFile();          // null on cancel
 *
 * Wraps the same libui functions as Libui\Generated\Ui, but returns ?string
 * (null on cancel) instead of '' for the file choosers.
 */
final class Dialogs
{
    public function __construct(private readonly Window $parent) {}

    public static function for(Window $parent): self
    {
        return new self($parent);
    }

    public function msgBox(string $title, string $description): void
    {
        Ffi::get()->uiMsgBox($this->parent->handle(), $title, $description);
    }

    public function error(string $title, string $description): void
    {
        Ffi::get()->uiMsgBoxError($this->parent->handle(), $title, $description);
    }

    /** @return string|null Selected path, or null if cancelled. */
    public function openFile(): ?string
    {
        return $this->nullIfEmpty(Ffi::ownedString(Ffi::get()->uiOpenFile($this->parent->handle())));
    }

    /** @return string|null Selected folder, or null if cancelled. */
    public function openFolder(): ?string
    {
        return $this->nullIfEmpty(Ffi::ownedString(Ffi::get()->uiOpenFolder($this->parent->handle())));
    }

    /** @return string|null Chosen save path, or null if cancelled. */
    public function saveFile(): ?string
    {
        return $this->nullIfEmpty(Ffi::ownedString(Ffi::get()->uiSaveFile($this->parent->handle())));
    }

    private function nullIfEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
```
`Ffi::ownedString` already frees the libui-owned `char*`, so there is no leak/double-free; mapping `''` to `null` happens purely in PHP after the C string is freed. `Window::handle()` returns the `uiWindow*` CData expected by these functions (inherited from `Control`).

---

## 3. DESCOPED: native `confirm()` (yes/no)

**Not implemented.** libui exposes only `uiMsgBox` (OK) and `uiMsgBoxError` (OK) — neither returns a result and there is no native yes/no dialog. A synchronous modal would require a nested `uiMain` loop, which is fragile (re-entrancy, quit handling, platform divergence) and explicitly out of scope. Document this as a known limitation in the `Dialogs` class docblock and in the user guide:

> libui has no native yes/no confirmation dialog and no synchronous modal result. To confirm an action, build a small modal `Window` with two `Button`s (e.g. "Confirm"/"Cancel") and run your continuation in the button's `onClicked` callback, or drive the flow with `Ffi::queueMain(...)`. `Dialogs::msgBox()` / `error()` are informational (OK-only).

Do **not** ship a nested-loop hack.

---

## 4. Test plan (TDD)

Create `tests/MenuTest.php` and `tests/DialogsTest.php`. Mark FFI-backed ones with `#[Group('smoke')]` and extend `LibuiTestCase`. Ordering tests must call `Window::resetMenuLockForTesting()` in `setUp()` so they are order-independent within the shared PHPUnit process.

### `tests/MenuTest.php`
- `testMenuCreatedBeforeAnyWindowSucceeds` — after `Window::resetMenuLockForTesting()`, `new Menu('File')` does not throw and is `instanceof Menu`.
- `testMenuCreatedAfterWindowThrowsMenuOrderException` — reset, `new Window('W', 100, 100, false)`, then `expectException(MenuOrderException::class)` on `new Menu('Late')`; assert message mentions "before the first window".
- `testMenuOrderExceptionIsLogicException` — `MenuOrderException` is `instanceof \LogicException`.
- `testWindowMenusLockedFlagFlipsOnFirstWindow` — reset → `Window::menusLocked()` is false; construct a Window → it is true.
- `testMultipleMenusBeforeWindowAllSucceed` — reset, build three menus, none throw.
- `testMenuItemOnClickReturnsThisForChaining` — reset; build a menu, `$item = $menu->appendItem('X')`; `assertSame($item, $item->onClick(fn () => null))`.
- `testMenuItemOnClickAcceptsSingleArgHandler` — bind `fn (MenuItem $i) => null` without error (FFI-backed smoke, mirrors `CallbackTest`).
- `testAppendItemWithInlineOnClickReturnsMenuItem` — `$menu->appendItem('Open', fn (MenuItem $i) => null)` returns a `Libui\MenuItem` and binds without error.
- `testAppendCheckItemWithInlineOnClickReturnsMenuItem` — same for `appendCheckItem`.
- `testOnClickHandlerExceptionIsCaughtNotFatal` — a handler that throws is swallowed; assert no exception escapes the binding call (the catch-and-STDERR path; can be asserted at bind time since invocation needs the event loop — assert binding a throwing handler does not itself throw).

### `tests/DialogsTest.php`
- `testDialogsForReturnsInstanceBoundToWindow` — `Dialogs::for($window) instanceof Dialogs`.
- `testWindowDialogsReturnsDialogsFacade` — `$window->dialogs() instanceof Dialogs`.
- `testDialogsConstructorAcceptsWindow` — `new Dialogs($window)` constructs without error.
- `testDialogsMethodsExistWithParentlessSignatures` — via `ReflectionMethod`, assert `msgBox`, `error`, `openFile`, `openFolder`, `saveFile` exist and that the file-chooser methods take **zero** required parameters (the `$parent` boilerplate is gone).
- `testOpenFileReturnsNullableString` — via reflection, `openFile`/`openFolder`/`saveFile` return type is `?string` (contrast with generated `Ui::openFile` returning `string`). Do **not** actually invoke them (they would block on a real dialog).

### Existing suites
- Re-run `tests/AppTest.php` and `tests/CallbackTest.php` unchanged to prove the additive changes don't regress lifecycle or callback retention. Note: `AppTest` constructs `Window` objects in the shared process, which flips `menusLocked`; the new ordering tests handle this via `resetMenuLockForTesting()` in `setUp()`, so test ordering is irrelevant.

---

## 5. Example update (optional, keep runnable)
In `examples/menu.php`, after the menus/window are built, demonstrate the new ergonomics without breaking the file:
```php
$dialogs = $window->dialogs();
$open->onClick(function (\Libui\MenuItem $item) use ($dialogs) {
    $path = $dialogs->openFile();
    if ($path !== null) {
        $dialogs->msgBox('You picked', $path);
    }
});
$about->onClick(fn (\Libui\MenuItem $item) => $dialogs->msgBox('About', 'PHP libui menu demo'));
```
This showcases `onClick` (no raw `$win`) and `Window::dialogs()` (no repeated `$parent`).

---

## 6. Acceptance checklist
- [ ] `composer test` green, including new `MenuTest`/`DialogsTest`.
- [ ] PHPStan clean (update `phpstan-baseline.neon` only if a genuinely unavoidable dynamic-FFI ignore is needed; prefer typed code).
- [ ] No edits under `src/Generated/`.
- [ ] `new Menu()` after a `new Window()` throws `MenuOrderException` with an actionable message.
- [ ] `MenuItem::onClick(fn (MenuItem) => ...)` works and never exposes the raw `uiWindow*`.
- [ ] `Window::dialogs()` / `Dialogs::for()` remove the repeated `$parent`; file choosers return `?string` (null on cancel).
- [ ] `confirm()` is absent by design and documented as a limitation.
- [ ] Existing `Menu::__construct(string)`, `MenuItem::onClicked(callable)`, and `Generated\Ui::*` still work (back-compat).