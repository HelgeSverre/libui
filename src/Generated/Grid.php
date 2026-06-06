<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiGrid`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Grid subclass instead.
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
     * @see uiGridInsertAt
     */
    public function insertAt(\Libui\Control $c, \Libui\Control $existing, \Libui\Generated\Enum\At $at, int $xspan, int $yspan, int $hexpand, \Libui\Generated\Enum\Align $halign, int $vexpand, \Libui\Generated\Enum\Align $valign): static
    {
        \Libui\Ffi::get()->uiGridInsertAt($this->handle, \Libui\Ffi::control($c->handle()), \Libui\Ffi::control($existing->handle()), $at->value, $xspan, $yspan, $hexpand, $halign->value, $vexpand, $valign->value);
        return $this;
    }

    /**
     * Returns whether or not controls within the grid are padded.
     *
     * @see uiGridPadded
     */
    public function padded(): bool
    {
        return \Libui\Ffi::get()->uiGridPadded($this->handle) !== 0;
    }

    /**
     * Sets whether or not controls within the grid are padded.
     *
     * @see uiGridSetPadded
     */
    public function setPadded(bool $padded): static
    {
        \Libui\Ffi::get()->uiGridSetPadded($this->handle, (int) $padded);
        return $this;
    }
}
