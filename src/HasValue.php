<?php

declare(strict_types=1);

namespace Libui;

/**
 * An input widget with a single readable/writable value, for generic binding
 * (e.g. {@see Form::values()} / {@see Form::setValues()}).
 *
 * The value type varies by widget — string (Entry), int (Spinbox/Combobox),
 * bool (Checkbox), {@see Color} (ColorButton). Each widget keeps its own typed
 * accessors (text()/setText(), checked()/setChecked(), …); value()/setValue()
 * are the uniform layer on top, coercing as needed.
 */
interface HasValue
{
    public function value(): mixed;

    public function setValue(mixed $value): static;
}
