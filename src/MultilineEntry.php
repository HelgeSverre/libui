<?php

declare(strict_types=1);

namespace Libui;

/**
 * MultilineEntry widget. Hand-editable — add convenience methods here.
 * Inherits the generated API from Generated\\MultilineEntry.
 */
class MultilineEntry extends Generated\MultilineEntry implements HasValue
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
