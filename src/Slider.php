<?php

declare(strict_types=1);

namespace Libui;

/**
 * Slider widget. Hand-editable — add convenience methods here.
 * Inherits the generated API from Generated\\Slider.
 */
class Slider extends Generated\Slider implements HasValue
{
    // value(): int is inherited from the generated class and satisfies HasValue.
    public function setValue(mixed $value): static
    {
        return parent::setValue((int) $value);
    }
}
