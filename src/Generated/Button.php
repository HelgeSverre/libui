<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiButton`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Button subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
class Button extends Control
{
    /**
     * Creates a new button.
     *
     * @param string $text Label text.
     *
     * libui: uiNewButton
     */
    public function __construct(string $text)
    {
        $this->handle = \Libui\Ffi::get()->uiNewButton($text);
    }

    /**
     * Returns the button label text.
     *
     * @return string The text of the label.
     *
     * libui: uiButtonText
     */
    public function text(): string
    {
        return \Libui\Ffi::ownedString(\Libui\Ffi::get()->uiButtonText($this->handle));
    }

    /**
     * Sets the button label text.
     *
     * @param string $text Label text.
     *
     * libui: uiButtonSetText
     */
    public function setText(string $text): static
    {
        \Libui\Ffi::get()->uiButtonSetText($this->handle, $text);
        return $this;
    }

    /**
     * Registers a callback for when the button is clicked.
     *
     * @param callable(static): void $cb Receives this widget.
     * @note Only one callback can be registered at a time.
     * @note Registering a second handler supersedes the first at the C level; the prior trampoline stays retained for the lifetime of this object.
     *
     * libui: uiButtonOnClicked
     */
    public function onClicked(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) {
            try {
                $cb($this);
            } catch (\Throwable $exception) {
                \fwrite(\STDERR, "[onClicked] {$exception->getMessage()}\n");
            }
        });
        \Libui\Ffi::get()->uiButtonOnClicked($this->handle, $fn, null);
        return $this;
    }
}
