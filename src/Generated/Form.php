<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiForm`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Form subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
class Form extends Control
{
    /**
     * Creates a new form.
     *
     * libui: uiNewForm
     */
    public function __construct()
    {
        $this->handle = \Libui\Ffi::get()->uiNewForm();
    }

    /**
     * Appends a control with a label to the form. Stretchy items expand to use the remaining space within the container. In...
     *
     * @param string $label Label text.
     * @param \Libui\Control $c Control to append.
     * @param int $stretchy `TRUE` to stretch control, `FALSE` otherwise.
     *
     * libui: uiFormAppend
     */
    public function append(string $label, \Libui\Control $c, int $stretchy): static
    {
        \Libui\Ffi::get()->uiFormAppend($this->handle, $label, \Libui\Ffi::control($c->handle()), $stretchy);
        return $this;
    }

    /**
     * Returns the number of controls contained within the form.
     *
     * libui: uiFormNumChildren
     */
    public function numChildren(): int
    {
        return \Libui\Ffi::get()->uiFormNumChildren($this->handle);
    }

    /**
     * Removes the control at $index from the form.
     *
     * @param int $index Index of the control to be removed.
     * @note The control is neither destroyed nor freed.
     *
     * libui: uiFormDelete
     */
    public function delete(int $index): static
    {
        \Libui\Ffi::get()->uiFormDelete($this->handle, $index);
        return $this;
    }

    /**
     * Returns whether or not controls within the form are padded. Padding is defined as space between individual controls.
     *
     * @return bool `TRUE` if controls are padded, `FALSE` otherwise.
     *
     * libui: uiFormPadded
     */
    public function padded(): bool
    {
        return \Libui\Ffi::get()->uiFormPadded($this->handle) !== 0;
    }

    /**
     * Sets whether or not controls within the box are padded. Padding is defined as space between individual controls. The...
     *
     * @param bool $padded `TRUE` to make controls padded, `FALSE` otherwise.
     *
     * libui: uiFormSetPadded
     */
    public function setPadded(bool $padded): static
    {
        \Libui\Ffi::get()->uiFormSetPadded($this->handle, (int) $padded);
        return $this;
    }
}
