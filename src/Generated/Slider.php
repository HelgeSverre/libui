<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiSlider`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Slider subclass instead.
 */
class Slider extends Control
{
    /**
     * Creates a new slider.
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
     * @see uiSliderValue
     */
    public function value(): int
    {
        return \Libui\Ffi::get()->uiSliderValue($this->handle);
    }

    /**
     * Sets the slider value.
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
     * @see uiSliderHasToolTip
     */
    public function hasToolTip(): bool
    {
        return \Libui\Ffi::get()->uiSliderHasToolTip($this->handle) !== 0;
    }

    /**
     * Sets whether or not the slider has a tool tip.
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
     * @see uiSliderOnChanged
     */
    public function onChanged(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) { $cb($this); });
        \Libui\Ffi::get()->uiSliderOnChanged($this->handle, $fn, null);
        return $this;
    }

    /**
     * Registers a callback for when the slider is released from dragging.
     *
     * @see uiSliderOnReleased
     */
    public function onReleased(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) { $cb($this); });
        \Libui\Ffi::get()->uiSliderOnReleased($this->handle, $fn, null);
        return $this;
    }

    /**
     * Sets the slider range.
     *
     * @see uiSliderSetRange
     */
    public function setRange(int $min, int $max): static
    {
        \Libui\Ffi::get()->uiSliderSetRange($this->handle, $min, $max);
        return $this;
    }
}
