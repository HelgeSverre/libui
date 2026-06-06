<?php

declare(strict_types=1);

namespace Libui\Text;

use Libui\Ffi;
use Libui\Generated\Enum\TextItalic;
use Libui\Generated\Enum\TextWeight;
use Libui\Generated\Enum\Underline;

/**
 * A single text attribute (a family, size, weight, colour, …) built via one of
 * the static factories and applied to a range of an AttributedString.
 *
 * Each factory calls the matching uiNew*Attribute, which returns an owned
 * uiAttribute*. Once the attribute is handed to
 * AttributedString::setAttribute() the string takes ownership of it, so this
 * wrapper deliberately stays thin and does no freeing of its own.
 */
final class Attribute
{
    private function __construct(private readonly \FFI\CData $attr) {}

    public static function family(string $family): self
    {
        return new self(Ffi::get()->uiNewFamilyAttribute($family));
    }

    public static function size(float $size): self
    {
        return new self(Ffi::get()->uiNewSizeAttribute($size));
    }

    public static function weight(TextWeight $weight): self
    {
        return new self(Ffi::get()->uiNewWeightAttribute($weight->value));
    }

    public static function italic(TextItalic $italic): self
    {
        return new self(Ffi::get()->uiNewItalicAttribute($italic->value));
    }

    public static function color(float $r, float $g, float $b, float $a = 1.0): self
    {
        return new self(Ffi::get()->uiNewColorAttribute($r, $g, $b, $a));
    }

    public static function background(float $r, float $g, float $b, float $a = 1.0): self
    {
        return new self(Ffi::get()->uiNewBackgroundAttribute($r, $g, $b, $a));
    }

    public static function underline(Underline $underline = Underline::Single): self
    {
        return new self(Ffi::get()->uiNewUnderlineAttribute($underline->value));
    }

    public function handle(): \FFI\CData
    {
        return $this->attr;
    }
}
