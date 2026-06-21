<?php

declare(strict_types=1);

namespace Libui;

/**
 * Combobox widget. Hand-editable — add convenience methods here.
 * Inherits the generated API from Generated\\Combobox.
 */
class Combobox extends Generated\Combobox implements HasValue
{
    public function value(): int
    {
        return $this->selected();
    }

    public function setValue(mixed $value): static
    {
        return $this->setSelected((int) $value);
    }
}
