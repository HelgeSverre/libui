<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Color;
use Libui\Generated\Enum\AttributeType;
use Libui\Generated\Enum\TextItalic;
use Libui\Generated\Enum\TextStretch;
use Libui\Generated\Enum\TextWeight;
use Libui\Generated\Enum\Underline;
use Libui\Generated\Enum\UnderlineColor;
use Libui\Text\Attribute;
use Libui\Text\AttributedString;
use Libui\Text\FontDescriptor;
use Libui\Text\RichText;
use Libui\Text\TextLayout;
use Libui\Text\TextStyle;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the text subsystem (AttributedString, Attribute, FontDescriptor, TextLayout).
 * These classes are used for rich text rendering with custom attributes.
 */
final class TextTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        \Libui\Ffi::init();
    }

    // ========================================================================
    // ATTRIBUTED STRING TESTS
    // ========================================================================

    public function testAttributedStringConstructsEmpty(): void
    {
        $str = new AttributedString('');
        $this->assertInstanceOf(AttributedString::class, $str);
    }

    public function testAttributedStringConstructsWithText(): void
    {
        $str = new AttributedString('Hello World');
        $this->assertInstanceOf(AttributedString::class, $str);
    }

    public function testAttributedStringHandle(): void
    {
        $str = new AttributedString('Test');
        $handle = $str->handle();

        $this->assertInstanceOf(\FFI\CData::class, $handle);
        $this->assertFalse(\FFI::isNull($handle));
    }

    public function testAttributedStringLength(): void
    {
        $str = new AttributedString('Hello');
        $this->assertSame(5, $str->length());
    }

    public function testAttributedStringLengthEmpty(): void
    {
        $str = new AttributedString('');
        $this->assertSame(0, $str->length());
    }

    public function testAttributedStringAppend(): void
    {
        $str = new AttributedString('Hello');
        $str->append(' World');

        $this->assertSame(11, $str->length());
    }

    public function testAttributedStringInsert(): void
    {
        $str = new AttributedString('Hello World');
        $str->insert(' Beautiful ', 6);

        $this->assertSame(22, $str->length());
    }

    public function testAttributedStringDelete(): void
    {
        $str = new AttributedString('Hello World');
        $str->delete_(5, 11); // Delete " World" (positions 5-11)

        $this->assertSame(5, $str->length());
    }

    public function testAttributedStringSetAttribute(): void
    {
        $str = new AttributedString('Hello World');
        $attr = new Attribute(AttributeType::Color, 0, 5);

        $result = $str->setAttribute($attr);

        $this->assertSame($str, $result);
    }

    public function testAttributedStringFree(): void
    {
        $str = new AttributedString('Test');
        $str->free();

        $this->assertTrue(true, 'AttributedString::free() should complete without error');
    }

    public function testAttributedStringFreeIsIdempotent(): void
    {
        $str = new AttributedString('Test');
        $str->free();
        $str->free(); // must be a no-op, not a double uiFreeAttributedString abort

        $this->assertTrue(true, 'free() twice did not abort');
    }

    public function testTextLayoutOutlivesItsSourceStringScope(): void
    {
        // The source AttributedString's only local reference is dropped inside the
        // closure. TextLayout must RETAIN it — otherwise AttributedString::__destruct
        // frees the string while the layout still points at it (use-after-free).
        $layout = (static function (): TextLayout {
            $string = new AttributedString('Hello world');
            return new TextLayout($string, new FontDescriptor(), 200.0);
        })();
        gc_collect_cycles();

        $extents = $layout->extents();

        $this->assertCount(2, $extents);
        $this->assertGreaterThan(0.0, $extents[0]); // measured a real, non-garbage width
    }

    // ========================================================================
    // ATTRIBUTE TESTS
    // ========================================================================

    public function testAttributeConstructs(): void
    {
        $attr = new Attribute(AttributeType::Color, 0, 10);
        $this->assertInstanceOf(Attribute::class, $attr);
    }

    public function testAttributeHandle(): void
    {
        $attr = new Attribute(AttributeType::Weight, 0, 5);
        $handle = $attr->handle();

        $this->assertInstanceOf(\FFI\CData::class, $handle);
        $this->assertFalse(\FFI::isNull($handle));
    }

    public function testAttributeWithColor(): void
    {
        // Color attributes use RGBA values
        $attr = new Attribute(AttributeType::Color, 0, 10, 1.0, 0.5, 0.25, 1.0);
        $this->assertInstanceOf(Attribute::class, $attr);
    }

    public function testAttributeWithBackgroundColor(): void
    {
        $attr = new Attribute(AttributeType::Background, 0, 10, 0.0, 1.0, 0.0, 1.0);
        $this->assertInstanceOf(Attribute::class, $attr);
    }

    public function testAttributeWithUnderline(): void
    {
        $attr = new Attribute(AttributeType::Underline, 0, 10, Underline::Single);
        $this->assertInstanceOf(Attribute::class, $attr);
    }

    public function testAttributeWithUnderlineColor(): void
    {
        $attr = new Attribute(AttributeType::UnderlineColor, 0, 10, UnderlineColor::Custom);
        $this->assertInstanceOf(Attribute::class, $attr);
    }

    public function testAttributeWithWeight(): void
    {
        $attr = new Attribute(AttributeType::Weight, 0, 10, TextWeight::Bold);
        $this->assertInstanceOf(Attribute::class, $attr);
    }

    public function testAttributeWithItalic(): void
    {
        $attr = new Attribute(AttributeType::Italic, 0, 10, TextItalic::Italic);
        $this->assertInstanceOf(Attribute::class, $attr);
    }

    public function testAttributeWithStretch(): void
    {
        $attr = new Attribute(AttributeType::Stretch, 0, 10, TextStretch::Expanded);
        $this->assertInstanceOf(Attribute::class, $attr);
    }

    public function testAttributeFromColorBuildsValidColorAttribute(): void
    {
        $attr = Attribute::fromColor(Color::rgb(0x80_4020, 0.5));

        $this->assertInstanceOf(Attribute::class, $attr);
        $this->assertFalse(\FFI::isNull($attr->handle()));
    }

    public function testAttributeBackgroundFromColorBuildsValidAttribute(): void
    {
        $attr = Attribute::backgroundFromColor(Color::white());

        $this->assertInstanceOf(Attribute::class, $attr);
        $this->assertFalse(\FFI::isNull($attr->handle()));
    }

    public function testTextStyleBuildsStretchAttribute(): void
    {
        $style = new TextStyle(stretch: TextStretch::Expanded);

        $attributes = $style->attributes();

        $this->assertCount(1, $attributes);
        $this->assertInstanceOf(Attribute::class, $attributes[0]);
    }

    public function testAttributeWithFontFamily(): void
    {
        $attr = new Attribute(AttributeType::Family, 0, 10, 'Arial');
        $this->assertInstanceOf(Attribute::class, $attr);
    }

    public function testAttributeWithFontSize(): void
    {
        $attr = new Attribute(AttributeType::Size, 0, 10, 14.0);
        $this->assertInstanceOf(Attribute::class, $attr);
    }

    public function testAttributeFree(): void
    {
        $attr = new Attribute(AttributeType::Weight, 0, 5, TextWeight::Bold);
        $attr->free();

        $this->assertTrue(true, 'Attribute::free() should complete without error');
    }

    // ========================================================================
    // FONT DESCRIPTOR TESTS
    // ========================================================================

    public function testFontDescriptorConstructs(): void
    {
        $font = new FontDescriptor();
        $this->assertInstanceOf(FontDescriptor::class, $font);
    }

    public function testFontDescriptorHandle(): void
    {
        $font = new FontDescriptor();
        $handle = $font->handle();

        $this->assertInstanceOf(\FFI\CData::class, $handle);
        $this->assertFalse(\FFI::isNull($handle));
    }

    public function testFontDescriptorWithAllOptions(): void
    {
        $font = new FontDescriptor(
            family: 'Arial',
            size: 14.0,
            weight: TextWeight::Bold,
            italic: TextItalic::Italic,
            stretch: TextStretch::Expanded,
        );

        $this->assertInstanceOf(FontDescriptor::class, $font);
    }

    public function testFontDescriptorGettersReadBackTheStruct(): void
    {
        $font = new FontDescriptor('Helvetica', 18.0, TextWeight::Bold, TextItalic::Italic, TextStretch::Expanded);

        $this->assertSame('Helvetica', $font->family());
        $this->assertSame(18.0, $font->size());
        $this->assertSame(TextWeight::Bold, $font->weight());
        $this->assertSame(TextItalic::Italic, $font->italic());
        $this->assertSame(TextStretch::Expanded, $font->stretch());
    }

    public function testFontDescriptorFromCDataRoundTrips(): void
    {
        $original = new FontDescriptor('Courier', 12.0, weight: TextWeight::Medium);
        $copy = FontDescriptor::fromCData($original->toCData());

        $this->assertSame('Courier', $copy->family());
        $this->assertSame(12.0, $copy->size());
        $this->assertSame(TextWeight::Medium, $copy->weight());
    }

    public function testFontDescriptorSetFamily(): void
    {
        $font = new FontDescriptor();
        $result = $font->setFamily('Helvetica');

        $this->assertSame($font, $result);
    }

    public function testFontDescriptorSetSize(): void
    {
        $font = new FontDescriptor();
        $result = $font->setSize(16.0);

        $this->assertSame($font, $result);
    }

    public function testFontDescriptorSetWeight(): void
    {
        $font = new FontDescriptor();
        $result = $font->setWeight(TextWeight::Bold);

        $this->assertSame($font, $result);
    }

    public function testFontDescriptorSetItalic(): void
    {
        $font = new FontDescriptor();
        $result = $font->setItalic(TextItalic::Italic);

        $this->assertSame($font, $result);
    }

    public function testFontDescriptorSetStretch(): void
    {
        $font = new FontDescriptor();
        $result = $font->setStretch(TextStretch::Condensed);

        $this->assertSame($font, $result);
    }

    public function testFontDescriptorFree(): void
    {
        $font = new FontDescriptor();
        $font->free();

        $this->assertTrue(true, 'FontDescriptor::free() should complete without error');
    }

    // ========================================================================
    // TEXT LAYOUT TESTS
    // ========================================================================

    public function testTextLayoutConstructs(): void
    {
        $str = new AttributedString('Hello');
        $layout = new TextLayout($str);

        $this->assertInstanceOf(TextLayout::class, $layout);
    }

    public function testTextLayoutHandle(): void
    {
        $str = new AttributedString('Test');
        $layout = new TextLayout($str);
        $handle = $layout->handle();

        $this->assertInstanceOf(\FFI\CData::class, $handle);
        $this->assertFalse(\FFI::isNull($handle));
    }

    public function testTextLayoutSetWidth(): void
    {
        $str = new AttributedString('Hello World');
        $layout = new TextLayout($str);
        $result = $layout->setWidth(100.0);

        $this->assertSame($layout, $result);
    }

    public function testTextLayoutWidth(): void
    {
        $str = new AttributedString('Hello World');
        $layout = new TextLayout($str);
        $layout->setWidth(200.0);

        $width = $layout->width();
        $this->assertIsFloat($width);
    }

    public function testTextLayoutHeight(): void
    {
        $str = new AttributedString('Hello World');
        $layout = new TextLayout($str);

        $height = $layout->height();
        $this->assertIsFloat($height);
    }

    public function testTextLayoutExtents(): void
    {
        $str = new AttributedString('Hello World');
        $layout = new TextLayout($str);

        $extents = $layout->extents();
        $this->assertIsArray($extents);
        $this->assertCount(2, $extents);
    }

    public function testTextLayoutFree(): void
    {
        $str = new AttributedString('Test');
        $layout = new TextLayout($str);
        $layout->free();

        $this->assertTrue(true, 'TextLayout::free() should complete without error');
    }

    // ========================================================================
    // INTEGRATION TESTS
    // ========================================================================

    public function testAttributedStringWithMultipleAttributes(): void
    {
        $str = new AttributedString('Hello World');

        // Add color attribute for "Hello"
        $str->setAttribute(new Attribute(AttributeType::Color, 0, 5, 1.0, 0.0, 0.0, 1.0));

        // Add weight attribute for "World"
        $str->setAttribute(new Attribute(AttributeType::Weight, 6, 11, TextWeight::Bold));

        // Add italic attribute for the whole string
        $str->setAttribute(new Attribute(AttributeType::Italic, 0, 11, TextItalic::Italic));

        $this->assertSame(11, $str->length());
    }

    public function testAttributedStringWithFontAttributes(): void
    {
        $str = new AttributedString('Styled Text');

        // Set font family
        $str->setAttribute(new Attribute(AttributeType::Family, 0, 11, 'Arial'));

        // Set font size
        $str->setAttribute(new Attribute(AttributeType::Size, 0, 11, 16.0));

        // Set font weight
        $str->setAttribute(new Attribute(AttributeType::Weight, 0, 11, TextWeight::Bold));

        $this->assertSame(11, $str->length());
    }

    public function testAttributedStringWithUnderline(): void
    {
        $str = new AttributedString('Underlined Text');

        $str->setAttribute(new Attribute(AttributeType::Underline, 0, 15, Underline::Single));
        $str->setAttribute(new Attribute(AttributeType::UnderlineColor, 0, 15, UnderlineColor::Custom));

        $this->assertSame(15, $str->length());
    }

    public function testTextLayoutWithFontDescriptor(): void
    {
        $str = new AttributedString('Hello World');
        $layout = new TextLayout($str);

        $font = new FontDescriptor();
        $font->setFamily('Arial');
        $font->setSize(14.0);

        $layout->setFont($font);

        $this->assertInstanceOf(TextLayout::class, $layout);
    }

    public function testTextLayoutChaining(): void
    {
        $str = new AttributedString('Hello');
        $layout = new TextLayout($str);

        $result = $layout->setWidth(100.0);

        $this->assertSame($layout, $result);
    }

    public function testFontDescriptorChaining(): void
    {
        $font = new FontDescriptor();

        $result = $font
            ->setFamily('Arial')
            ->setSize(14.0)
            ->setWeight(TextWeight::Bold)
            ->setItalic(TextItalic::Italic);

        $this->assertSame($font, $result);
    }

    // ========================================================================
    // ENUM COVERAGE FOR TEXT
    // ========================================================================

    public function testTextWeightValues(): void
    {
        $this->assertSame(0, TextWeight::Minimum->value);
        $this->assertSame(400, TextWeight::Normal->value);
        $this->assertSame(700, TextWeight::Bold->value);
        $this->assertSame(1000, TextWeight::Maximum->value);
    }

    public function testTextItalicValues(): void
    {
        $this->assertSame(0, TextItalic::Normal->value);
        $this->assertSame(1, TextItalic::Oblique->value);
        $this->assertSame(2, TextItalic::Italic->value);
    }

    public function testTextStretchValues(): void
    {
        $this->assertSame(0, TextStretch::UltraCondensed->value);
        $this->assertSame(4, TextStretch::Normal->value);
        $this->assertSame(8, TextStretch::UltraExpanded->value);
    }

    public function testUnderlineValues(): void
    {
        $this->assertSame(0, Underline::None->value);
        $this->assertSame(1, Underline::Single->value);
        $this->assertSame(2, Underline::Double->value);
        $this->assertSame(3, Underline::Suggestion->value);
    }

    public function testUnderlineColorValues(): void
    {
        $this->assertSame(0, UnderlineColor::Custom->value);
        $this->assertSame(1, UnderlineColor::Spelling->value);
        $this->assertSame(2, UnderlineColor::Grammar->value);
        $this->assertSame(3, UnderlineColor::Auxiliary->value);
    }

    // ========================================================================
    // COMPLEX SCENARIOS
    // ========================================================================

    public function testComplexAttributedString(): void
    {
        $str = new AttributedString('Hello Beautiful World');

        // "Hello" in red bold (positions 0-4, length 5)
        $str->setAttribute(new Attribute(AttributeType::Color, 0, 5, 1.0, 0.0, 0.0, 1.0));
        $str->setAttribute(new Attribute(AttributeType::Weight, 0, 5, TextWeight::Bold));

        // " Beautiful" in green italic (positions 5-14, length 10 including spaces)
        $str->setAttribute(new Attribute(AttributeType::Color, 5, 15, 0.0, 1.0, 0.0, 1.0));
        $str->setAttribute(new Attribute(AttributeType::Italic, 5, 15, TextItalic::Italic));

        // "World" in blue underlined (positions 15-20, length 5)
        $str->setAttribute(new Attribute(AttributeType::Color, 15, 20, 0.0, 0.0, 1.0, 1.0));
        $str->setAttribute(new Attribute(AttributeType::Underline, 15, 20, Underline::Single));

        $this->assertSame(21, $str->length());
    }

    public function testAttributedStringManipulation(): void
    {
        $str = new AttributedString('Hello');

        // Append
        $str->append(' World');
        $this->assertSame(11, $str->length());

        // Insert " Beautiful " (11 bytes) at position 6 in "Hello World" (11 bytes)
        // Result: "Hello  Beautiful World" (22 bytes)
        $str->insert(' Beautiful ', 6);
        $this->assertSame(22, $str->length());

        // Delete from position 6 to 17 (the " Beautiful " text)
        // Note: after insert, " Beautiful " is at positions 6-16 (11 bytes)
        $str->delete_(6, 17); // Remove " Beautiful "
        $this->assertSame(11, $str->length());
    }

    public function testFontDescriptorFullConfiguration(): void
    {
        $font = new FontDescriptor();

        $font->setFamily('Helvetica');
        $font->setSize(16.0);
        $font->setWeight(TextWeight::SemiBold);
        $font->setItalic(TextItalic::Italic);
        $font->setStretch(TextStretch::Expanded);

        $this->assertInstanceOf(FontDescriptor::class, $font);
    }

    public function testTextLayoutWithWidthConstraint(): void
    {
        $str = new AttributedString('This is a long text that needs wrapping');
        $layout = new TextLayout($str);

        $layout->setWidth(100.0);

        $width = $layout->width();
        $height = $layout->height();

        $this->assertIsFloat($width);
        $this->assertIsFloat($height);
    }

    // ========================================================================
    // TEXT STYLE
    // ========================================================================

    public function testTextStyleEmptyProducesNoAttributes(): void
    {
        $this->assertSame([], new TextStyle()->attributes());
    }

    public function testTextStyleAcceptsColorObjectsAndNormalizesToArray(): void
    {
        $style = new TextStyle(color: Color::rgb(0xFF_8000), background: Color::white());

        $this->assertSame([1.0, 0x80 / 255, 0.0, 1.0], $style->color);
        $this->assertSame([1.0, 1.0, 1.0, 1.0], $style->background);
    }

    public function testTextStyleStillAcceptsColorArrays(): void
    {
        $style = new TextStyle(color: [0.1, 0.2, 0.3]);

        $this->assertSame([0.1, 0.2, 0.3, 1.0], $style->color);
    }

    public function testTextStyleWithAcceptsColor(): void
    {
        $derived = new TextStyle(color: [0.0, 0.0, 0.0])->with(color: Color::rgb(0x00_FF00));

        $this->assertSame([0.0, 1.0, 0.0, 1.0], $derived->color);
    }

    public function testTextStyleFontFallsBackToDefaults(): void
    {
        $font = new TextStyle()->font();

        $this->assertInstanceOf(FontDescriptor::class, $font);
        $this->assertFalse(\FFI::isNull($font->handle()));
    }

    public function testTextStyleEmitsAnAttributePerSetField(): void
    {
        $style = new TextStyle(
            family: 'Arial',
            size: 14.0,
            weight: TextWeight::Bold,
            italic: TextItalic::Italic,
            stretch: TextStretch::Expanded,
            color: [1.0, 0.0, 0.0],
            background: [0.0, 1.0, 0.0, 0.5],
            underline: Underline::Single,
        );

        $attributes = $style->attributes();

        $this->assertCount(8, $attributes);
        $this->assertContainsOnlyInstancesOf(Attribute::class, $attributes);
    }

    public function testTextStyleWithMergesOverridesAndKeepsTheRest(): void
    {
        $base = new TextStyle(family: 'Arial', weight: TextWeight::Bold);
        $derived = $base->with(weight: TextWeight::Normal);

        // family carries over, weight is overridden, original is untouched.
        $this->assertSame('Arial', $derived->family);
        $this->assertSame(TextWeight::Normal, $derived->weight);
        $this->assertSame(TextWeight::Bold, $base->weight);
    }

    // ========================================================================
    // RICH TEXT
    // ========================================================================

    public function testRichTextCreateReturnsInstance(): void
    {
        $this->assertInstanceOf(RichText::class, RichText::create());
    }

    public function testRichTextAppendIsFluentAndGrowsTheString(): void
    {
        $rich = RichText::create();
        $result = $rich->append('Hello', new TextStyle(weight: TextWeight::Bold));

        $this->assertSame($rich, $result);
        $this->assertSame(5, $rich->string()->length());

        $rich->append(' World');
        $this->assertSame(11, $rich->string()->length());
    }

    public function testRichTextLayoutReturnsTextLayout(): void
    {
        $layout = RichText::create()->append('Hello')->layout(200.0);

        $this->assertInstanceOf(TextLayout::class, $layout);
        $layout->free();
    }

    public function testRichTextMeasureReturnsExtents(): void
    {
        $extents = RichText::create()->append('Hello World')->measure(200.0);

        $this->assertCount(2, $extents);
        $this->assertIsFloat($extents[0]);
        $this->assertIsFloat($extents[1]);
    }

    public function testRichTextHeightIsNonNegative(): void
    {
        $height = RichText::create()->append('Hello World')->height(200.0);

        $this->assertIsFloat($height);
        $this->assertGreaterThanOrEqual(0.0, $height);
    }
}
