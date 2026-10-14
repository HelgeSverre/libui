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
     * libui: uiNewVerticalBox
     */
    public function __construct()
    {
        \Libui\Ffi::assertInitialized();
        $this->initializeHandle(\Libui\Ffi::get()->uiNewVerticalBox());
    }

    /**
     * Creates a new horizontal box. Controls within the box are placed next to each other horizontally.
     *
     * libui: uiNewHorizontalBox
     */
    public static function horizontal(): static
    {
        \Libui\Ffi::assertInitialized();
        return static::wrap(\Libui\Ffi::get()->uiNewHorizontalBox());
    }

    /**
     * Appends a control to the box. Stretchy items expand to use the remaining space within the box. In the case of multipl...
     *
     * @param \Libui\Control $child Control instance to append.
     * @param int $stretchy `TRUE` to stretch control, `FALSE` otherwise.
     *
     * libui: uiBoxAppend
     */
    public function append(\Libui\Control $child, int $stretchy): static
    {
        $this->assertCanAdopt($child);
        \Libui\Ffi::get()->uiBoxAppend($this->handle(), \Libui\Ffi::control($child->handle()), $stretchy);
        $this->adoptChild($child);
        return $this;
    }

    /**
     * Returns the number of controls contained within the box.
     *
     * @return int Number of children.
     *
     * libui: uiBoxNumChildren
     */
    public function numChildren(): int
    {
        return \Libui\Ffi::get()->uiBoxNumChildren($this->handle());
    }

    /**
     * Removes the control at $index from the box.
     *
     * @param int $index Index of control to be removed.
     * @note The control is neither destroyed nor freed.
     *
     * libui: uiBoxDelete
     */
    public function delete(int $index): static
    {
        $this->assertChildIndex($index);
        \Libui\Ffi::get()->uiBoxDelete($this->handle(), $index);
        $this->releaseChild($index);
        return $this;
    }

    /**
     * Returns whether or not controls within the box are padded. Padding is defined as space between individual controls.
     *
     * @return bool `TRUE` if controls are padded, `FALSE` otherwise.
     *
     * libui: uiBoxPadded
     */
    public function padded(): bool
    {
        return \Libui\Ffi::get()->uiBoxPadded($this->handle()) !== 0;
    }

    /**
     * Sets whether or not controls within the box are padded. Padding is defined as space between individual controls. The...
     *
     * @param bool $padded `TRUE` to make controls padded, `FALSE` otherwise.
     *
     * libui: uiBoxSetPadded
     */
    public function setPadded(bool $padded): static
    {
        \Libui\Ffi::get()->uiBoxSetPadded($this->handle(), (int) $padded);
        return $this;
    }
}
