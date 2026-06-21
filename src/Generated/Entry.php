<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiEntry`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Entry subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
class Entry extends Control
{
    /**
     * Creates a new entry.
     *
     * @see uiNewEntry
     */
    public function __construct()
    {
        $this->handle = \Libui\Ffi::get()->uiNewEntry();
    }

    /**
     * Creates a new entry suitable for sensitive inputs like passwords.
     *
     * @see uiNewPasswordEntry
     */
    public static function password(): static
    {
        return static::wrap(\Libui\Ffi::get()->uiNewPasswordEntry());
    }

    /**
     * Creates a new entry suitable for search.
     *
     * @see uiNewSearchEntry
     */
    public static function search(): static
    {
        return static::wrap(\Libui\Ffi::get()->uiNewSearchEntry());
    }

    /**
     * Returns the entry's text.
     *
     * @return string The text of the entry.
     *
     * @see uiEntryText
     */
    public function text(): string
    {
        return \Libui\Ffi::ownedString(\Libui\Ffi::get()->uiEntryText($this->handle));
    }

    /**
     * Sets the entry's text.
     *
     * @param string $text Entry text.
     *
     * @see uiEntrySetText
     */
    public function setText(string $text): static
    {
        \Libui\Ffi::get()->uiEntrySetText($this->handle, $text);
        return $this;
    }

    /**
     * Registers a callback for when the user changes the entry's text.
     *
     * @param callable(static): void $cb Receives this widget.
     * @note The callback is not triggered when calling uiEntrySetText().
     *
     * @see uiEntryOnChanged
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
        \Libui\Ffi::get()->uiEntryOnChanged($this->handle, $fn, null);
        return $this;
    }

    /**
     * Returns whether or not the entry's text can be changed.
     *
     * @return bool `TRUE` if read only, `FALSE` otherwise. [Default: `FALSE`]
     *
     * @see uiEntryReadOnly
     */
    public function readOnly(): bool
    {
        return \Libui\Ffi::get()->uiEntryReadOnly($this->handle) !== 0;
    }

    /**
     * Sets whether or not the entry's text is read only.
     *
     * @param bool $readonly `TRUE` to make read only, `FALSE` otherwise.
     *
     * @see uiEntrySetReadOnly
     */
    public function setReadOnly(bool $readonly): static
    {
        \Libui\Ffi::get()->uiEntrySetReadOnly($this->handle, (int) $readonly);
        return $this;
    }
}
