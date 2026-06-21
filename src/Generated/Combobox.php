<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiCombobox`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Combobox subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
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
     * @param string $text Item text.
     *
     * @see uiComboboxAppend
     */
    public function append(string $text): static
    {
        \Libui\Ffi::get()->uiComboboxAppend($this->handle, $text);
        return $this;
    }

    /**
     * Inserts an item at $index to the combo box.
     *
     * @param int $index Index at which to insert the item.
     * @param string $text Item text.
     *
     * @see uiComboboxInsertAt
     */
    public function insertAt(int $index, string $text): static
    {
        \Libui\Ffi::get()->uiComboboxInsertAt($this->handle, $index, $text);
        return $this;
    }

    /**
     * Deletes an item at $index from the combo box.
     *
     * @param int $index Index of the item to be deleted.
     * @note Deleting the index of the item currently selected will move the selection to the next item in the combo box or `-1` if no such item exists.
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
     * @return int Number of items.
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
     * @return int Index of the item selected, `-1` on empty selection. [Default `-1`]
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
     * @param int $index Index of the item to be selected, `-1` to clear selection.
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
     * @param callable(static): void $cb Receives this widget.
     * @note The callback is not triggered when calling uiComboboxSetSelected(), uiComboboxInsertAt(), uiComboboxDelete(), or uiComboboxClear().
     * @note Only one callback can be registered at a time.
     *
     * @see uiComboboxOnSelected
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
        \Libui\Ffi::get()->uiComboboxOnSelected($this->handle, $fn, null);
        return $this;
    }
}
