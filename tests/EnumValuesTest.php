<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Generated\Enum\Align;
use Libui\Generated\Enum\DrawBrushType;
use Libui\Generated\Enum\DrawFillMode;
use Libui\Generated\Enum\DrawLineCap;
use Libui\Generated\Enum\TextWeight;
use Libui\Generated\Flags\Modifiers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Assert the generated PHP enums / flag constants carry the exact integer
 * values declared in the libui C header — guarding against the generator
 * drifting out of sync with ui.h. Pure value checks; no library needed.
 */
final class EnumValuesTest extends TestCase
{
    #[DataProvider('enumValues')]
    public function testGeneratedValueMatchesHeader(int $actual, int $expected): void
    {
        $this->assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function enumValues(): iterable
    {
        yield 'TextWeight::Minimum' => [TextWeight::Minimum->value, 0];
        yield 'TextWeight::Normal' => [TextWeight::Normal->value, 400];
        yield 'TextWeight::Bold' => [TextWeight::Bold->value, 700];
        yield 'TextWeight::Maximum' => [TextWeight::Maximum->value, 1000];
        yield 'Align::Fill' => [Align::Fill->value, 0];
        yield 'DrawBrushType::Solid' => [DrawBrushType::Solid->value, 0];
        yield 'DrawBrushType::LinearGradient' => [DrawBrushType::LinearGradient->value, 1];
        yield 'DrawLineCap::Flat' => [DrawLineCap::Flat->value, 0];
        yield 'DrawFillMode::Winding' => [DrawFillMode::Winding->value, 0];
        yield 'Modifiers::Ctrl (1 << 0)' => [Modifiers::Ctrl, 1];
        yield 'Modifiers::Shift (1 << 2)' => [Modifiers::Shift, 4];
    }
}
