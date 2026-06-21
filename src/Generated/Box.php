<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiBox`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Box subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
class Box extends Control
{
    /**
     * Creates a new vertical box. Controls within the box are placed next to each other vertically.
     *
     * @see uiNewVerticalBox
     */
    public function __construct()
    {
        $this->handle = \Libui\Ffi::get()->uiNewVerticalBox();
    }

    /**
     * Creates a new horizontal box. Controls within the box are placed next to each other horizontally.
     *
     * @see uiNewHorizontalBox
     */
    public static function horizontal(): static
    {
        return static::wrap(\Libui\Ffi::get()->uiNewHorizontalBox());
    }

    /**
     * Appends a control to the box. Stretchy items expand to use the remaining space within the box. In the case of multipl...
     *
     * @param \Libui\Control $child Control instance to append.
     * @param int $stretchy `TRUE` to stretch control, `FALSE` otherwise.
     *
     * @see uiBoxAppend
     */
    public function append(\Libui\Control $child, int $stretchy): static
    {
        \Libui\Ffi::get()->uiBoxAppend($this->handle, \Libui\Ffi::control($child->handle()), $stretchy);
        return $this;
    }

    /**
     * Returns the number of controls contained within the box.
     *
     * @return int Number of children.
     *
     * @see uiBoxNumChildren
     */
    public function numChildren(): int
    {
        return \Libui\Ffi::get()->uiBoxNumChildren($this->handle);
    }

    /**
     * Removes the control at $index from the box.
     *
     * @param int $index Index of control to be removed.
     * @note The control neither destroyed nor freed.
     *
     * @see uiBoxDelete
     */
    public function delete(int $index): static
    {
        \Libui\Ffi::get()->uiBoxDelete($this->handle, $index);
        return $this;
    }

    /**
     * Returns whether or not controls within the box are padded. Padding is defined as space between individual controls.
     *
     * @return bool `TRUE` if controls are padded, `FALSE` otherwise.
     *
     * @see uiBoxPadded
     */
    public function padded(): bool
    {
        return \Libui\Ffi::get()->uiBoxPadded($this->handle) !== 0;
    }

    /**
     * Sets whether or not controls within the box are padded. Padding is defined as space between individual controls. The...
     *
     * @param bool $padded `TRUE` to make controls padded, `FALSE` otherwise.
     *
     * @see uiBoxSetPadded
     */
    public function setPadded(bool $padded): static
    {
        \Libui\Ffi::get()->uiBoxSetPadded($this->handle, (int) $padded);
        return $this;
    }
}
