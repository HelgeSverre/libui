<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiRadioButtons`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\RadioButtons subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
class RadioButtons extends Control
{
    /**
     * Creates a new radio buttons instance.
     *
     * @see uiNewRadioButtons
     */
    public function __construct()
    {
        $this->handle = \Libui\Ffi::get()->uiNewRadioButtons();
    }

    /**
     * Appends a radio button.
     *
     * @param string $text Radio button text.
     *
     * @see uiRadioButtonsAppend
     */
    public function append(string $text): static
    {
        \Libui\Ffi::get()->uiRadioButtonsAppend($this->handle, $text);
        return $this;
    }

    /**
     * Returns the index of the item selected.
     *
     * @return int Index of the item selected, `-1` on empty selection.
     *
     * @see uiRadioButtonsSelected
     */
    public function selected(): int
    {
        return \Libui\Ffi::get()->uiRadioButtonsSelected($this->handle);
    }

    /**
     * Sets the item selected.
     *
     * @param int $index Index of the item to be selected, `-1` to clear selection.
     *
     * @see uiRadioButtonsSetSelected
     */
    public function setSelected(int $index): static
    {
        \Libui\Ffi::get()->uiRadioButtonsSetSelected($this->handle, $index);
        return $this;
    }

    /**
     * Registers a callback for when radio button is selected.
     *
     * @param callable(static): void $cb Receives this widget.
     * @note The callback is not triggered when calling uiRadioButtonsSetSelected().
     * @note Only one callback can be registered at a time.
     *
     * @see uiRadioButtonsOnSelected
     */
    public function onSelected(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) {
            try {
                $cb($this);
            } catch (\Throwable $exception) {
                \fwrite(\STDERR, "[onSelected] {$exception->getMessage()}\n");
            }
        });
        \Libui\Ffi::get()->uiRadioButtonsOnSelected($this->handle, $fn, null);
        return $this;
    }
}
