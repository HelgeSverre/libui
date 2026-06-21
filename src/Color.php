<?php

declare(strict_types=1);

namespace Libui;

/**
 * An immutable RGBA colour, stored as normalized 0..1 channels (libui-native).
 *
 * The one typed way to express a colour across the binding — drawing brushes,
 * text attributes, colour buttons, table backgrounds. Construct it the way you
 * already think about colour (hex int, hex string, 0..1 floats, 8-bit ints, or a
 * named constant) and hand it to any colour-consuming API.
 *
 *   Color::rgb(0x312B90)            // hex int
 *   Color::rgba(0.19, 0.17, 0.56)   // 0..1 floats
 *   Color::rgb255(49, 43, 144)      // 8-bit ints
 *   Color::hex('#312B90')           // #RGB / #RRGGBB / #RRGGBBAA
 *   Color::black();                 // named
 *
 * Float inputs are clamped to 0..1 (forgiving); out-of-range hex/8-bit values
 * throw, since those are almost always a typo rather than rounding drift.
 */
final class Color
{
    private function __construct(
        public readonly float $r,
        public readonly float $g,
        public readonly float $b,
        public readonly float $a,
    ) {}

    /** Colour from 0..1 float channels. Out-of-range values are clamped. */
    public static function rgba(float $r, float $g, float $b, float $a = 1.0): self
    {
        return new self(self::clamp($r), self::clamp($g), self::clamp($b), self::clamp($a));
    }

    /** Colour from a `0xRRGGBB` integer, with optional 0..1 alpha. */
    public static function rgb(int $hex, float $a = 1.0): self
    {
        if ($hex < 0 || $hex > 0xFFFFFF) {
            throw new \InvalidArgumentException(\sprintf('Color::rgb() expects 0x000000..0xFFFFFF, got 0x%X', $hex));
        }

        return self::rgb255(($hex >> 16) & 0xFF, ($hex >> 8) & 0xFF, $hex & 0xFF, $a);
    }

    /** Colour from 8-bit (0-255) channels, with optional 0..1 alpha. */
    public static function rgb255(int $r, int $g, int $b, float $a = 1.0): self
    {
        foreach (['r' => $r, 'g' => $g, 'b' => $b] as $name => $value) {
            if ($value < 0 || $value > 255) {
                throw new \InvalidArgumentException("Color::rgb255() channel {$name} out of range (0-255): {$value}");
            }
        }

        return new self($r / 255, $g / 255, $b / 255, self::clamp($a));
    }

    /** Colour from a `#RGB`, `#RRGGBB`, or `#RRGGBBAA` string (leading `#` optional). */
    public static function hex(string $hex): self
    {
        $digits = ltrim($hex, '#');

        if (preg_match('/^[0-9A-Fa-f]+$/', $digits) !== 1 || ! \in_array(\strlen($digits), [3, 6, 8], true)) {
            throw new \InvalidArgumentException("Color::hex() expects #RGB, #RRGGBB, or #RRGGBBAA, got: {$hex}");
        }

        if (\strlen($digits) === 3) {
            $digits = $digits[0] . $digits[0] . $digits[1] . $digits[1] . $digits[2] . $digits[2];
        }

        $r = (int) hexdec(substr($digits, 0, 2));
        $g = (int) hexdec(substr($digits, 2, 2));
        $b = (int) hexdec(substr($digits, 4, 2));
        $a = \strlen($digits) === 8 ? (int) hexdec(substr($digits, 6, 2)) / 255 : 1.0;

        return new self($r / 255, $g / 255, $b / 255, $a);
    }

    /**
     * Coerce a Color or an `[r, g, b]` / `[r, g, b, a]` float array into a Color.
     *
     * Lets colour-consuming APIs accept either form; arrays default to opaque.
     *
     * @param self|array{float,float,float}|array{float,float,float,float} $color
     */
    public static function from(self|array $color): self
    {
        if ($color instanceof self) {
            return $color;
        }

        return self::rgba($color[0], $color[1], $color[2], $color[3] ?? 1.0);
    }

    public static function black(): self
    {
        return new self(0.0, 0.0, 0.0, 1.0);
    }

    public static function white(): self
    {
        return new self(1.0, 1.0, 1.0, 1.0);
    }

    public static function transparent(): self
    {
        return new self(0.0, 0.0, 0.0, 0.0);
    }

    /** A copy with a different alpha (0..1, clamped). */
    public function withAlpha(float $a): self
    {
        return new self($this->r, $this->g, $this->b, self::clamp($a));
    }

    /**
     * The channels as a `[r, g, b, a]` float array, for the float-array APIs.
     *
     * @return array{float, float, float, float}
     */
    public function toArray(): array
    {
        return [$this->r, $this->g, $this->b, $this->a];
    }

    /** The colour as a `0xRRGGBB` integer (alpha dropped). */
    public function toHex(): int
    {
        return (self::to255($this->r) << 16) | (self::to255($this->g) << 8) | self::to255($this->b);
    }

    private static function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    private static function to255(float $value): int
    {
        return (int) round($value * 255);
    }
}
