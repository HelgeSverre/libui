<?php

declare(strict_types=1);

namespace Libui;

/**
 * RadioButtons widget. Hand-editable — add convenience methods here.
 * Inherits the generated API from Generated\\RadioButtons.
 */
class RadioButtons extends Generated\RadioButtons implements HasValue
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
