<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiMultilineEntry`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\MultilineEntry subclass instead.
 */
class MultilineEntry extends Control
{
    /**
     * Creates a new multi line entry that visually wraps text when lines overflow.
     *
     * @see uiNewMultilineEntry
     */
    public function __construct()
    {
        $this->handle = \Libui\Ffi::get()->uiNewMultilineEntry();
    }

    /**
     * Creates a new multi line entry that scrolls horizontally when lines overflow.
     *
     * @see uiNewNonWrappingMultilineEntry
     */
    public static function nonWrapping(): static
    {
        return static::wrap(\Libui\Ffi::get()->uiNewNonWrappingMultilineEntry());
    }

    /**
     * Returns the multi line entry's text.
     *
     * @see uiMultilineEntryText
     */
    public function text(): string
    {
        return \Libui\Ffi::ownedString(\Libui\Ffi::get()->uiMultilineEntryText($this->handle));
    }

    /**
     * Sets the multi line entry's text.
     *
     * @see uiMultilineEntrySetText
     */
    public function setText(string $text): static
    {
        \Libui\Ffi::get()->uiMultilineEntrySetText($this->handle, $text);
        return $this;
    }

    /**
     * Appends text to the multi line entry's text.
     *
     * @see uiMultilineEntryAppend
     */
    public function append(string $text): static
    {
        \Libui\Ffi::get()->uiMultilineEntryAppend($this->handle, $text);
        return $this;
    }

    /**
     * Registers a callback for when the user changes the multi line entry's text.
     *
     * @see uiMultilineEntryOnChanged
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
        \Libui\Ffi::get()->uiMultilineEntryOnChanged($this->handle, $fn, null);
        return $this;
    }

    /**
     * Returns whether or not the multi line entry's text can be changed.
     *
     * @see uiMultilineEntryReadOnly
     */
    public function readOnly(): bool
    {
        return \Libui\Ffi::get()->uiMultilineEntryReadOnly($this->handle) !== 0;
    }

    /**
     * Sets whether or not the multi line entry's text is read only.
     *
     * @see uiMultilineEntrySetReadOnly
     */
    public function setReadOnly(bool $readonly): static
    {
        \Libui\Ffi::get()->uiMultilineEntrySetReadOnly($this->handle, (int) $readonly);
        return $this;
    }
}
