<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiEditableCombobox`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\EditableCombobox subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
class EditableCombobox extends Control
{
    /**
     * Creates a new editable combo box.
     *
     * @see uiNewEditableCombobox
     */
    public function __construct()
    {
        $this->handle = \Libui\Ffi::get()->uiNewEditableCombobox();
    }

    /**
     * Appends an item to the editable combo box.
     *
     * @param string $text Item text.
     *
     * @see uiEditableComboboxAppend
     */
    public function append(string $text): static
    {
        \Libui\Ffi::get()->uiEditableComboboxAppend($this->handle, $text);
        return $this;
    }

    /**
     * Returns the text of the editable combo box.
     *
     * @return string The editable combo box text.
     *
     * @see uiEditableComboboxText
     */
    public function text(): string
    {
        return \Libui\Ffi::ownedString(\Libui\Ffi::get()->uiEditableComboboxText($this->handle));
    }

    /**
     * Sets the editable combo box text.
     *
     * @param string $text Text field text.
     *
     * @see uiEditableComboboxSetText
     */
    public function setText(string $text): static
    {
        \Libui\Ffi::get()->uiEditableComboboxSetText($this->handle, $text);
        return $this;
    }

    /**
     * Registers a callback for when an editable combo box item is selected or user text changed.
     *
     * @param callable(static): void $cb Receives this widget.
     * @note The callback is not triggered when calling uiEditableComboboxSetText().
     * @note Only one callback can be registered at a time.
     *
     * @see uiEditableComboboxOnChanged
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
        \Libui\Ffi::get()->uiEditableComboboxOnChanged($this->handle, $fn, null);
        return $this;
    }
}
