<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiCheckbox`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Checkbox subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
class Checkbox extends Control
{
    /**
     * Creates a new checkbox.
     *
     * @param string $text Label text.
     *
     * libui: uiNewCheckbox
     */
    public function __construct(string $text)
    {
        $this->handle = \Libui\Ffi::get()->uiNewCheckbox($text);
    }

    /**
     * Returns the checkbox label text.
     *
     * @return string The text of the label.
     *
     * libui: uiCheckboxText
     */
    public function text(): string
    {
        return \Libui\Ffi::ownedString(\Libui\Ffi::get()->uiCheckboxText($this->handle));
    }

    /**
     * Sets the checkbox label text.
     *
     * @param string $text Label text.
     *
     * libui: uiCheckboxSetText
     */
    public function setText(string $text): static
    {
        \Libui\Ffi::get()->uiCheckboxSetText($this->handle, $text);
        return $this;
    }

    /**
     * Registers a callback for when the checkbox is toggled by the user.
     *
     * @param callable(static): void $cb Receives this widget.
     * @note The callback is not triggered when calling uiCheckboxSetChecked().
     * @note Only one callback can be registered at a time.
     * @note Registering a second handler supersedes the first at the C level; the prior trampoline stays retained for the lifetime of this object.
     *
     * libui: uiCheckboxOnToggled
     */
    public function onToggled(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) {
            try {
                $cb($this);
            } catch (\Throwable $exception) {
                \fwrite(\STDERR, "[onToggled] {$exception->getMessage()}\n");
            }
        });
        \Libui\Ffi::get()->uiCheckboxOnToggled($this->handle, $fn, null);
        return $this;
    }

    /**
     * Returns whether or the checkbox is checked.
     *
     * @return bool `TRUE` if checked, `FALSE` otherwise.
     *
     * libui: uiCheckboxChecked
     */
    public function checked(): bool
    {
        return \Libui\Ffi::get()->uiCheckboxChecked($this->handle) !== 0;
    }

    /**
     * Sets whether or not the checkbox is checked.
     *
     * @param bool $checked `TRUE` to check box, `FALSE` otherwise.
     *
     * libui: uiCheckboxSetChecked
     */
    public function setChecked(bool $checked): static
    {
        \Libui\Ffi::get()->uiCheckboxSetChecked($this->handle, (int) $checked);
        return $this;
    }
}
