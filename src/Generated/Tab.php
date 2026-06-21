<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiTab`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Tab subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
class Tab extends Control
{
    /**
     * Creates a new tab container.
     *
     * @see uiNewTab
     */
    public function __construct()
    {
        $this->handle = \Libui\Ffi::get()->uiNewTab();
    }

    /**
     * Returns the index of the tab selected.
     *
     * @return int Index of the tab selected
     *
     * @see uiTabSelected
     */
    public function selected(): int
    {
        return \Libui\Ffi::get()->uiTabSelected($this->handle);
    }

    /**
     * Sets the tab selected.
     *
     * @param int $index Index of the tab to be selected
     * @note The $index must be in the range [0, uiTabNumPages(t) - 1].
     *
     * @see uiTabSetSelected
     */
    public function setSelected(int $index): static
    {
        \Libui\Ffi::get()->uiTabSetSelected($this->handle, $index);
        return $this;
    }

    /**
     * Registers a callback for when a tab is selected.
     *
     * @param callable(static): void $cb Receives this widget.
     * @note The callback is not triggered when calling uiTabSetSelected(),
     * @note Only one callback can be registered at a time.
     *
     * @see uiTabOnSelected
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
        \Libui\Ffi::get()->uiTabOnSelected($this->handle, $fn, null);
        return $this;
    }

    /**
     * Appends a control in form of a page/tab with label.
     *
     * @param string $name Label text.
     * @param \Libui\Control $c Control to append.
     *
     * @see uiTabAppend
     */
    public function append(string $name, \Libui\Control $c): static
    {
        \Libui\Ffi::get()->uiTabAppend($this->handle, $name, \Libui\Ffi::control($c->handle()));
        return $this;
    }

    /**
     * Inserts a control in form of a page/tab with label at $index.
     *
     * @param string $name Label text.
     * @param int $index Index at which to insert the control.
     * @param \Libui\Control $c Control to insert.
     *
     * @see uiTabInsertAt
     */
    public function insertAt(string $name, int $index, \Libui\Control $c): static
    {
        \Libui\Ffi::get()->uiTabInsertAt($this->handle, $name, $index, \Libui\Ffi::control($c->handle()));
        return $this;
    }

    /**
     * Removes the control at $index.
     *
     * @param int $index Index of the control to be removed.
     * @note The control is neither destroyed nor freed.
     *
     * @see uiTabDelete
     */
    public function delete(int $index): static
    {
        \Libui\Ffi::get()->uiTabDelete($this->handle, $index);
        return $this;
    }

    /**
     * Returns the number of pages contained.
     *
     * @return int Number of pages.
     *
     * @see uiTabNumPages
     */
    public function numPages(): int
    {
        return \Libui\Ffi::get()->uiTabNumPages($this->handle);
    }

    /**
     * Returns whether or not the page/tab at $index has a margin.
     *
     * @param int $index Index to check if it has a margin.
     * @return bool `TRUE` if the tab has a margin, `FALSE` otherwise.
     *
     * @see uiTabMargined
     */
    public function margined(int $index): bool
    {
        return \Libui\Ffi::get()->uiTabMargined($this->handle, $index) !== 0;
    }

    /**
     * Sets whether or not the page/tab at $index has a margin.
     *
     * @param int $index Index of the tab/page to un/set margin for.
     * @param bool $margined `TRUE` to set a margin for tab at $index, `FALSE` otherwise.
     *
     * @see uiTabSetMargined
     */
    public function setMargined(int $index, bool $margined): static
    {
        \Libui\Ffi::get()->uiTabSetMargined($this->handle, $index, (int) $margined);
        return $this;
    }
}
