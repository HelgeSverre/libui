<?php

declare(strict_types=1);

namespace Libui\Text;

use Libui\Generated\Enum\DrawTextAlign;

/**
 * Small facade for building styled text and producing measured TextLayout
 * instances without repeating the AttributedString/FontDescriptor dance.
 */
final class RichText
{
    private AttributedString $string;

    private function __construct(
        private readonly TextStyle $defaultStyle,
    ) {
        $this->string = new AttributedString();
    }

    public static function create(?TextStyle $defaultStyle = null): self
    {
        return new self($defaultStyle ?? new TextStyle());
    }

    public function append(string $text, ?TextStyle $style = null): self
    {
        $style ??= $this->defaultStyle;
        $this->string->append($text, ...$style->attributes());
        return $this;
    }

    public function string(): AttributedString
    {
        return $this->string;
    }

    public function layout(float $width, DrawTextAlign $align = DrawTextAlign::Left): TextLayout
    {
        return new TextLayout($this->string, $this->defaultStyle->font(), $width, $align);
    }

    /** @return array{float, float} */
    public function measure(float $width, DrawTextAlign $align = DrawTextAlign::Left): array
    {
        $layout = $this->layout($width, $align);
        $extents = $layout->extents();
        $layout->free();

        return $extents;
    }

    public function height(float $width, DrawTextAlign $align = DrawTextAlign::Left): float
    {
        return $this->measure($width, $align)[1];
    }
}
