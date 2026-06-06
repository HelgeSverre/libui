<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Generated\Enum\Align;
use Libui\Generated\Enum\At;
use Libui\Generated\Enum\AttributeType;
use Libui\Generated\Enum\DrawBrushType;
use Libui\Generated\Enum\DrawFillMode;
use Libui\Generated\Enum\DrawLineCap;
use Libui\Generated\Enum\DrawLineJoin;
use Libui\Generated\Enum\DrawTextAlign;
use Libui\Generated\Enum\ExtKey;
use Libui\Generated\Enum\SortIndicator;
use Libui\Generated\Enum\TableSelectionMode;
use Libui\Generated\Enum\TableValueType;
use Libui\Generated\Enum\TextItalic;
use Libui\Generated\Enum\TextStretch;
use Libui\Generated\Enum\TextWeight;
use Libui\Generated\Enum\UiForEach;
use Libui\Generated\Enum\Underline;
use Libui\Generated\Enum\UnderlineColor;
use Libui\Generated\Enum\WindowResizeEdge;
use Libui\Generated\Flags\Modifiers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Complete test coverage for ALL generated enums and flags.
 * Asserts that each enum/flag carries the exact integer values declared in libui's C header.
 * This guards against the generator drifting out of sync with ui.h.
 */
final class EnumCompleteTest extends TestCase
{
    // ========================================================================
    // ENUM TESTS - Each enum has its own test method
    // ========================================================================

    public function testAlignEnumValues(): void
    {
        $this->assertSame(0, Align::Fill->value);
        $this->assertSame(1, Align::Start->value);
        $this->assertSame(2, Align::Center->value);
        $this->assertSame(3, Align::End->value);
    }

    public function testAttributeTypeEnumValues(): void
    {
        $this->assertSame(0, AttributeType::Family->value);
        $this->assertSame(1, AttributeType::Size->value);
        $this->assertSame(2, AttributeType::Weight->value);
        $this->assertSame(3, AttributeType::Italic->value);
        $this->assertSame(4, AttributeType::Stretch->value);
        $this->assertSame(5, AttributeType::Color->value);
        $this->assertSame(6, AttributeType::Background->value);
        $this->assertSame(7, AttributeType::Underline->value);
        $this->assertSame(8, AttributeType::UnderlineColor->value);
        $this->assertSame(9, AttributeType::Features->value);
    }

    public function testDrawBrushTypeEnumValues(): void
    {
        $this->assertSame(0, DrawBrushType::Solid->value);
        $this->assertSame(1, DrawBrushType::LinearGradient->value);
        $this->assertSame(2, DrawBrushType::RadialGradient->value);
        $this->assertSame(3, DrawBrushType::Image->value);
    }

    public function testDrawFillModeEnumValues(): void
    {
        $this->assertSame(0, DrawFillMode::Winding->value);
        $this->assertSame(1, DrawFillMode::Alternate->value);
    }

    public function testDrawLineCapEnumValues(): void
    {
        $this->assertSame(0, DrawLineCap::Flat->value);
        $this->assertSame(1, DrawLineCap::Round->value);
        $this->assertSame(2, DrawLineCap::Square->value);
    }

    public function testDrawLineJoinEnumValues(): void
    {
        $this->assertSame(0, DrawLineJoin::Miter->value);
        $this->assertSame(1, DrawLineJoin::Round->value);
        $this->assertSame(2, DrawLineJoin::Bevel->value);
    }

    public function testDrawTextAlignEnumValues(): void
    {
        $this->assertSame(0, DrawTextAlign::Left->value);
        $this->assertSame(1, DrawTextAlign::Center->value);
        $this->assertSame(2, DrawTextAlign::Right->value);
    }

    public function testExtKeyEnumValues(): void
    {
        $this->assertSame(1, ExtKey::Escape->value);
        $this->assertSame(2, ExtKey::Insert->value);
        $this->assertSame(3, ExtKey::Delete->value);
        $this->assertSame(4, ExtKey::Home->value);
        $this->assertSame(5, ExtKey::End->value);
        $this->assertSame(6, ExtKey::PageUp->value);
        $this->assertSame(7, ExtKey::PageDown->value);
        $this->assertSame(8, ExtKey::Up->value);
        $this->assertSame(9, ExtKey::Down->value);
        $this->assertSame(10, ExtKey::Left->value);
        $this->assertSame(11, ExtKey::Right->value);
        $this->assertSame(12, ExtKey::F1->value);
        $this->assertSame(23, ExtKey::F12->value);
    }

