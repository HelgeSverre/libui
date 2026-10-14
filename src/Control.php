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
     * Whether destroy() has already freed this control. Guards against a double
     * uiControlDestroy() (which frees an already-freed pointer and crashes).
     * Descendants are invalidated recursively when their native parent is destroyed.
     */
    private bool $destroyed = false;

    /** Copying this object would alias a native pointer and allow double destruction. */
    final protected function __clone(): void
    {
        throw new \LogicException('Native controls cannot be cloned.');
    }

    /** @var \SplObjectStorage<Control,null>|null */
    private static ?\SplObjectStorage $live = null;

    /** @var list<Control> */
    private array $children = [];

    /** @var \WeakReference<Control>|null */
    private ?\WeakReference $owner = null;

    /** Register a native handle so its PHP owner outlives all native uses. */
    protected function initializeHandle(\FFI\CData $handle): void
    {
        $this->handle = $handle;
        self::$live ??= new \SplObjectStorage();
        self::$live->offsetSet($this, null);
    }

    protected function assertAlive(): void
    {
        if ($this->destroyed) {
            throw new \LogicException(static::class . ' has been destroyed.');
        }
    }

    private function isNativeControl(): bool
    {
        return ! ($this instanceof Generated\Menu || $this instanceof Generated\MenuItem);
    }

    /** Validate an adoption before calling C; leave ownership unchanged on failure. */
    protected function assertCanAdopt(?Control $child, bool $replacing = false): void
    {
        $this->assertAlive();
        if ($child === null) {
            return;
        }
        $child->asControl();
        if ($child->owner !== null && ! ($replacing && $child->owner->get() === $this && ($this->children[0] ?? null) === $child) || $child instanceof Generated\Window) {
            throw new \LogicException('The child must be detached and cannot be a top-level window.');
        }
        for ($ancestor = $this; $ancestor !== null; $ancestor = $ancestor->owner?->get()) {
            if ($ancestor === $child) {
                throw new \LogicException('A control cannot contain itself or an ancestor.');
            }
        }
    }

    protected function adoptChild(Control $child, ?int $index = null): void
    {
        $child->owner = \WeakReference::create($this);
        \array_splice($this->children, $index ?? \count($this->children), 0, [$child]);
    }

    protected function replaceChild(?Control $child): void
    {
        foreach ($this->children as $old) {
            $old->owner = null;
        }
        $this->children = [];
        if ($child !== null) {
            $this->adoptChild($child);
        }
    }

    protected function assertChildIndex(int $index, bool $inserting = false): void
    {
        $this->assertAlive();
        $this->assertIndex($index, \count($this->children), $inserting);
    }

    protected function assertIndex(int $index, int $count, bool $inserting = false): void
    {
        $this->assertAlive();
        $limit = $count - ($inserting ? 0 : 1);
        if ($index < 0 || $index > $limit) {
            throw new \OutOfBoundsException('Container child index out of range: ' . $index);
        }
    }

    protected function releaseChild(int $index): void
    {
        $this->children[$index]->owner = null;
        \array_splice($this->children, $index, 1);
    }

    protected function assertOwnsChild(Control $child): void
    {
        $child->assertAlive();
        if ($child->owner?->get() !== $this) {
            throw new \LogicException('The reference control must belong to this container.');
        }
    }

    /** Destroy control trees before their models/images and native library teardown. @internal */
    public static function destroyAll(): void
    {
        foreach (self::$live === null ? [] : \iterator_to_array(self::$live) as $control) {
            if (! $control->isNativeControl() || $control->owner !== null) {
                continue;
            }
            $control->destroy();
        }
    }

    /** Native menus are owned by libui and become invalid at uiUninit(). @internal */
    public static function invalidateMenus(): void
    {
        foreach (self::$live === null ? [] : \iterator_to_array(self::$live) as $control) {
            $control->markDestroyed();
        }
    }

    /**
     * Native callback trampolines, retained for the process lifetime.
     *
     * PHP closures handed to C as function pointers are otherwise garbage-
     * collected while libui still holds the pointer — freeing the trampoline
     * mid-event-loop and crashing. Storing them statically keeps them alive
     * even after the owning widget object is gone.
     *
     * @var list<callable>
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
        $this->assertAlive();
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
        if (! $this->isNativeControl()) {
            throw new \LogicException('Menus are owned by libui and are not uiControl widgets.');
        }
        return Ffi::control($this->handle());
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
     * Drops every retained widget callback trampoline.
     *
     * Called by {@see Ffi::uninit()} once libui's loop is torn down: the native
     * pointers into these closures are dead, so holding them only leaks. A fresh
     * {@see Ffi::init()} then starts from an empty store.
     *
     * @internal
     */
    public static function clearRetainedCallbacks(): void
    {
        self::$callbacks = [];
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
        $obj->initializeHandle($handle);
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
     * Attached widgets must first be detached with the owning container API.
     * Parent destruction invalidates descendants; repeated destroy() is safe.
     */
    public function destroy(): void
    {
        if ($this->destroyed) {
            return;
        }
        $handle = $this->asControl();
        if ($this->owner !== null) {
            throw new \LogicException('Detach the control from its parent before destroying it.');
        }
        $this->markDestroyed();
        Ffi::get()->uiControlDestroy($handle);
    }

    /** Whether this control has been destroyed explicitly or by its close callback. */
    public function isDestroyed(): bool
    {
        return $this->destroyed;
    }

    /** Record destruction performed by a native lifecycle callback. */
    protected function markDestroyed(): void
    {
        $this->destroyed = true;
        foreach ($this->children as $child) {
            $child->markDestroyed();
        }
        $this->children = [];
        $this->owner = null;
        if (self::$live?->offsetExists($this)) {
            self::$live->offsetUnset($this);
        }
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
