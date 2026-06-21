<?php

declare(strict_types=1);

namespace Libui;

/**
 * Form widget — labelled rows of controls. Hand-editable.
 * Inherits the generated API from Generated\\Form.
 */
class Form extends Generated\Form
{
    /** @var array<string, Control> Appended fields, label => control, in order. */
    private array $fields = [];

    /** Append a labelled field; $stretchy (bool, or the raw 0/1 int) defaults to off. */
    public function append(string $label, Control $c, bool|int $stretchy = false): static
    {
        $this->fields[$label] = $c;
        return parent::append($label, $c, (int) $stretchy);
    }

    /** Append a labelled field that grows to fill vertical space. */
    public function appendStretchy(string $label, Control $c): static
    {
        return $this->append($label, $c, true);
    }

    /**
     * Read every {@see HasValue} field as `[label => value]`. Non-value controls
     * (separators, labels, …) are skipped.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $out = [];
        foreach ($this->fields as $label => $control) {
            if ($control instanceof HasValue) {
                $out[$label] = $control->value();
            }
        }

        return $out;
    }

    /**
     * Set fields from `[label => value]`. Unknown labels and non-value controls
     * are ignored, so a partial map is fine.
     *
     * @param array<string, mixed> $values
     */
    public function setValues(array $values): static
    {
        foreach ($values as $label => $value) {
            $control = $this->fields[$label] ?? null;
            if ($control instanceof HasValue) {
                $control->setValue($value);
            }
        }

        return $this;
    }
}
