<?php

declare(strict_types=1);

namespace Libui;

use Libui\Generated\Enum\Align;

/**
 * Grid widget. Hand-editable — add convenience methods here.
 * Inherits the generated API from Generated\\Grid.
 */
class Grid extends Generated\Grid
{
    /**
     * Friendlier placement over the generated 9-positional-arg append(): spans
     * default to a single cell, the expand flags are real bools, and alignment
     * defaults to Fill. $left is the column, $top the row.
     */
    public function appendAt(
        Control $control,
        int $left,
        int $top,
        int $xspan = 1,
        int $yspan = 1,
        bool $hexpand = false,
        Align $halign = Align::Fill,
        bool $vexpand = false,
        Align $valign = Align::Fill,
    ): static {
        return parent::append($control, $left, $top, $xspan, $yspan, (int) $hexpand, $halign, (int) $vexpand, $valign);
    }

    /** Place a control in a single, non-expanding cell at column $column, row $row. */
    public function place(Control $control, int $column, int $row): static
    {
        return $this->appendAt($control, $column, $row);
    }
}