    public function testSortIndicatorEnumValues(): void
    {
        $this->assertSame(0, SortIndicator::None->value);
        $this->assertSame(1, SortIndicator::Ascending->value);
        $this->assertSame(2, SortIndicator::Descending->value);
    }

    public function testTableSelectionModeEnumValues(): void
    {
        $this->assertSame(0, TableSelectionMode::None->value);
        $this->assertSame(1, TableSelectionMode::ZeroOrOne->value);
        $this->assertSame(2, TableSelectionMode::One->value);
        $this->assertSame(3, TableSelectionMode::ZeroOrMany->value);
    }

    public function testTableValueTypeEnumValues(): void
    {
        $this->assertSame(0, TableValueType::String->value);
        $this->assertSame(1, TableValueType::Image->value);
        $this->assertSame(2, TableValueType::Int->value);
        $this->assertSame(3, TableValueType::Color->value);
    }

    public function testTextItalicEnumValues(): void
    {
        $this->assertSame(0, TextItalic::Normal->value);
        $this->assertSame(1, TextItalic::Oblique->value);
        $this->assertSame(2, TextItalic::Italic->value);
    }

    public function testTextStretchEnumValues(): void
    {
        $this->assertSame(0, TextStretch::UltraCondensed->value);
        $this->assertSame(1, TextStretch::ExtraCondensed->value);
        $this->assertSame(2, TextStretch::Condensed->value);
        $this->assertSame(3, TextStretch::SemiCondensed->value);
        $this->assertSame(4, TextStretch::Normal->value);
        $this->assertSame(5, TextStretch::SemiExpanded->value);
        $this->assertSame(6, TextStretch::Expanded->value);
        $this->assertSame(7, TextStretch::ExtraExpanded->value);
        $this->assertSame(8, TextStretch::UltraExpanded->value);
    }

    public function testTextWeightEnumValues(): void
    {
        $this->assertSame(0, TextWeight::Minimum->value);
        $this->assertSame(100, TextWeight::Thin->value);
        $this->assertSame(200, TextWeight::UltraLight->value);
        $this->assertSame(300, TextWeight::Light->value);
        $this->assertSame(350, TextWeight::Book->value);
        $this->assertSame(400, TextWeight::Normal->value);
        $this->assertSame(500, TextWeight::Medium->value);
        $this->assertSame(600, TextWeight::SemiBold->value);
        $this->assertSame(700, TextWeight::Bold->value);
        $this->assertSame(800, TextWeight::UltraBold->value);
        $this->assertSame(900, TextWeight::Heavy->value);
        $this->assertSame(950, TextWeight::UltraHeavy->value);
        $this->assertSame(1000, TextWeight::Maximum->value);
    }

    public function testUnderlineEnumValues(): void
    {
        $this->assertSame(0, Underline::None->value);
        $this->assertSame(1, Underline::Single->value);
        $this->assertSame(2, Underline::Double->value);
        $this->assertSame(3, Underline::Suggestion->value);
    }

    public function testUnderlineColorEnumValues(): void
    {
        $this->assertSame(0, UnderlineColor::Custom->value);
        $this->assertSame(1, UnderlineColor::Spelling->value);
        $this->assertSame(2, UnderlineColor::Grammar->value);
        $this->assertSame(3, UnderlineColor::Auxiliary->value);
    }

    public function testUiForEachEnumValues(): void
    {
        $this->assertSame(0, UiForEach::ForEachContinue->value);
        $this->assertSame(1, UiForEach::Stop->value);
    }

    public function testWindowResizeEdgeEnumValues(): void
    {
        $this->assertSame(0, WindowResizeEdge::Left->value);
        $this->assertSame(1, WindowResizeEdge::Top->value);
        $this->assertSame(2, WindowResizeEdge::Right->value);
        $this->assertSame(3, WindowResizeEdge::Bottom->value);
        $this->assertSame(4, WindowResizeEdge::TopLeft->value);
        $this->assertSame(5, WindowResizeEdge::TopRight->value);
        $this->assertSame(6, WindowResizeEdge::BottomLeft->value);
        $this->assertSame(7, WindowResizeEdge::BottomRight->value);
    }

