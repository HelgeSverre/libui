<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiSeparator`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Separator subclass instead.
 */
class Separator extends Control
{
    /**
     * Creates a new horizontal separator to separate controls being stacked vertically.
     *
     * @see uiNewHorizontalSeparator
     */
    public function __construct()
    {
        $this->handle = \Libui\Ffi::get()->uiNewHorizontalSeparator();
    }

    /**
     * Creates a new vertical separator to separate controls being stacked horizontally.
     *
     * @see uiNewVerticalSeparator
     */
    public static function vertical(): static
    {
        return static::wrap(\Libui\Ffi::get()->uiNewVerticalSeparator());
    }
}
