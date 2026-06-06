<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiSpinbox`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Spinbox subclass instead.
 */
class Spinbox extends Control
{
    /**
     * Creates a new spinbox.
     *
     * @see uiNewSpinbox
     */
    public function __construct(int $min, int $max)
    {
        $this->handle = \Libui\Ffi::get()->uiNewSpinbox($min, $max);
    }

    /**
     * Returns the spinbox value.
     *
     * @see uiSpinboxValue
     */
    public function value(): int
    {
        return \Libui\Ffi::get()->uiSpinboxValue($this->handle);
    }

    /**
     * Sets the spinbox value.
     *
     * @see uiSpinboxSetValue
     */
    public function setValue(int $value): static
    {
        \Libui\Ffi::get()->uiSpinboxSetValue($this->handle, $value);
        return $this;
    }

    /**
     * Registers a callback for when the spinbox value is changed by the user.
     *
     * @see uiSpinboxOnChanged
     */
    public function onChanged(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) { $cb($this); });
        \Libui\Ffi::get()->uiSpinboxOnChanged($this->handle, $fn, null);
        return $this;
    }
}
