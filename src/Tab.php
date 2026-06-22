<?php

declare(strict_types=1);

namespace Libui;

/**
 * Tab widget. Hand-editable — add convenience methods here.
 * Inherits the generated API from Generated\\Tab.
 */
class Tab extends Generated\Tab
{
    /**
     * Appends a page and marks it margined in one step.
     *
     * @param string $name Label text.
     * @param \Libui\Control $c Control to append.
     */
    public function appendMargined(string $name, \Libui\Control $c): static
    {
        $this->append($name, $c);
        $this->setMargined($this->numPages() - 1, true);
        return $this;
    }

    /**
     * Appends an ordered map of pages, keyed by their label.
     *
     * @param array<string, \Libui\Control> $named Ordered map of title => Control.
     */
    public function pages(array $named): static
    {
        foreach ($named as $name => $control) {
            $this->append($name, $control);
        }
        return $this;
    }
}
