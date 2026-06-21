<?php

declare(strict_types=1);

namespace Libui;

/**
 * EditableCombobox widget. Hand-editable — add convenience methods here.
 * Inherits the generated API from Generated\\EditableCombobox.
 */
class EditableCombobox extends Generated\EditableCombobox implements HasValue
{
    public function value(): string
    {
        return $this->text();
    }

    public function setValue(mixed $value): static
    {
        return $this->setText((string) $value);
    }
}
