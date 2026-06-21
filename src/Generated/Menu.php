<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiMenu`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Menu subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
class Menu extends Control
{
    /**
     * Creates a new menu. Typical values are `File`, `Edit`, `Help`.
     *
     * @param string $name Menu label.
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
     * @param string $name Menu item text.
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
     * @param string $name Menu item text.
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
     * @warning Only one such menu item may exist per application.
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
     * @warning Only one such menu item may exist per application.
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
     * @warning Only one such menu item may exist per application.
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
