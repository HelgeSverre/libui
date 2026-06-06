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

    /** The raw `uiX *` handle for this widget. */
    public function handle(): \FFI\CData
    {
        return $this->handle;
    }

    /** This widget upcast to `uiControl *` for generic control functions. */
    public function asControl(): \FFI\CData
    {
        return Ffi::control($this->handle);
    }

    /** Retain a PHP callback so its native trampoline survives the GC. */
    protected static function keep(callable $cb): callable
    {
        self::$callbacks[] = $cb;
        return $cb;
    }

    /** Build an instance around an existing handle, bypassing the constructor. */
    protected static function wrap(\FFI\CData $handle): static
    {
        $obj = (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
        $obj->handle = $handle;
        return $obj;
    }

    // --- common uiControl verbs (inherited by every widget) ------------------

    public function show(): static
    {
        Ffi::get()->uiControlShow($this->asControl());
        return $this;
    }

    public function hide(): static
    {
        Ffi::get()->uiControlHide($this->asControl());
        return $this;
    }

    public function enable(): static
    {
        Ffi::get()->uiControlEnable($this->asControl());
        return $this;
    }

    public function disable(): static
    {
        Ffi::get()->uiControlDisable($this->asControl());
        return $this;
    }

    public function destroy(): void
    {
        Ffi::get()->uiControlDestroy($this->asControl());
    }

    public function visible(): bool
    {
        return Ffi::get()->uiControlVisible($this->asControl()) !== 0;
    }

    public function enabled(): bool
    {
        return Ffi::get()->uiControlEnabled($this->asControl()) !== 0;
    }

    public function toplevel(): bool
    {
        return Ffi::get()->uiControlToplevel($this->asControl()) !== 0;
    }
}
