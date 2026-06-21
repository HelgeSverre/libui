<?php

declare(strict_types=1);

namespace Libui;

/**
 * ColorButton widget. Hand-editable — add convenience methods here.
 * Inherits the generated API from Generated\\ColorButton.
 */
class ColorButton extends Generated\ColorButton
{
    /**
     * Set the button colour from a {@see Color}, or from raw 0..1 float channels
     * (the generated signature still works).
     */
    public function setColor(Color|float $r, float $g = 0.0, float $b = 0.0, float $a = 1.0): static
    {
        if ($r instanceof Color) {
            return parent::setColor($r->r, $r->g, $r->b, $r->a);
        }

        return parent::setColor($r, $g, $b, $a);
    }

    /**
     * The currently selected colour as a {@see Color}, wrapping the generated
     * output-pointer getter.
     */
    public function getColor(): Color
    {
        $ffi = Ffi::get();
        $r = $ffi->new('double');
        $g = $ffi->new('double');
        $b = $ffi->new('double');
        $a = $ffi->new('double');

        $this->color($r, $g, $b, $a);

        return Color::rgba($r->cdata, $g->cdata, $b->cdata, $a->cdata);
    }
}
