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
     * @see uiNewForm
     */
    public function __construct()
    {
        $this->handle = \Libui\Ffi::get()->uiNewForm();
    }

    /**
     * Appends a control with a label to the form.
     *
     * @see uiFormAppend
     */
    public function append(string $label, \Libui\Control $c, int $stretchy): static
    {
        \Libui\Ffi::get()->uiFormAppend($this->handle, $label, \Libui\Ffi::control($c->handle()), $stretchy);
        return $this;
    }

    /**
     * Returns the number of controls contained within the form.
     *
     * @see uiFormNumChildren
     */
    public function numChildren(): int
    {
        return \Libui\Ffi::get()->uiFormNumChildren($this->handle);
    }

    /**
     * Removes the control at @p index from the form.
     *
     * @see uiFormDelete
     */
    public function delete(int $index): static
    {
        \Libui\Ffi::get()->uiFormDelete($this->handle, $index);
        return $this;
    }

    /**
     * Returns whether or not controls within the form are padded.
     *
     * @see uiFormPadded
     */
    public function padded(): bool
    {
        return \Libui\Ffi::get()->uiFormPadded($this->handle) !== 0;
    }

    /**
     * Sets whether or not controls within the box are padded.
     *
     * @see uiFormSetPadded
     */
    public function setPadded(bool $padded): static
    {
        \Libui\Ffi::get()->uiFormSetPadded($this->handle, (int) $padded);
        return $this;
    }
}
