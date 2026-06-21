<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiGrid`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Grid subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
class Grid extends Control
{
    /**
     * Creates a new grid.
     *
     * @see uiNewGrid
     */
    public function __construct()
    {
        $this->handle = \Libui\Ffi::get()->uiNewGrid();
    }

    /**
     * Appends a control to the grid.
     *
     * @param \Libui\Control $c The control to insert.
     * @param int $left Placement as number of columns from the left. Integer in range of `[INT_MIN, INT_MAX]`.
     * @param int $top Placement as number of rows from the top. Integer in range of `[INT_MIN, INT_MAX]`.
     * @param int $xspan Number of columns to span. Integer in range of `[0, INT_MAX]`.
     * @param int $yspan Number of rows to span. Integer in range of `[0, INT_MAX]`.
     * @param int $hexpand `TRUE` to expand reserved area horizontally, `FALSE` otherwise.
     * @param \Libui\Generated\Enum\Align $halign Horizontal alignment of the control within the reserved space.
     * @param int $vexpand `TRUE` to expand reserved area vertically, `FALSE` otherwise.
     * @param \Libui\Generated\Enum\Align $valign Vertical alignment of the control within the reserved space.
     *
     * @see uiGridAppend
     */
    public function append(\Libui\Control $c, int $left, int $top, int $xspan, int $yspan, int $hexpand, \Libui\Generated\Enum\Align $halign, int $vexpand, \Libui\Generated\Enum\Align $valign): static
    {
        \Libui\Ffi::get()->uiGridAppend($this->handle, \Libui\Ffi::control($c->handle()), $left, $top, $xspan, $yspan, $hexpand, $halign->value, $vexpand, $valign->value);
        return $this;
    }

    /**
     * Inserts a control positioned in relation to another control within the grid.
     *
     * @param \Libui\Control $c The control to insert.
     * @param \Libui\Control $existing The existing control to position relatively to.
     * @param \Libui\Generated\Enum\At $at Placement specifier in relation to $existing control.
     * @param int $xspan Number of columns to span. Integer in range of `[0, INT_MAX]`.
     * @param int $yspan Number of rows to span. Integer in range of `[0, INT_MAX]`.
     * @param int $hexpand `TRUE` to expand reserved area horizontally, `FALSE` otherwise.
     * @param \Libui\Generated\Enum\Align $halign Horizontal alignment of the control within the reserved space.
     * @param int $vexpand `TRUE` to expand reserved area vertically, `FALSE` otherwise.
     * @param \Libui\Generated\Enum\Align $valign Vertical alignment of the control within the reserved space.
     *
     * @see uiGridInsertAt
     */
    public function insertAt(\Libui\Control $c, \Libui\Control $existing, \Libui\Generated\Enum\At $at, int $xspan, int $yspan, int $hexpand, \Libui\Generated\Enum\Align $halign, int $vexpand, \Libui\Generated\Enum\Align $valign): static
    {
        \Libui\Ffi::get()->uiGridInsertAt($this->handle, \Libui\Ffi::control($c->handle()), \Libui\Ffi::control($existing->handle()), $at->value, $xspan, $yspan, $hexpand, $halign->value, $vexpand, $valign->value);
        return $this;
    }

    /**
     * Returns whether or not controls within the grid are padded. Padding is defined as space between individual controls.
     *
     * @return bool `TRUE` if controls are padded, `FALSE` otherwise.
     *
     * @see uiGridPadded
     */
    public function padded(): bool
    {
        return \Libui\Ffi::get()->uiGridPadded($this->handle) !== 0;
    }

    /**
     * Sets whether or not controls within the grid are padded. Padding is defined as space between individual controls. The...
     *
     * @param bool $padded `TRUE` to make controls padded, `FALSE` otherwise.
     *
     * @see uiGridSetPadded
     */
    public function setPadded(bool $padded): static
    {
        \Libui\Ffi::get()->uiGridSetPadded($this->handle, (int) $padded);
        return $this;
    }
}
