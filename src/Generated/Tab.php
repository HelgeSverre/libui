<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiTab`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Tab subclass instead.
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
     * @see uiTabSelected
     */
    public function selected(): int
    {
        return \Libui\Ffi::get()->uiTabSelected($this->handle);
    }

    /**
     * Sets the tab selected.
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
     * @see uiTabOnSelected
     */
    public function onSelected(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) {
            try {
                $cb($this);
            } catch (\Throwable $e) {
                \fwrite(\STDERR, "[onSelected] {$e->getMessage()}\n");
            }
        });
        \Libui\Ffi::get()->uiTabOnSelected($this->handle, $fn, null);
        return $this;
    }

    /**
     * Appends a control in form of a page/tab with label.
     *
     * @see uiTabAppend
     */
    public function append(string $name, \Libui\Control $c): static
    {
        \Libui\Ffi::get()->uiTabAppend($this->handle, $name, \Libui\Ffi::control($c->handle()));
        return $this;
    }

    /**
     * Inserts a control in form of a page/tab with label at @p index.
     *
     * @see uiTabInsertAt
     */
    public function insertAt(string $name, int $index, \Libui\Control $c): static
    {
        \Libui\Ffi::get()->uiTabInsertAt($this->handle, $name, $index, \Libui\Ffi::control($c->handle()));
        return $this;
    }

    /**
     * Removes the control at @p index.
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
     * @see uiTabNumPages
     */
    public function numPages(): int
    {
        return \Libui\Ffi::get()->uiTabNumPages($this->handle);
    }

    /**
     * Returns whether or not the page/tab at @p index has a margin.
     *
     * @see uiTabMargined
     */
    public function margined(int $index): int
    {
        return \Libui\Ffi::get()->uiTabMargined($this->handle, $index);
    }

    /**
     * Sets whether or not the page/tab at @p index has a margin.
     *
     * @see uiTabSetMargined
     */
    public function setMargined(int $index, int $margined): static
    {
        \Libui\Ffi::get()->uiTabSetMargined($this->handle, $index, $margined);
        return $this;
    }
}
