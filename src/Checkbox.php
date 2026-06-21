<?php

declare(strict_types=1);

namespace Libui;

/**
 * Checkbox widget. Hand-editable — add convenience methods here.
 * Inherits the generated API from Generated\\Checkbox.
 */
class Checkbox extends Generated\Checkbox implements HasValue
{
    public function value(): bool
    {
        return $this->checked();
    }

    public function setValue(mixed $value): static
    {
        return $this->setChecked((bool) $value);
    }
}
