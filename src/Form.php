<?php

declare(strict_types=1);

namespace Libui;

/**
 * Form widget — labelled rows of controls. Hand-editable.
 * Inherits the generated API from Generated\\Form.
 */
class Form extends Generated\Form
{
    /** Append a labelled field; $stretchy (bool, or the raw 0/1 int) defaults to off. */
    public function append(string $label, Control $c, bool|int $stretchy = false): static
    {
        return parent::append($label, $c, (int) $stretchy);
    }

    /** Append a labelled field that grows to fill vertical space. */
    public function appendStretchy(string $label, Control $c): static
    {
        return parent::append($label, $c, 1);
    }
}
