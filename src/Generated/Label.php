<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiLabel`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Label subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
class Label extends Control
{
    /**
     * Creates a new label.
     *
     * @param string $text Label text.
     *
     * @see uiNewLabel
     */
    public function __construct(string $text)
    {
        $this->handle = \Libui\Ffi::get()->uiNewLabel($text);
    }

    /**
     * Returns the label text.
     *
     * @return string The text of the label.
     *
     * @see uiLabelText
     */
    public function text(): string
    {
        return \Libui\Ffi::ownedString(\Libui\Ffi::get()->uiLabelText($this->handle));
    }

    /**
     * Sets the label text.
     *
     * @param string $text Label text.
     *
     * @see uiLabelSetText
     */
    public function setText(string $text): static
    {
        \Libui\Ffi::get()->uiLabelSetText($this->handle, $text);
        return $this;
    }
}
