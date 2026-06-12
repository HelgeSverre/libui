<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiGroup`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Group subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
class Group extends Control
{
    /**
     * Creates a new group.
     *
     * @see uiNewGroup
     */
    public function __construct(string $title)
    {
        $this->handle = \Libui\Ffi::get()->uiNewGroup($title);
    }

    /**
     * Returns the group title.
     *
     * @see uiGroupTitle
     */
    public function title(): string
    {
        return \Libui\Ffi::ownedString(\Libui\Ffi::get()->uiGroupTitle($this->handle));
    }

    /**
     * Sets the group title.
     *
     * @see uiGroupSetTitle
     */
    public function setTitle(string $title): static
    {
        \Libui\Ffi::get()->uiGroupSetTitle($this->handle, $title);
        return $this;
    }

    /**
     * Sets the group's child.
     *
     * @see uiGroupSetChild
     */
    public function setChild(\Libui\Control $c): static
    {
        \Libui\Ffi::get()->uiGroupSetChild($this->handle, \Libui\Ffi::control($c->handle()));
        return $this;
    }

    /**
     * Returns whether or not the group has a margin.
     *
     * @see uiGroupMargined
     */
    public function margined(): bool
    {
        return \Libui\Ffi::get()->uiGroupMargined($this->handle) !== 0;
    }

    /**
     * Sets whether or not the group has a margin.
     *
     * @see uiGroupSetMargined
     */
    public function setMargined(bool $margined): static
    {
        \Libui\Ffi::get()->uiGroupSetMargined($this->handle, (int) $margined);
        return $this;
    }
}
