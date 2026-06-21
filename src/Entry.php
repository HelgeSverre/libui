<?php

declare(strict_types=1);

namespace Libui;

/**
 * Entry widget. Hand-editable — add convenience methods here.
 * Inherits the generated API from Generated\\Entry.
 */
class Entry extends Generated\Entry implements HasValue
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
