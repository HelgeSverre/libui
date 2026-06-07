<?php

declare(strict_types=1);

namespace Libui\Text;

use Libui\Ffi;
use Libui\Generated\Enum\AttributeType;
use Libui\Generated\Enum\TextItalic;
use Libui\Generated\Enum\TextStretch;
use Libui\Generated\Enum\TextWeight;
use Libui\Generated\Enum\Underline;
use Libui\Generated\Enum\UnderlineColor;

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
    private readonly \FFI\CData $attr;
    private readonly int $start;
    private readonly int $end;

    /**
     * Create an attribute with a range.
     * The attribute type and additional parameters vary by type:
     * - Family: (AttributeType::Family, start, end, string $family)
     * - Size: (AttributeType::Size, start, end, float $size)
     * - Weight: (AttributeType::Weight, start, end, TextWeight $weight)
     * - Italic: (AttributeType::Italic, start, end, TextItalic $italic)
     * - Stretch: (AttributeType::Stretch, start, end, TextStretch $stretch)
     * - Color: (AttributeType::Color, start, end, float $r, float $g, float $b, float $a)
     * - Background: (AttributeType::Background, start, end, float $r, float $g, float $b, float $a)
     * - Underline: (AttributeType::Underline, start, end, Underline $underline)
     * - UnderlineColor: (AttributeType::UnderlineColor, start, end, UnderlineColor $color, [r, g, b, a])
     */
    public function __construct(AttributeType $type, int $start, int $end, mixed ...$params)
    {
        $ffi = Ffi::get();
        $this->start = $start;
        $this->end = $end;

        switch ($type) {
            case AttributeType::Family:
                $this->attr = $ffi->uiNewFamilyAttribute($params[0] ?? 'Arial');
                break;
            case AttributeType::Size:
                $this->attr = $ffi->uiNewSizeAttribute($params[0] ?? 14.0);
                break;
            case AttributeType::Weight:
                $this->attr = $ffi->uiNewWeightAttribute(($params[0] ?? TextWeight::Normal)->value);
                break;
            case AttributeType::Italic:
                $this->attr = $ffi->uiNewItalicAttribute(($params[0] ?? TextItalic::Normal)->value);
                break;
            case AttributeType::Stretch:
                $this->attr = $ffi->uiNewStretchAttribute(($params[0] ?? TextStretch::Normal)->value);
                break;
            case AttributeType::Color:
                $this->attr = $ffi->uiNewColorAttribute(
                    $params[0] ?? 0.0,
                    $params[1] ?? 0.0,
                    $params[2] ?? 0.0,
                    $params[3] ?? 1.0
                );
                break;
            case AttributeType::Background:
                $this->attr = $ffi->uiNewBackgroundAttribute(
                    $params[0] ?? 0.0,
                    $params[1] ?? 0.0,
                    $params[2] ?? 0.0,
                    $params[3] ?? 1.0
                );
                break;
            case AttributeType::Underline:
                $this->attr = $ffi->uiNewUnderlineAttribute(($params[0] ?? Underline::None)->value);
                break;
            case AttributeType::UnderlineColor:
                // uiNewUnderlineColorAttribute takes (uiUnderlineColor u, double r, double g, double b, double a)
                // If only the enum is provided, use black as default
                $color = ($params[0] ?? UnderlineColor::Custom)->value;
                $r = $params[1] ?? 0.0;
                $g = $params[2] ?? 0.0;
                $b = $params[3] ?? 0.0;
                $a = $params[4] ?? 1.0;
                $this->attr = $ffi->uiNewUnderlineColorAttribute($color, $r, $g, $b, $a);
                break;
            case AttributeType::Features:
            default:
                throw new \InvalidArgumentException("Unsupported attribute type: {$type->name}");
        }
    }

    public function handle(): \FFI\CData
    {
        return $this->attr;
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function getEnd(): int
    {
        return $this->end;
    }

    public function free(): void
    {
        // Attributes are freed by the AttributedString when set, nothing to do here
    }

    // Static factory methods for backwards compatibility

    public static function family(string $family): self
    {
        return new self(AttributeType::Family, 0, 0, $family);
    }

    public static function size(float $size): self
    {
        return new self(AttributeType::Size, 0, 0, $size);
    }

    public static function weight(TextWeight $weight): self
    {
        return new self(AttributeType::Weight, 0, 0, $weight);
    }

    public static function italic(TextItalic $italic): self
    {
        return new self(AttributeType::Italic, 0, 0, $italic);
    }

    public static function color(float $r, float $g, float $b, float $a = 1.0): self
    {
        return new self(AttributeType::Color, 0, 0, $r, $g, $b, $a);
    }

    /** Colour from a 0xRRGGBB integer (mirrors Brush::rgb). */
    public static function rgb(int $hex, float $a = 1.0): self
    {
        return self::color((($hex >> 16) & 0xFF) / 255, (($hex >> 8) & 0xFF) / 255, ($hex & 0xFF) / 255, $a);
    }

    public static function background(float $r, float $g, float $b, float $a = 1.0): self
    {
        return new self(AttributeType::Background, 0, 0, $r, $g, $b, $a);
    }

    public static function underline(Underline $underline = Underline::Single): self
    {
        return new self(AttributeType::Underline, 0, 0, $underline);
    }

    public static function underlineColor(UnderlineColor $color): self
    {
        return new self(AttributeType::UnderlineColor, 0, 0, $color);
    }
}
