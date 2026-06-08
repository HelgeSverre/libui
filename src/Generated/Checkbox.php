<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiCheckbox`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Checkbox subclass instead.
 */
class Checkbox extends Control
{
    /**
     * Creates a new checkbox.
     *
     * @see uiNewCheckbox
     */
    public function __construct(string $text)
    {
        $this->handle = \Libui\Ffi::get()->uiNewCheckbox($text);
    }

    /**
     * Returns the checkbox label text.
     *
     * @see uiCheckboxText
     */
    public function text(): string
    {
        return \Libui\Ffi::ownedString(\Libui\Ffi::get()->uiCheckboxText($this->handle));
    }

    /**
     * Sets the checkbox label text.
     *
     * @see uiCheckboxSetText
     */
    public function setText(string $text): static
    {
        \Libui\Ffi::get()->uiCheckboxSetText($this->handle, $text);
        return $this;
    }

    /**
     * Registers a callback for when the checkbox is toggled by the user.
     *
     * @see uiCheckboxOnToggled
     */
    public function onToggled(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) {
            try {
                $cb($this);
            } catch (\Throwable $e) {
                \fwrite(\STDERR, "[onToggled] {$e->getMessage()}\n");
            }
        });
        \Libui\Ffi::get()->uiCheckboxOnToggled($this->handle, $fn, null);
        return $this;
    }

    /**
     * Returns whether or the checkbox is checked.
     *
     * @see uiCheckboxChecked
     */
    public function checked(): bool
    {
        return \Libui\Ffi::get()->uiCheckboxChecked($this->handle) !== 0;
    }

    /**
     * Sets whether or not the checkbox is checked.
     *
     * @see uiCheckboxSetChecked
     */
    public function setChecked(bool $checked): static
    {
        \Libui\Ffi::get()->uiCheckboxSetChecked($this->handle, (int) $checked);
        return $this;
    }
}
