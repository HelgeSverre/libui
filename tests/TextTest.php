<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Generated\Enum\At;
use Libui\Generated\Enum\AttributeType;
use Libui\Generated\Enum\TextItalic;
use Libui\Generated\Enum\TextStretch;
use Libui\Generated\Enum\TextWeight;
use Libui\Generated\Enum\Underline;
use Libui\Generated\Enum\UnderlineColor;
use Libui\Text\Attribute;
use Libui\Text\AttributedString;
use Libui\Text\FontDescriptor;
use Libui\Text\TextLayout;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the text subsystem (AttributedString, Attribute, FontDescriptor, TextLayout).
 * These classes are used for rich text rendering with custom attributes.
 */
final class TextTest extends TestCase
{
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

        $this->assertSame(19, $str->length());
    }

    public function testAttributedStringDelete(): void
    {
        $str = new AttributedString('Hello World');
        $str->delete_(5, 6); // Delete " World"

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
        $attr = new Attribute(AttributeType::BackgroundColor, 0, 10, 0.0, 1.0, 0.0, 1.0);
        $this->assertInstanceOf(Attribute::class, $attr);
    }

    public function testAttributeWithUnderline(): void
    {
        $attr = new Attribute(AttributeType::Underline, 0, 10, Underline::Single);
        $this->assertInstanceOf(Attribute::class, $attr);
    }

    public function testAttributeWithUnderlineColor(): void
    {
        $attr = new Attribute(AttributeType::UnderlineColor, 0, 10, UnderlineColor::Color);
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

    public function testAttributeWithFontFamily(): void
    {
        $attr = new Attribute(AttributeType::FontFamily, 0, 10, 'Arial');
        $this->assertInstanceOf(Attribute::class, $attr);
    }

    public function testAttributeWithFontSize(): void
    {
        $attr = new Attribute(AttributeType::FontSize, 0, 10, 14.0);
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
        $this->assertInstanceOf(\FFI\CData::class, $extents);
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
        $str->setAttribute(new Attribute(AttributeType::FontFamily, 0, 11, 'Arial'));

        // Set font size
        $str->setAttribute(new Attribute(AttributeType::FontSize, 0, 11, 16.0));

        // Set font weight
        $str->setAttribute(new Attribute(AttributeType::Weight, 0, 11, TextWeight::Bold));

        $this->assertSame(11, $str->length());
    }

    public function testAttributedStringWithUnderline(): void
    {
        $str = new AttributedString('Underlined Text');

        $str->setAttribute(new Attribute(AttributeType::Underline, 0, 15, Underline::Single));
        $str->setAttribute(new Attribute(AttributeType::UnderlineColor, 0, 15, UnderlineColor::Color));

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
        $this->assertSame(1, TextItalic::Italic->value);
        $this->assertSame(2, TextItalic::Oblique->value);
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
        $this->assertSame(0, UnderlineColor::Spacing->value);
        $this->assertSame(1, UnderlineColor::Color->value);
    }

    // ========================================================================
    // COMPLEX SCENARIOS
    // ========================================================================

    public function testComplexAttributedString(): void
    {
        $str = new AttributedString('Hello Beautiful World');

        // "Hello" in red bold
        $str->setAttribute(new Attribute(AttributeType::Color, 0, 5, 1.0, 0.0, 0.0, 1.0));
        $str->setAttribute(new Attribute(AttributeType::Weight, 0, 5, TextWeight::Bold));

        // " Beautiful" in green italic
        $str->setAttribute(new Attribute(AttributeType::Color, 5, 14, 0.0, 1.0, 0.0, 1.0));
        $str->setAttribute(new Attribute(AttributeType::Italic, 5, 14, TextItalic::Italic));

        // "World" in blue underlined
        $str->setAttribute(new Attribute(AttributeType::Color, 14, 19, 0.0, 0.0, 1.0, 1.0));
        $str->setAttribute(new Attribute(AttributeType::Underline, 14, 19, Underline::Single));

        $this->assertSame(19, $str->length());
    }

    public function testAttributedStringManipulation(): void
    {
        $str = new AttributedString('Hello');

        // Append
        $str->append(' World');
        $this->assertSame(11, $str->length());

        // Insert
        $str->insert(' Beautiful ', 6);
        $this->assertSame(21, $str->length());

        // Delete
        $str->delete_(6, 10); // Remove " Beautiful"
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
}
