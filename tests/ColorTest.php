<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Color;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Libui\Color value type — pure PHP, no FFI required.
 */
final class ColorTest extends TestCase
{
    // ========================================================================
    // CONSTRUCTION EQUIVALENCE
    // ========================================================================

    public function testHexIntFloatAnd255ConstructorsAreEquivalent(): void
    {
        $fromHexInt = Color::rgb(0x312B90);
        $from255 = Color::rgb255(49, 43, 144);
        $fromHexStr = Color::hex('#312B90');

        $this->assertSame($from255->toArray(), $fromHexInt->toArray());
        $this->assertSame($fromHexStr->toArray(), $fromHexInt->toArray());
    }

    public function testRgbaStoresNormalizedChannels(): void
    {
        $c = Color::rgba(0.25, 0.5, 0.75, 0.5);

        $this->assertSame(0.25, $c->r);
        $this->assertSame(0.5, $c->g);
        $this->assertSame(0.75, $c->b);
        $this->assertSame(0.5, $c->a);
    }

    public function testRgb255NormalizesTo01(): void
    {
        $c = Color::rgb255(255, 0, 51);

        $this->assertSame(1.0, $c->r);
        $this->assertSame(0.0, $c->g);
        $this->assertSame(51 / 255, $c->b);
    }

    public function testRgbDefaultsAlphaToOpaque(): void
    {
        $this->assertSame(1.0, Color::rgb(0x000000)->a);
    }

    public function testRgbAcceptsExplicitAlpha(): void
    {
        $this->assertSame(0.5, Color::rgb(0x000000, 0.5)->a);
    }

    // ========================================================================
    // HEX STRING PARSING
    // ========================================================================

    public function testHexParsesSixDigitForm(): void
    {
        $c = Color::hex('#FF8000');

        $this->assertSame(1.0, $c->r);
        $this->assertSame(0x80 / 255, $c->g);
        $this->assertSame(0.0, $c->b);
        $this->assertSame(1.0, $c->a);
    }

    public function testHexParsesShortThreeDigitForm(): void
    {
        // #abc expands to #aabbcc
        $this->assertSame(Color::hex('#aabbcc')->toArray(), Color::hex('#abc')->toArray());
    }

    public function testHexParsesEightDigitFormWithAlpha(): void
    {
        $c = Color::hex('#00000080');

        $this->assertSame(0.0, $c->r);
        $this->assertSame(0x80 / 255, $c->a);
    }

    public function testHexAcceptsNoLeadingHash(): void
    {
        $this->assertSame(Color::hex('#312B90')->toArray(), Color::hex('312B90')->toArray());
    }

    public function testHexIsCaseInsensitive(): void
    {
        $this->assertSame(Color::hex('#ABCDEF')->toArray(), Color::hex('#abcdef')->toArray());
    }

    // ========================================================================
    // INTEROP
    // ========================================================================

    public function testToArrayReturnsChannelsInOrder(): void
    {
        $this->assertSame([0.1, 0.2, 0.3, 0.4], Color::rgba(0.1, 0.2, 0.3, 0.4)->toArray());
    }

    public function testToHexRoundTripsHexConstruction(): void
    {
        $this->assertSame(0x312B90, Color::rgb(0x312B90)->toHex());
        $this->assertSame(0xFF8000, Color::hex('#FF8000')->toHex());
    }

    public function testToHexDropsAlpha(): void
    {
        $this->assertSame(0x010203, Color::rgb(0x010203, 0.5)->toHex());
    }

    // ========================================================================
    // DERIVATION (IMMUTABLE)
    // ========================================================================

    public function testWithAlphaReturnsNewInstanceAndLeavesOriginalUnchanged(): void
    {
        $opaque = Color::rgb(0x112233);
        $faded = $opaque->withAlpha(0.5);

        $this->assertNotSame($opaque, $faded);
        $this->assertSame(1.0, $opaque->a);
        $this->assertSame(0.5, $faded->a);
        // colour channels are preserved
        $this->assertSame($opaque->r, $faded->r);
        $this->assertSame($opaque->g, $faded->g);
        $this->assertSame($opaque->b, $faded->b);
    }

    // ========================================================================
    // CLAMPING (FORGIVING FLOATS)
    // ========================================================================

    public function testRgbaClampsOutOfRangeFloats(): void
    {
        $c = Color::rgba(2.0, -1.0, 0.5, 5.0);

        $this->assertSame(1.0, $c->r);
        $this->assertSame(0.0, $c->g);
        $this->assertSame(0.5, $c->b);
        $this->assertSame(1.0, $c->a);
    }

    public function testWithAlphaClampsOutOfRange(): void
    {
        $this->assertSame(1.0, Color::rgb(0x000000)->withAlpha(9.0)->a);
        $this->assertSame(0.0, Color::rgb(0x000000)->withAlpha(-9.0)->a);
    }

    // ========================================================================
    // VALIDATION (THROWS)
    // ========================================================================

    public function testRgb255RejectsChannelAbove255(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Color::rgb255(300, 0, 0);
    }

    public function testRgb255RejectsNegativeChannel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Color::rgb255(0, -1, 0);
    }

    public function testRgbRejectsOutOfRangeHexInt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Color::rgb(0x1000000);
    }

    public function testHexRejectsMalformedString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Color::hex('#12');
    }

    public function testHexRejectsNonHexCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Color::hex('#GGGGGG');
    }

    // ========================================================================
    // COERCION (Color::from — accepts a Color or an [r,g,b(,a)] array)
    // ========================================================================

    public function testFromColorReturnsAnEqualColor(): void
    {
        $c = Color::rgb(0x123456, 0.5);

        $this->assertSame($c->toArray(), Color::from($c)->toArray());
    }

    public function testFromThreeElementArrayDefaultsAlphaToOpaque(): void
    {
        $this->assertSame([0.1, 0.2, 0.3, 1.0], Color::from([0.1, 0.2, 0.3])->toArray());
    }

    public function testFromFourElementArrayUsesAlpha(): void
    {
        $this->assertSame([0.1, 0.2, 0.3, 0.4], Color::from([0.1, 0.2, 0.3, 0.4])->toArray());
    }

    public function testFromArrayClampsOutOfRange(): void
    {
        $this->assertSame([1.0, 0.0, 0.5, 1.0], Color::from([2.0, -1.0, 0.5])->toArray());
    }

    // ========================================================================
    // NAMED COLORS
    // ========================================================================

    public function testBlack(): void
    {
        $this->assertSame([0.0, 0.0, 0.0, 1.0], Color::black()->toArray());
    }

    public function testWhite(): void
    {
        $this->assertSame([1.0, 1.0, 1.0, 1.0], Color::white()->toArray());
    }

    public function testTransparent(): void
    {
        $this->assertSame([0.0, 0.0, 0.0, 0.0], Color::transparent()->toArray());
    }
}
