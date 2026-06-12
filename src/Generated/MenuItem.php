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
     * @see uiMenuItemEnable
     */
    public function enable(): static
    {
        \Libui\Ffi::get()->uiMenuItemEnable($this->handle);
        return $this;
    }

    /**
     * Disables the menu item.
     *
     * @see uiMenuItemDisable
     */
    public function disable(): static
    {
        \Libui\Ffi::get()->uiMenuItemDisable($this->handle);
        return $this;
    }

    /**
     * Registers a callback for when the menu item is clicked.
     *
     * @see uiMenuItemOnClicked
     */
    public function onClicked(callable $cb): static
    {
        $fn = static::keep(function ($sender, $window, $data) use ($cb) {
            try {
                $cb($this, $window);
            } catch (\Throwable $e) {
                \fwrite(\STDERR, "[onClicked] {$e->getMessage()}\n");
            }
        });
        \Libui\Ffi::get()->uiMenuItemOnClicked($this->handle, $fn, null);
        return $this;
    }

    /**
     * Returns whether or not the menu item's checkbox is checked.
     *
     * @see uiMenuItemChecked
     */
    public function checked(): bool
    {
        return \Libui\Ffi::get()->uiMenuItemChecked($this->handle) !== 0;
    }

    /**
     * Sets whether or not the menu item's checkbox is checked.
     *
     * @see uiMenuItemSetChecked
     */
    public function setChecked(bool $checked): static
    {
        \Libui\Ffi::get()->uiMenuItemSetChecked($this->handle, (int) $checked);
        return $this;
    }
}
