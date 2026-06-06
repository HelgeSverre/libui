<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiDateTimePicker`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\DateTimePicker subclass instead.
 */
class DateTimePicker extends Control
{
    /**
     * Creates a new date picker.
     *
     * @see uiNewDateTimePicker
     */
    public function __construct()
    {
        $this->handle = \Libui\Ffi::get()->uiNewDateTimePicker();
    }

    /**
     * Creates a new time picker.
     *
     * @see uiNewDatePicker
     */
    public static function dateOnly(): static
    {
        return static::wrap(\Libui\Ffi::get()->uiNewDatePicker());
    }

    /**
     * Creates a new date and time picker.
     *
     * @see uiNewTimePicker
     */
    public static function timeOnly(): static
    {
        return static::wrap(\Libui\Ffi::get()->uiNewTimePicker());
    }

    /**
     * Returns date and time stored in the data time picker.
     *
     * @see uiDateTimePickerTime
     */
    public function time(\FFI\CData $time): static
    {
        \Libui\Ffi::get()->uiDateTimePickerTime($this->handle, $time);
        return $this;
    }

    /**
     * Sets date and time of the data time picker.
     *
     * @see uiDateTimePickerSetTime
     */
    public function setTime(\FFI\CData $time): static
    {
        \Libui\Ffi::get()->uiDateTimePickerSetTime($this->handle, $time);
        return $this;
    }

    /**
     * Registers a callback for when the date time picker value is changed by the user.
     *
     * @see uiDateTimePickerOnChanged
     */
    public function onChanged(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) { $cb($this); });
        \Libui\Ffi::get()->uiDateTimePickerOnChanged($this->handle, $fn, null);
        return $this;
    }
}
