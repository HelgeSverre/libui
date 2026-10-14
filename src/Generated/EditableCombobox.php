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
     * libui: uiNewEditableCombobox
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
     * libui: uiEditableComboboxAppend
     */
    public function append(string $text): static
    {
        \Libui\Ffi::get()->uiEditableComboboxAppend($this->handle, $text);
        return $this;
    }

    /**
     * Returns the text of the editable combo box. This text is either the text of one of the predefined list items or the t...
     *
     * @return string The editable combo box text.
     *
     * libui: uiEditableComboboxText
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
     * libui: uiEditableComboboxSetText
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
     * @note Registering a second handler supersedes the first at the C level; the prior trampoline stays retained for the lifetime of this object.
     *
     * libui: uiEditableComboboxOnChanged
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

    /**
     * Returns the editable combo box's placeholder.
     *
     * @return string The placeholder text of the combo box.
     *
     * libui: uiEditableComboboxPlaceholder
     */
    public function placeholder(): string
    {
        return \Libui\Ffi::ownedString(\Libui\Ffi::get()->uiEditableComboboxPlaceholder($this->handle));
    }

    /**
     * Sets text to be displayed in the editable combo box when it is empty.
     *
     * @param string $text Placeholder text.
     *
     * libui: uiEditableComboboxSetPlaceholder
     */
    public function setPlaceholder(string $text): static
    {
        \Libui\Ffi::get()->uiEditableComboboxSetPlaceholder($this->handle, $text);
        return $this;
    }
}
