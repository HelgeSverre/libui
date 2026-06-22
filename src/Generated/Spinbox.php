<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiSpinbox`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Spinbox subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
class Spinbox extends Control
{
    /**
     * Creates a new spinbox. The initial spinbox value equals the minimum value. In the current implementation $min and $ma...
     *
     * @param int $min Minimum value.
     * @param int $max Maximum value.
     *
     * libui: uiNewSpinbox
     */
    public function __construct(int $min, int $max)
    {
        $this->handle = \Libui\Ffi::get()->uiNewSpinbox($min, $max);
    }

    /**
     * Returns the spinbox value.
     *
     * @return int Spinbox value.
     *
     * libui: uiSpinboxValue
     */
    public function value(): int
    {
        return \Libui\Ffi::get()->uiSpinboxValue($this->handle);
    }

    /**
     * Sets the spinbox value.
     *
     * @param int $value Value to set.
     * @note Setting a value out of range will clamp to the nearest value in range.
     *
     * libui: uiSpinboxSetValue
     */
    public function setValue(int $value): static
    {
        \Libui\Ffi::get()->uiSpinboxSetValue($this->handle, $value);
        return $this;
    }

    /**
     * Registers a callback for when the spinbox value is changed by the user.
     *
     * @param callable(static): void $cb Receives this widget.
     * @note The callback is not triggered when calling uiSpinboxSetValue().
     * @note Only one callback can be registered at a time.
     * @note Registering a second handler supersedes the first at the C level; the prior trampoline stays retained for the lifetime of this object.
     *
     * libui: uiSpinboxOnChanged
     */
    public function onChanged(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) {
            try {
                $cb($this);
            } catch (\Throwable $exception) {
                \fwrite(\STDERR, "[onChanged] {$exception->getMessage()}\n");
            }
        });
        \Libui\Ffi::get()->uiSpinboxOnChanged($this->handle, $fn, null);
        return $this;
    }
}
