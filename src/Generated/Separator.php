<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiSeparator`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Separator subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
class Separator extends Control
{
    /**
     * Creates a new horizontal separator to separate controls being stacked vertically.
     *
     * libui: uiNewHorizontalSeparator
     */
    public function __construct()
    {
        $this->handle = \Libui\Ffi::get()->uiNewHorizontalSeparator();
    }

    /**
     * Creates a new vertical separator to separate controls being stacked horizontally.
     *
     * libui: uiNewVerticalSeparator
     */
    public static function vertical(): static
    {
        return static::wrap(\Libui\Ffi::get()->uiNewVerticalSeparator());
    }
}
