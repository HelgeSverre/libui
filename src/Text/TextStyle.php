<?php

declare(strict_types=1);

namespace Libui\Text;

use Libui\Generated\Enum\TextItalic;
use Libui\Generated\Enum\TextStretch;
use Libui\Generated\Enum\TextWeight;
use Libui\Generated\Enum\Underline;

/**
 * High-level text style that can produce both a default layout font and span
 * attributes for an AttributedString.
 */
final class TextStyle
{
    /**
     * @param array{float,float,float}|array{float,float,float,float}|null $color
     * @param array{float,float,float}|array{float,float,float,float}|null $background
     */
    public function __construct(
        public readonly ?string $family = null,
        public readonly ?float $size = null,
        public readonly ?TextWeight $weight = null,
        public readonly ?TextItalic $italic = null,
        public readonly ?TextStretch $stretch = null,
        public readonly ?array $color = null,
        public readonly ?array $background = null,
        public readonly ?Underline $underline = null,
    ) {}

    /**
     * @param array{float,float,float}|array{float,float,float,float}|null $color
     * @param array{float,float,float}|array{float,float,float,float}|null $background
     */
    public function with(
        ?string $family = null,
        ?float $size = null,
        ?TextWeight $weight = null,
        ?TextItalic $italic = null,
        ?TextStretch $stretch = null,
        ?array $color = null,
        ?array $background = null,
        ?Underline $underline = null,
    ): self {
        return new self(
            family: $family ?? $this->family,
            size: $size ?? $this->size,
            weight: $weight ?? $this->weight,
            italic: $italic ?? $this->italic,
            stretch: $stretch ?? $this->stretch,
            color: $color ?? $this->color,
            background: $background ?? $this->background,
            underline: $underline ?? $this->underline,
        );
    }

    public function font(): FontDescriptor
    {
        return new FontDescriptor(
            $this->family ?? 'Arial',
            $this->size ?? 14.0,
            $this->weight ?? TextWeight::Normal,
            $this->italic ?? TextItalic::Normal,
            $this->stretch ?? TextStretch::Normal,
        );
    }

    /** @return list<Attribute> */
    public function attributes(): array
    {
        $attributes = [];

        if ($this->family !== null) {
            $attributes[] = Attribute::family($this->family);
        }
        if ($this->size !== null) {
            $attributes[] = Attribute::size($this->size);
        }
        if ($this->weight !== null) {
            $attributes[] = Attribute::weight($this->weight);
        }
        if ($this->italic !== null) {
            $attributes[] = Attribute::italic($this->italic);
        }
        if ($this->stretch !== null) {
            $attributes[] = Attribute::stretch($this->stretch);
        }
        if ($this->color !== null) {
            $attributes[] = Attribute::color($this->color[0], $this->color[1], $this->color[2], $this->color[3] ?? 1.0);
        }
        if ($this->background !== null) {
            $attributes[] = Attribute::background($this->background[0], $this->background[1], $this->background[2], $this->background[3] ?? 1.0);
        }
        if ($this->underline !== null) {
            $attributes[] = Attribute::underline($this->underline);
        }

        return $attributes;
    }
}
