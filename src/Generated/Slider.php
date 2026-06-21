<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiSlider`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Slider subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
class Slider extends Control
{
    /**
     * Creates a new slider.
     *
     * @param int $min Minimum value.
     * @param int $max Maximum value.
     *
     * @see uiNewSlider
     */
    public function __construct(int $min, int $max)
    {
        $this->handle = \Libui\Ffi::get()->uiNewSlider($min, $max);
    }

    /**
     * Returns the slider value.
     *
     * @return int Slider value.
     *
     * @see uiSliderValue
     */
    public function value(): int
    {
        return \Libui\Ffi::get()->uiSliderValue($this->handle);
    }

    /**
     * Sets the slider value.
     *
     * @param int $value Value to set.
     *
     * @see uiSliderSetValue
     */
    public function setValue(int $value): static
    {
        \Libui\Ffi::get()->uiSliderSetValue($this->handle, $value);
        return $this;
    }

    /**
     * Returns whether or not the slider has a tool tip.
     *
     * @return bool `TRUE` if a tool tip is present, `FALSE` otherwise. [Default `TRUE`]
     *
     * @see uiSliderHasToolTip
     */
    public function hasToolTip(): bool
    {
        return \Libui\Ffi::get()->uiSliderHasToolTip($this->handle) !== 0;
    }

    /**
     * Sets whether or not the slider has a tool tip.
     *
     * @param bool $hasToolTip `TRUE` to display a tool tip, `FALSE` to display no tool tip.
     *
     * @see uiSliderSetHasToolTip
     */
    public function setHasToolTip(bool $hasToolTip): static
    {
        \Libui\Ffi::get()->uiSliderSetHasToolTip($this->handle, (int) $hasToolTip);
        return $this;
    }

    /**
     * Registers a callback for when the slider value is changed by the user.
     *
     * @param callable(static): void $cb Receives this widget.
     * @note The callback is not triggered when calling uiSliderSetValue().
     * @note Only one callback can be registered at a time.
     *
     * @see uiSliderOnChanged
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
        \Libui\Ffi::get()->uiSliderOnChanged($this->handle, $fn, null);
        return $this;
    }

    /**
     * Registers a callback for when the slider is released from dragging.
     *
     * @param callable(static): void $cb Receives this widget.
     * @note Only one callback can be registered at a time.
     *
     * @see uiSliderOnReleased
     */
    public function onReleased(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) {
            try {
                $cb($this);
            } catch (\Throwable $exception) {
                \fwrite(\STDERR, "[onReleased] {$exception->getMessage()}\n");
            }
        });
        \Libui\Ffi::get()->uiSliderOnReleased($this->handle, $fn, null);
        return $this;
    }

    /**
     * Sets the slider range.
     *
     * @param int $min Minimum value.
     * @param int $max Maximum value.
     *
     * @see uiSliderSetRange
     */
    public function setRange(int $min, int $max): static
    {
        \Libui\Ffi::get()->uiSliderSetRange($this->handle, $min, $max);
        return $this;
    }
}
