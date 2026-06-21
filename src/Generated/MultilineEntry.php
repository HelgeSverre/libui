<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiMultilineEntry`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\MultilineEntry subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
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
     * @return string The containing text.
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
     * @param string $text Single/multi line text.
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
     * @param string $text Text to append.
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
     * @param callable(static): void $cb Receives this widget.
     * @note The callback is not triggered when calling uiMultilineEntrySetText() or uiMultilineEntryAppend().
     * @note Only one callback can be registered at a time.
     *
     * @see uiMultilineEntryOnChanged
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
        \Libui\Ffi::get()->uiMultilineEntryOnChanged($this->handle, $fn, null);
        return $this;
    }

    /**
     * Returns whether or not the multi line entry's text can be changed.
     *
     * @return bool `TRUE` if read only, `FALSE` otherwise. [Default: `FALSE`]
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
     * @param bool $readonly `TRUE` to make read only, `FALSE` otherwise.
     *
     * @see uiMultilineEntrySetReadOnly
     */
    public function setReadOnly(bool $readonly): static
    {
        \Libui\Ffi::get()->uiMultilineEntrySetReadOnly($this->handle, (int) $readonly);
        return $this;
    }
}
