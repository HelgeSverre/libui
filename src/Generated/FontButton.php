<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiFontButton`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\FontButton subclass instead.
 */
class FontButton extends Control
{
    /**
     * Creates a new font button.
     *
     * @see uiNewFontButton
     */
    public function __construct()
    {
        $this->handle = \Libui\Ffi::get()->uiNewFontButton();
    }

    /**
     * Returns the selected font.
     *
     * @see uiFontButtonFont
     */
    public function font(\FFI\CData $desc): static
    {
        \Libui\Ffi::get()->uiFontButtonFont($this->handle, $desc);
        return $this;
    }

    /**
     * Registers a callback for when the font is changed.
     *
     * @see uiFontButtonOnChanged
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
        \Libui\Ffi::get()->uiFontButtonOnChanged($this->handle, $fn, null);
        return $this;
    }
}
