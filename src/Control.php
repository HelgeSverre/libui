<?php

declare(strict_types=1);

namespace Libui;

/**
 * Base class for every libui widget.
 *
 * libui treats all controls as subclasses of `uiControl`, so the common verbs
 * (show/hide/enable/...) live here once and operate on the `uiControl *` upcast.
 * Every generated widget extends this class.
 */
abstract class Control
{
    protected \FFI\CData $handle;

    /**
     * Native callback trampolines, retained for the process lifetime.
     *
     * PHP closures handed to C as function pointers are otherwise garbage-
     * collected while libui still holds the pointer — freeing the trampoline
     * mid-event-loop and crashing. Storing them statically keeps them alive
     * even after the owning widget object is gone.
     */
    private static array $callbacks = [];

    /**
     * Returns the raw native handle for this widget.
     *
     * This is the `uiX *` pointer (e.g., `uiButton *`) that libui uses internally.
     * Most applications don't need to access this directly.
     *
     * @return \FFI\CData The widget's native handle as FFI \FFI\CData
     */
    public function handle(): \FFI\CData
    {
        return $this->handle;
    }

    /**
     * Returns this widget upcast to the generic uiControl pointer.
     *
     * This is used internally for operations that work on any control type,
     * such as adding to containers or checking visibility.
     *
     * @return \FFI\CData The widget's handle as uiControl *
     */
    public function asControl(): \FFI\CData
    {
        return Ffi::control($this->handle);
    }

    /**
     * Retains a PHP callback to prevent garbage collection.
     *
     * When PHP closures are passed to C as function pointers, FFI creates a native
     * trampoline. If PHP garbage-collects the closure while libui still holds the
     * pointer, the next event will crash. This method stores the closure statically
     * for the process lifetime.
     *
     * @param callable $cb The callback closure to retain
     * @return callable The same callback, now retained
     */
    protected static function keep(callable $cb): callable
    {
        self::$callbacks[] = $cb;
        return $cb;
    }

    /**
     * Returns the trampolines retained via {@see Control::keep()}.
     *
     * Exposed solely so the testing harness ({@see \Libui\Testing\Inspect})
     * can assert that event handlers were registered without running the
     * event loop. Has no effect on widget behaviour.
     *
     * @internal
     * @return list<callable> The retained callbacks, in registration order
     */
    public static function retainedCallbacks(): array
    {
        return self::$callbacks;
    }

    /**
     * Builds an instance around an existing native handle, bypassing the constructor.
     *
     * This is used by factory methods (like DateTimePicker::dateOnly()) to create
     * widget instances from native handles returned by libui's constructor functions.
     *
     * @param \FFI\CData $handle The native widget handle
     * @return static A new widget instance wrapping the handle
     */
    protected static function wrap(\FFI\CData $handle): static
    {
        $obj = new \ReflectionClass(static::class)->newInstanceWithoutConstructor();
        $obj->handle = $handle;
        return $obj;
    }

    // --- common uiControl verbs (inherited by every widget) ------------------

    /**
     * Makes the widget visible.
     *
     * @return static Returns $this for method chaining
     */
    public function show(): static
    {
        Ffi::get()->uiControlShow($this->asControl());
        return $this;
    }

    /**
     * Hides the widget.
     *
     * @return static Returns $this for method chaining
     */
    public function hide(): static
    {
        Ffi::get()->uiControlHide($this->asControl());
        return $this;
    }

    /**
     * Enables the widget (allows user interaction).
     *
     * @return static Returns $this for method chaining
     */
    public function enable(): static
    {
        Ffi::get()->uiControlEnable($this->asControl());
        return $this;
    }

    /**
     * Disables the widget (prevents user interaction).
     *
     * @return static Returns $this for method chaining
     */
    public function disable(): static
    {
        Ffi::get()->uiControlDisable($this->asControl());
        return $this;
    }

    /**
     * Destroys the widget and frees its resources.
     *
     * After calling destroy(), the widget handle becomes invalid.
     * Note: This does NOT remove the widget from its parent container.
     */
    public function destroy(): void
    {
        Ffi::get()->uiControlDestroy($this->asControl());
    }

    /**
     * Checks if the widget is currently visible.
     *
     * @return bool True if visible, false otherwise
     */
    public function visible(): bool
    {
        return Ffi::get()->uiControlVisible($this->asControl()) !== 0;
    }

    /**
     * Checks if the widget is currently enabled.
     *
     * @return bool True if enabled, false otherwise
     */
    public function enabled(): bool
    {
        return Ffi::get()->uiControlEnabled($this->asControl()) !== 0;
    }

    /**
     * Checks if the widget is a toplevel widget (e.g., a Window).
     *
     * @return bool True if toplevel, false otherwise
     */
    public function toplevel(): bool
    {
        return Ffi::get()->uiControlToplevel($this->asControl()) !== 0;
    }
}
