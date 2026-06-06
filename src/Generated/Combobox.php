<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiCombobox`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Combobox subclass instead.
 */
class Combobox extends Control
{
    /**
     * Creates a new combo box.
     *
     * @see uiNewCombobox
     */
    public function __construct()
    {
        $this->handle = \Libui\Ffi::get()->uiNewCombobox();
    }

    /**
     * Appends an item to the combo box.
     *
     * @see uiComboboxAppend
     */
    public function append(string $text): static
    {
        \Libui\Ffi::get()->uiComboboxAppend($this->handle, $text);
        return $this;
    }

    /**
     * Inserts an item at @p index to the combo box.
     *
     * @see uiComboboxInsertAt
     */
    public function insertAt(int $index, string $text): static
    {
        \Libui\Ffi::get()->uiComboboxInsertAt($this->handle, $index, $text);
        return $this;
    }

    /**
     * Deletes an item at @p index from the combo box.
     *
     * @see uiComboboxDelete
     */
    public function delete(int $index): static
    {
        \Libui\Ffi::get()->uiComboboxDelete($this->handle, $index);
        return $this;
    }

    /**
     * Deletes all items from the combo box.
     *
     * @see uiComboboxClear
     */
    public function clear(): static
    {
        \Libui\Ffi::get()->uiComboboxClear($this->handle);
        return $this;
    }

    /**
     * Returns the number of items contained within the combo box.
     *
     * @see uiComboboxNumItems
     */
    public function numItems(): int
    {
        return \Libui\Ffi::get()->uiComboboxNumItems($this->handle);
    }

    /**
     * Returns the index of the item selected.
     *
     * @see uiComboboxSelected
     */
    public function selected(): int
    {
        return \Libui\Ffi::get()->uiComboboxSelected($this->handle);
    }

    /**
     * Sets the item selected.
     *
     * @see uiComboboxSetSelected
     */
    public function setSelected(int $index): static
    {
        \Libui\Ffi::get()->uiComboboxSetSelected($this->handle, $index);
        return $this;
    }

    /**
     * Registers a callback for when a combo box item is selected.
     *
     * @see uiComboboxOnSelected
     */
    public function onSelected(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) { $cb($this); });
        \Libui\Ffi::get()->uiComboboxOnSelected($this->handle, $fn, null);
        return $this;
    }
}
