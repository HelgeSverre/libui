<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiButton`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Button subclass instead.
 */
class Button extends Control
{
    /**
     * Creates a new button.
     *
     * @see uiNewButton
     */
    public function __construct(string $text)
    {
        $this->handle = \Libui\Ffi::get()->uiNewButton($text);
    }

    /**
     * Returns the button label text.
     *
     * @see uiButtonText
     */
    public function text(): string
    {
        return \Libui\Ffi::ownedString(\Libui\Ffi::get()->uiButtonText($this->handle));
    }

    /**
     * Sets the button label text.
     *
     * @see uiButtonSetText
     */
    public function setText(string $text): static
    {
        \Libui\Ffi::get()->uiButtonSetText($this->handle, $text);
        return $this;
    }

    /**
     * Registers a callback for when the button is clicked.
     *
     * @see uiButtonOnClicked
     */
    public function onClicked(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) { $cb($this); });
        \Libui\Ffi::get()->uiButtonOnClicked($this->handle, $fn, null);
        return $this;
    }
}
