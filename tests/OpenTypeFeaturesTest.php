<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Generated\Enum\AttributeType;
use Libui\Text\Attribute;
use Libui\Text\OpenTypeFeatures;

final class OpenTypeFeaturesTest extends LibuiTestCase
{
    public function testConstructsWithNonNullHandle(): void
    {
        $features = new OpenTypeFeatures();
        $this->assertFalse(\FFI::isNull($features->handle()));
    }

    public function testAddReturnsSameInstance(): void
    {
        $features = new OpenTypeFeatures();
        $this->assertSame($features, $features->add('liga', 1));
    }

    public function testGetRoundTripsAddedFeature(): void
    {
        $features = new OpenTypeFeatures();
        $features->add('liga', 1);

        $this->assertSame(1, $features->get('liga'));
    }

    public function testGetReturnsNullForMissingFeature(): void
    {
        $features = new OpenTypeFeatures();
        $features->add('liga', 1);

        $this->assertNull($features->get('zzzz'));
    }

    public function testAddRejectsShortTag(): void
    {
        $features = new OpenTypeFeatures();
        $this->expectException(\InvalidArgumentException::class);
        $features->add('lig', 1);
    }

    public function testFeaturesAttributeConstructs(): void
    {
        $features = new OpenTypeFeatures();
        $features->add('liga', 1);

        $attribute = new Attribute(AttributeType::Features, 0, 4, $features);

        $this->assertInstanceOf(Attribute::class, $attribute);
        $this->assertFalse(\FFI::isNull($attribute->handle()));
    }

    public function testFeaturesAttributeRejectsNonFeaturesParam(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Attribute(AttributeType::Features, 0, 4, 'not-features');
    }
}
