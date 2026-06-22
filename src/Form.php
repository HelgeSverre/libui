<?php

declare(strict_types=1);

namespace Libui;

/**
 * Form widget — labelled rows of controls. Hand-editable.
 * Inherits the generated API from Generated\\Form.
 */
class Form extends Generated\Form
{
    /**
     * Appended fields as an ordered list of [label, control] pairs, mirroring
     * the children libui holds. A list (not a label-keyed map) so duplicate
     * labels stay distinct and indices line up with delete(int).
     *
     * @var list<array{string, Control}>
     */
    private array $fields = [];

    /** Append a labelled field; $stretchy (bool, or the raw 0/1 int) defaults to off. */
    public function append(string $label, Control $c, bool|int $stretchy = false): static
    {
        $this->fields[] = [$label, $c];
        return parent::append($label, $c, (int) $stretchy);
    }

    /** Append a labelled field that grows to fill vertical space. */
    public function appendStretchy(string $label, Control $c): static
    {
        return $this->append($label, $c, true);
    }

    /** Remove the field at $index, keeping the tracked list in sync. */
    public function delete(int $index): static
    {
        if ($index >= 0 && $index < \count($this->fields)) {
            \array_splice($this->fields, $index, 1);
        }

        return parent::delete($index);
    }

    /**
     * Read every {@see HasValue} field as `[label => value]`. Non-value controls
     * (separators, labels, …) are skipped. With duplicate labels, the last
     * field with a given label wins (arrays cannot hold duplicate keys).
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $out = [];
        foreach ($this->fields as [$label, $control]) {
            if ($control instanceof HasValue) {
                $out[$label] = $control->value();
            }
        }

        return $out;
    }

    /**
     * Set fields from `[label => value]`. Unknown labels and non-value controls
     * are ignored, so a partial map is fine. Every field whose label matches a
     * key is updated, so duplicate-label fields all receive the value.
     *
     * @param array<string, mixed> $values
     */
    public function setValues(array $values): static
    {
        foreach ($this->fields as [$label, $control]) {
            if ($control instanceof HasValue && \array_key_exists($label, $values)) {
                $control->setValue($values[$label]);
            }
        }

        return $this;
    }
}
