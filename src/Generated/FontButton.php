<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiFontButton`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\FontButton subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
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
     * @note Make sure to call `uiFreeFontButtonFont()` to free all allocated
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
     * @param callable(static): void $cb Receives this widget.
     * @note Only one callback can be registered at a time.
     *
     * @see uiFontButtonOnChanged
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
        \Libui\Ffi::get()->uiFontButtonOnChanged($this->handle, $fn, null);
        return $this;
    }
}
