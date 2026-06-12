<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiColorButton`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\ColorButton subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
class ColorButton extends Control
{
    /**
     * Creates a new color button.
     *
     * @see uiNewColorButton
     */
    public function __construct()
    {
        $this->handle = \Libui\Ffi::get()->uiNewColorButton();
    }

    /**
     * Returns the color button color.
     *
     * @see uiColorButtonColor
     */
    public function color(\FFI\CData $r, \FFI\CData $g, \FFI\CData $bl, \FFI\CData $a): static
    {
        \Libui\Ffi::get()->uiColorButtonColor($this->handle, \FFI::addr($r), \FFI::addr($g), \FFI::addr($bl), \FFI::addr($a));
        return $this;
    }

    /**
     * Sets the color button color.
     *
     * @see uiColorButtonSetColor
     */
    public function setColor(float $r, float $g, float $bl, float $a): static
    {
        \Libui\Ffi::get()->uiColorButtonSetColor($this->handle, $r, $g, $bl, $a);
        return $this;
    }

    /**
     * Registers a callback for when the color is changed.
     *
     * @see uiColorButtonOnChanged
     */
    public function onChanged(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) {
            try {
                $cb($this);
            } catch (\Throwable $e) {
                \fwrite(\STDERR, "[onChanged] {$e->getMessage()}\n");
            }
        });
        \Libui\Ffi::get()->uiColorButtonOnChanged($this->handle, $fn, null);
        return $this;
    }
}
