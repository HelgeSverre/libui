<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiMenu`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Menu subclass instead.
 */
class Menu extends Control
{
    /**
     * Creates a new menu.
     *
     * @see uiNewMenu
     */
    public function __construct(string $name)
    {
        $this->handle = \Libui\Ffi::get()->uiNewMenu($name);
    }

    /**
     * Appends a generic menu item.
     *
     * @see uiMenuAppendItem
     */
    public function appendItem(string $name): \Libui\Generated\MenuItem
    {
        return \Libui\Generated\MenuItem::wrap(\Libui\Ffi::get()->uiMenuAppendItem($this->handle, $name));
    }

    /**
     * Appends a generic menu item with a checkbox.
     *
     * @see uiMenuAppendCheckItem
     */
    public function appendCheckItem(string $name): \Libui\Generated\MenuItem
    {
        return \Libui\Generated\MenuItem::wrap(\Libui\Ffi::get()->uiMenuAppendCheckItem($this->handle, $name));
    }

    /**
     * Appends a new `Quit` menu item.
     *
     * @see uiMenuAppendQuitItem
     */
    public function appendQuitItem(): \Libui\Generated\MenuItem
    {
        return \Libui\Generated\MenuItem::wrap(\Libui\Ffi::get()->uiMenuAppendQuitItem($this->handle));
    }

    /**
     * Appends a new `Preferences` menu item.
     *
     * @see uiMenuAppendPreferencesItem
     */
    public function appendPreferencesItem(): \Libui\Generated\MenuItem
    {
        return \Libui\Generated\MenuItem::wrap(\Libui\Ffi::get()->uiMenuAppendPreferencesItem($this->handle));
    }

    /**
     * Appends a new `About` menu item.
     *
     * @see uiMenuAppendAboutItem
     */
    public function appendAboutItem(): \Libui\Generated\MenuItem
    {
        return \Libui\Generated\MenuItem::wrap(\Libui\Ffi::get()->uiMenuAppendAboutItem($this->handle));
    }

    /**
     * Appends a new separator.
     *
     * @see uiMenuAppendSeparator
     */
    public function appendSeparator(): static
    {
        \Libui\Ffi::get()->uiMenuAppendSeparator($this->handle);
        return $this;
    }
}
