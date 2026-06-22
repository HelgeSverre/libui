<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiMenuItem`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\MenuItem subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
class MenuItem extends Control
{
    /**
     * Enables the menu item.
     *
     * libui: uiMenuItemEnable
     */
    public function enable(): static
    {
        \Libui\Ffi::get()->uiMenuItemEnable($this->handle);
        return $this;
    }

    /**
     * Disables the menu item. Menu item is grayed out and user interaction is not possible.
     *
     * libui: uiMenuItemDisable
     */
    public function disable(): static
    {
        \Libui\Ffi::get()->uiMenuItemDisable($this->handle);
        return $this;
    }

    /**
     * Registers a callback for when the menu item is clicked.
     *
     * @param callable(static, \FFI\CData): void $cb Receives this menu item and the source uiWindow handle.
     * @note Only one callback can be registered at a time.
     * @note Registering a second handler supersedes the first at the C level; the prior trampoline stays retained for the lifetime of this object.
     *
     * libui: uiMenuItemOnClicked
     */
    public function onClicked(callable $cb): static
    {
        $fn = static::keep(function ($sender, $window, $data) use ($cb) {
            try {
                $cb($this, $window);
            } catch (\Throwable $exception) {
                \fwrite(\STDERR, "[onClicked] {$exception->getMessage()}\n");
            }
        });
        \Libui\Ffi::get()->uiMenuItemOnClicked($this->handle, $fn, null);
        return $this;
    }

    /**
     * Returns whether or not the menu item's checkbox is checked. To be used only with items created via uiMenuAppendCheckI...
     *
     * @return bool `TRUE` if checked, `FALSE` otherwise.
     *
     * libui: uiMenuItemChecked
     */
    public function checked(): bool
    {
        return \Libui\Ffi::get()->uiMenuItemChecked($this->handle) !== 0;
    }

    /**
     * Sets whether or not the menu item's checkbox is checked. To be used only with items created via uiMenuAppendCheckItem().
     *
     * @param bool $checked `TRUE` to check menu item checkbox, `FALSE` otherwise.
     *
     * libui: uiMenuItemSetChecked
     */
    public function setChecked(bool $checked): static
    {
        \Libui\Ffi::get()->uiMenuItemSetChecked($this->handle, (int) $checked);
        return $this;
    }
}
