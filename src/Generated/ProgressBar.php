<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiProgressBar`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\ProgressBar subclass instead.
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
     * @see uiProgressBarValue
     */
    public function value(): int
    {
        return \Libui\Ffi::get()->uiProgressBarValue($this->handle);
    }

    /**
     * Sets the progress bar value.
     *
     * @see uiProgressBarSetValue
     */
    public function setValue(int $n): static
    {
        \Libui\Ffi::get()->uiProgressBarSetValue($this->handle, $n);
        return $this;
    }
}
