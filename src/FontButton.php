<?php

declare(strict_types=1);

namespace Libui;

use Libui\Text\FontDescriptor;

/**
 * FontButton widget. Hand-editable — add convenience methods here.
 * Inherits the generated API from Generated\\FontButton.
 *
 * Note: libui has no font *setter* — the font is chosen by the user through the
 * native font dialog — so this exposes only a typed getter.
 */
class FontButton extends Generated\FontButton
{
    /**
     * The currently selected font as a typed {@see FontDescriptor}, wrapping the
     * generated output-pointer getter and freeing libui's allocated copy.
     */
    public function getFont(): FontDescriptor
    {
        $ffi = Ffi::get();
        $desc = $ffi->new('uiFontDescriptor');

        $this->font(\FFI::addr($desc)); // libui fills $desc (and allocates Family)
        $font = FontDescriptor::fromCData($desc); // copies the family into a PHP buffer
        $ffi->uiFreeFontButtonFont(\FFI::addr($desc)); // free libui's Family allocation

        return $font;
    }
}
