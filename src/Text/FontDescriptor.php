<?php

declare(strict_types=1);

namespace Libui\Text;

use Libui\Ffi;
use Libui\Generated\Enum\TextItalic;
use Libui\Generated\Enum\TextStretch;
use Libui\Generated\Enum\TextWeight;

/**
 * The default font for a TextLayout, wrapping the uiFontDescriptor struct
 * {Family char*, Size double, Weight, Italic, Stretch}.
 *
 * The struct holds a raw char* into a C-allocated copy of the family name; both
 * the struct and that string buffer are retained on this object so libui's
 * pointers stay valid for as long as the descriptor is in use.
 */
final class FontDescriptor
{
    private \FFI\CData $descriptor;
    /** The C char[] backing the struct's Family pointer; kept alive past addr(). */
    private \FFI\CData $familyBuffer;

    public function __construct(
        string $family,
        float $size,
        TextWeight $weight = TextWeight::Normal,
        TextItalic $italic = TextItalic::Normal,
        TextStretch $stretch = TextStretch::Normal,
    ) {
        $ffi = Ffi::get();
        $descriptor = $ffi->new('uiFontDescriptor');

        // Allocate a NUL-terminated C copy of the family name and point at it.
        $bytes = \strlen($family);
        $buffer = $ffi->new("char[" . ($bytes + 1) . "]");
        \FFI::memcpy($buffer, $family, $bytes);
        $buffer[$bytes] = "\0";

        // addr($buffer[0]) yields a plain `char *` into the buffer (no cast needed).
        $descriptor->Family = \FFI::addr($buffer[0]);
        $descriptor->Size = $size;
        $descriptor->Weight = $weight->value;
        $descriptor->Italic = $italic->value;
        $descriptor->Stretch = $stretch->value;

        $this->descriptor = $descriptor;
        $this->familyBuffer = $buffer;
    }

    public function toCData(): \FFI\CData
    {
        return $this->descriptor;
    }

    public function addr(): \FFI\CData
    {
        return \FFI::addr($this->descriptor);
    }
}
