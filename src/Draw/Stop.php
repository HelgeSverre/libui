<?php

declare(strict_types=1);

namespace Libui\Draw;

use Libui\Color;

/**
 * A single gradient colour stop: a position along the gradient (0..1) and a
 * {@see Color}. The typed replacement for hand-built [pos, r, g, b, a] tuples
 * passed to {@see Brush::linearGradient()} / {@see Brush::radialGradient()}.
 *
 *   Brush::linearGradient(0, 0, 0, 200, [
 *       Stop::at(0.0, Color::rgb(0x312B90)),
 *       Stop::at(1.0, Color::rgb(0x0F172A)),
 *   ]);
 */
final class Stop
{
    public function __construct(
        public readonly float $pos,
        public readonly Color $color,
    ) {}

    public static function at(float $pos, Color $color): self
    {
        return new self($pos, $color);
    }

    /**
     * The stop as the [pos, r, g, b, a] tuple that {@see Brush::toCData()}
     * already consumes.
     *
     * @return array{float, float, float, float, float}
     */
    public function toArray(): array
    {
        return [$this->pos, $this->color->r, $this->color->g, $this->color->b, $this->color->a];
    }
}
