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
     * @see uiEntryText
     */
    public function text(): string
    {
        return \Libui\Ffi::ownedString(\Libui\Ffi::get()->uiEntryText($this->handle));
    }

    /**
     * Sets the entry's text.
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
     * @see uiEntryOnChanged
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
        \Libui\Ffi::get()->uiEntryOnChanged($this->handle, $fn, null);
        return $this;
    }

    /**
     * Returns whether or not the entry's text can be changed.
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
     * @see uiEntrySetReadOnly
     */
    public function setReadOnly(bool $readonly): static
    {
        \Libui\Ffi::get()->uiEntrySetReadOnly($this->handle, (int) $readonly);
        return $this;
    }
}
