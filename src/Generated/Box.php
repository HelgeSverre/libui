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
     * Creates a new vertical box.
     *
     * @see uiNewVerticalBox
     */
    public function __construct()
    {
        $this->handle = \Libui\Ffi::get()->uiNewVerticalBox();
    }

    /**
     * Creates a new horizontal box.
     *
     * @see uiNewHorizontalBox
     */
    public static function horizontal(): static
    {
        return static::wrap(\Libui\Ffi::get()->uiNewHorizontalBox());
    }

    /**
     * Appends a control to the box.
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
     * @see uiBoxNumChildren
     */
    public function numChildren(): int
    {
        return \Libui\Ffi::get()->uiBoxNumChildren($this->handle);
    }

    /**
     * Removes the control at @p index from the box.
     *
     * @see uiBoxDelete
     */
    public function delete(int $index): static
    {
        \Libui\Ffi::get()->uiBoxDelete($this->handle, $index);
        return $this;
    }

    /**
     * Returns whether or not controls within the box are padded.
     *
     * @see uiBoxPadded
     */
    public function padded(): bool
    {
        return \Libui\Ffi::get()->uiBoxPadded($this->handle) !== 0;
    }

    /**
     * Sets whether or not controls within the box are padded.
     *
     * @see uiBoxSetPadded
     */
    public function setPadded(bool $padded): static
    {
        \Libui\Ffi::get()->uiBoxSetPadded($this->handle, (int) $padded);
        return $this;
    }
}