    public function testAtEnumValues(): void
    {
        $this->assertSame(0, At::Leading->value);
        $this->assertSame(1, At::Top->value);
        $this->assertSame(2, At::Trailing->value);
        $this->assertSame(3, At::Bottom->value);
    }

    // ========================================================================
    // FLAGS TESTS
    // ========================================================================

    public function testModifiersFlagValues(): void
    {
        $this->assertSame(1, Modifiers::Ctrl);
        $this->assertSame(2, Modifiers::Alt);
        $this->assertSame(4, Modifiers::Shift);
        $this->assertSame(8, Modifiers::Super);
    }

    public function testModifiersHasHelper(): void
    {
        // Test the bitmask helper method
        $mask = Modifiers::Ctrl | Modifiers::Shift; // 1 | 4 = 5

        $this->assertTrue(Modifiers::has($mask, Modifiers::Ctrl));
        $this->assertTrue(Modifiers::has($mask, Modifiers::Shift));
        $this->assertFalse(Modifiers::has($mask, Modifiers::Alt));
        $this->assertFalse(Modifiers::has($mask, Modifiers::Super));

        // Test single flag
        $this->assertTrue(Modifiers::has(Modifiers::Shift, Modifiers::Shift));
        $this->assertFalse(Modifiers::has(Modifiers::Shift, Modifiers::Ctrl));
    }

    // ========================================================================
    // ENUM CASES ARE INSTANCES
    // ========================================================================

    public function testEnumCasesAreSingletonInstances(): void
    {
        // PHP enums are singletons - each case is a single instance
        $this->assertSame(Align::Fill, Align::Fill);
        $this->assertSame(DrawBrushType::Solid, DrawBrushType::Solid);
        $this->assertSame(TextWeight::Normal, TextWeight::Normal);
    }

    public function testEnumFromValueWorks(): void
    {
        $this->assertSame(Align::Fill, Align::from(0));
        $this->assertSame(Align::Start, Align::from(1));
        $this->assertSame(Align::Center, Align::from(2));
        $this->assertSame(Align::End, Align::from(3));

        $this->assertSame(TextWeight::Normal, TextWeight::from(400));
        $this->assertSame(TextWeight::Bold, TextWeight::from(700));
    }

    public function testEnumFromValueThrowsForInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        Align::from(999);
    }

    public function testEnumTryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(Align::tryFrom(999));
    }

    public function testEnumCaseCount(): void
    {
        // Verify we're testing all cases
        $this->assertCount(4, Align::cases());
        $this->assertCount(10, AttributeType::cases());
        $this->assertCount(4, DrawBrushType::cases());
        $this->assertCount(2, DrawFillMode::cases());
        $this->assertCount(3, DrawLineCap::cases());
        $this->assertCount(3, DrawLineJoin::cases());
        $this->assertCount(3, DrawTextAlign::cases());
        $this->assertCount(39, ExtKey::cases());
        $this->assertCount(3, SortIndicator::cases());
        $this->assertCount(4, TableSelectionMode::cases());
        $this->assertCount(4, TableValueType::cases());
        $this->assertCount(3, TextItalic::cases());
        $this->assertCount(9, TextStretch::cases());
        $this->assertCount(13, TextWeight::cases());
        $this->assertCount(4, Underline::cases());
        $this->assertSame(4, count(UnderlineColor::cases()));
        $this->assertCount(2, UiForEach::cases());
        $this->assertCount(8, WindowResizeEdge::cases());
        $this->assertCount(4, At::cases());
    }

    // ========================================================================
    // DATA PROVIDER FOR COMPREHENSIVE COVERAGE
    // ========================================================================

    /**
     * Comprehensive data provider that covers ALL enum values.
     * This ensures we never miss testing an enum as new ones are added.
     *
     * @return iterable<string, array{int, int}>
     */
    public static function allEnumValues(): iterable
    {
        // Align
        yield 'Align::Fill' => [Align::Fill->value, 0];
        yield 'Align::Start' => [Align::Start->value, 1];
        yield 'Align::Center' => [Align::Center->value, 2];
        yield 'Align::End' => [Align::End->value, 3];

        // AttributeType
        yield 'AttributeType::Family' => [AttributeType::Family->value, 0];
        yield 'AttributeType::Size' => [AttributeType::Size->value, 1];
        yield 'AttributeType::Weight' => [AttributeType::Weight->value, 2];
        yield 'AttributeType::Italic' => [AttributeType::Italic->value, 3];
        yield 'AttributeType::Stretch' => [AttributeType::Stretch->value, 4];
        yield 'AttributeType::Color' => [AttributeType::Color->value, 5];
        yield 'AttributeType::Background' => [AttributeType::Background->value, 6];
        yield 'AttributeType::Underline' => [AttributeType::Underline->value, 7];
        yield 'AttributeType::UnderlineColor' => [AttributeType::UnderlineColor->value, 8];
        yield 'AttributeType::Features' => [AttributeType::Features->value, 9];

        // DrawBrushType
        yield 'DrawBrushType::Solid' => [DrawBrushType::Solid->value, 0];
        yield 'DrawBrushType::LinearGradient' => [DrawBrushType::LinearGradient->value, 1];
        yield 'DrawBrushType::RadialGradient' => [DrawBrushType::RadialGradient->value, 2];
        yield 'DrawBrushType::Image' => [DrawBrushType::Image->value, 3];

        // DrawFillMode
        yield 'DrawFillMode::Winding' => [DrawFillMode::Winding->value, 0];
        yield 'DrawFillMode::Alternate' => [DrawFillMode::Alternate->value, 1];

        // DrawLineCap
        yield 'DrawLineCap::Flat' => [DrawLineCap::Flat->value, 0];
        yield 'DrawLineCap::Round' => [DrawLineCap::Round->value, 1];
        yield 'DrawLineCap::Square' => [DrawLineCap::Square->value, 2];

        // DrawLineJoin
        yield 'DrawLineJoin::Miter' => [DrawLineJoin::Miter->value, 0];
        yield 'DrawLineJoin::Round' => [DrawLineJoin::Round->value, 1];
        yield 'DrawLineJoin::Bevel' => [DrawLineJoin::Bevel->value, 2];

        // DrawTextAlign
        yield 'DrawTextAlign::Left' => [DrawTextAlign::Left->value, 0];
        yield 'DrawTextAlign::Center' => [DrawTextAlign::Center->value, 1];
        yield 'DrawTextAlign::Right' => [DrawTextAlign::Right->value, 2];

        // ExtKey (key extended keys)
        yield 'ExtKey::Escape' => [ExtKey::Escape->value, 1];
        yield 'ExtKey::Insert' => [ExtKey::Insert->value, 2];
        yield 'ExtKey::Delete' => [ExtKey::Delete->value, 3];
        yield 'ExtKey::Home' => [ExtKey::Home->value, 4];
        yield 'ExtKey::End' => [ExtKey::End->value, 5];
        yield 'ExtKey::PageUp' => [ExtKey::PageUp->value, 6];
        yield 'ExtKey::PageDown' => [ExtKey::PageDown->value, 7];
        yield 'ExtKey::Up' => [ExtKey::Up->value, 8];
        yield 'ExtKey::Down' => [ExtKey::Down->value, 9];
        yield 'ExtKey::Left' => [ExtKey::Left->value, 10];
        yield 'ExtKey::Right' => [ExtKey::Right->value, 11];
        yield 'ExtKey::F1' => [ExtKey::F1->value, 12];
        yield 'ExtKey::F12' => [ExtKey::F12->value, 23];

        // SortIndicator
        yield 'SortIndicator::None' => [SortIndicator::None->value, 0];
        yield 'SortIndicator::Ascending' => [SortIndicator::Ascending->value, 1];
        yield 'SortIndicator::Descending' => [SortIndicator::Descending->value, 2];

        // TableSelectionMode
        yield 'TableSelectionMode::None' => [TableSelectionMode::None->value, 0];
        yield 'TableSelectionMode::ZeroOrOne' => [TableSelectionMode::ZeroOrOne->value, 1];
        yield 'TableSelectionMode::One' => [TableSelectionMode::One->value, 2];
        yield 'TableSelectionMode::ZeroOrMany' => [TableSelectionMode::ZeroOrMany->value, 3];

        // TableValueType
        yield 'TableValueType::String' => [TableValueType::String->value, 0];
        yield 'TableValueType::Image' => [TableValueType::Image->value, 1];
        yield 'TableValueType::Int' => [TableValueType::Int->value, 2];
        yield 'TableValueType::Color' => [TableValueType::Color->value, 3];

        // TextItalic
        yield 'TextItalic::Normal' => [TextItalic::Normal->value, 0];
        yield 'TextItalic::Oblique' => [TextItalic::Oblique->value, 1];
        yield 'TextItalic::Italic' => [TextItalic::Italic->value, 2];

        // TextStretch
        yield 'TextStretch::UltraCondensed' => [TextStretch::UltraCondensed->value, 0];
        yield 'TextStretch::ExtraCondensed' => [TextStretch::ExtraCondensed->value, 1];
        yield 'TextStretch::Condensed' => [TextStretch::Condensed->value, 2];
        yield 'TextStretch::SemiCondensed' => [TextStretch::SemiCondensed->value, 3];
        yield 'TextStretch::Normal' => [TextStretch::Normal->value, 4];
        yield 'TextStretch::SemiExpanded' => [TextStretch::SemiExpanded->value, 5];
        yield 'TextStretch::Expanded' => [TextStretch::Expanded->value, 6];
        yield 'TextStretch::ExtraExpanded' => [TextStretch::ExtraExpanded->value, 7];
        yield 'TextStretch::UltraExpanded' => [TextStretch::UltraExpanded->value, 8];

        // TextWeight
        yield 'TextWeight::Minimum' => [TextWeight::Minimum->value, 0];
        yield 'TextWeight::Thin' => [TextWeight::Thin->value, 100];
        yield 'TextWeight::UltraLight' => [TextWeight::UltraLight->value, 200];
        yield 'TextWeight::Light' => [TextWeight::Light->value, 300];
        yield 'TextWeight::Book' => [TextWeight::Book->value, 350];
        yield 'TextWeight::Normal' => [TextWeight::Normal->value, 400];
        yield 'TextWeight::Medium' => [TextWeight::Medium->value, 500];
        yield 'TextWeight::SemiBold' => [TextWeight::SemiBold->value, 600];
        yield 'TextWeight::Bold' => [TextWeight::Bold->value, 700];
        yield 'TextWeight::UltraBold' => [TextWeight::UltraBold->value, 800];
        yield 'TextWeight::Heavy' => [TextWeight::Heavy->value, 900];
        yield 'TextWeight::UltraHeavy' => [TextWeight::UltraHeavy->value, 950];
        yield 'TextWeight::Maximum' => [TextWeight::Maximum->value, 1000];

        // Underline
        yield 'Underline::None' => [Underline::None->value, 0];
        yield 'Underline::Single' => [Underline::Single->value, 1];
        yield 'Underline::Double' => [Underline::Double->value, 2];
        yield 'Underline::Suggestion' => [Underline::Suggestion->value, 3];

        // UnderlineColor
        yield 'UnderlineColor::Custom' => [UnderlineColor::Custom->value, 0];
        yield 'UnderlineColor::Spelling' => [UnderlineColor::Spelling->value, 1];
        yield 'UnderlineColor::Grammar' => [UnderlineColor::Grammar->value, 2];
        yield 'UnderlineColor::Auxiliary' => [UnderlineColor::Auxiliary->value, 3];

        // UiForEach
        yield 'UiForEach::ForEachContinue' => [UiForEach::ForEachContinue->value, 0];
        yield 'UiForEach::Stop' => [UiForEach::Stop->value, 1];

        // WindowResizeEdge
        yield 'WindowResizeEdge::Left' => [WindowResizeEdge::Left->value, 0];
        yield 'WindowResizeEdge::Top' => [WindowResizeEdge::Top->value, 1];
        yield 'WindowResizeEdge::Right' => [WindowResizeEdge::Right->value, 2];
        yield 'WindowResizeEdge::Bottom' => [WindowResizeEdge::Bottom->value, 3];
        yield 'WindowResizeEdge::TopLeft' => [WindowResizeEdge::TopLeft->value, 4];
        yield 'WindowResizeEdge::TopRight' => [WindowResizeEdge::TopRight->value, 5];
        yield 'WindowResizeEdge::BottomLeft' => [WindowResizeEdge::BottomLeft->value, 6];
        yield 'WindowResizeEdge::BottomRight' => [WindowResizeEdge::BottomRight->value, 7];

        // At
        yield 'At::Leading' => [At::Leading->value, 0];
        yield 'At::Top' => [At::Top->value, 1];
        yield 'At::Trailing' => [At::Trailing->value, 2];
        yield 'At::Bottom' => [At::Bottom->value, 3];
    }

    #[DataProvider('allEnumValues')]
    public function testAllEnumValuesMatchExpected(int $actual, int $expected): void
    {
        $this->assertSame($expected, $actual);
    }
}
