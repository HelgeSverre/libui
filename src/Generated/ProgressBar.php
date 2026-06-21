<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiProgressBar`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\ProgressBar subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
class ProgressBar extends Control
{
    /**
     * Creates a new progress bar.
     *
     * @see uiNewProgressBar
     */
    public function __construct()
    {
        $this->handle = \Libui\Ffi::get()->uiNewProgressBar();
    }

    /**
     * Returns the progress bar value.
     *
     * @return int Progress bar value. `[Default 0]`
     *
     * @see uiProgressBarValue
     */
    public function value(): int
    {
        return \Libui\Ffi::get()->uiProgressBarValue($this->handle);
    }

    /**
     * Sets the progress bar value. Valid values are `[0, 100]` for displaying a solid bar imitating a percent value. Use a...
     *
     * @param int $n Value to set. Integer in the range of `[-1, 100]`.
     *
     * @see uiProgressBarSetValue
     */
    public function setValue(int $n): static
    {
        \Libui\Ffi::get()->uiProgressBarSetValue($this->handle, $n);
        return $this;
    }
}
