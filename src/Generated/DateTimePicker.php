<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiDateTimePicker`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\DateTimePicker subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
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
     * @warning The `struct tm` members `tm_wday` and `tm_yday` are undefined.
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
     * @param \FFI\CData $time Date and/or time as local time.
     * @warning The `struct tm` member `tm_isdst` is ignored on windows and should be set to `-1`.
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
     * @param callable(static): void $cb Receives this widget.
     * @note The callback is not triggered when calling uiDateTimePickerSetTime().
     * @note Only one callback can be registered at a time.
     *
     * @see uiDateTimePickerOnChanged
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
        \Libui\Ffi::get()->uiDateTimePickerOnChanged($this->handle, $fn, null);
        return $this;
    }
}
